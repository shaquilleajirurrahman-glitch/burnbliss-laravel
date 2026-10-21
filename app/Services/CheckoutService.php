<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Models\DailyQuota;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Support\PreOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Checkout tamu: membuat pesanan dari keranjang session dan menerima bukti pembayaran.
 */
class CheckoutService
{
    /** Huruf/angka tanpa yang mirip (0/O, 1/I/L) agar kode mudah dibaca & diketik. */
    private const ALFABET_KODE = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const PANJANG_KODE = 6;

    public function __construct(private CartService $cart) {}

    /**
     * Buat pesanan dari isi keranjang. Kuota harian & stok dicek dan dikurangi dalam satu
     * transaksi dengan row lock, sehingga dua checkout bersamaan tidak bisa melebihi kapasitas.
     *
     * Status awal:
     *  - Transfer/QRIS -> Menunggu Pembayaran (pelanggan perlu mengunggah bukti).
     *  - Tunai -> langsung Dikonfirmasi agar masuk antrean produksi, karena uang baru diterima
     *    saat serah terima (setelah produksi). Pembayarannya dicatat terpisah di tabel payments
     *    dengan status Menunggu Pembayaran sampai staf menandainya lunas.
     *
     * @param  array{nama_pelanggan: string, nomor_hp: string, alamat?: ?string, metode_pengiriman: string, metode_pembayaran: string, tanggal_ambil: string}  $data
     *
     * @throws ValidationException jika keranjang kosong, kuota penuh, atau stok kurang
     */
    public function placeOrder(array $data): Order
    {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['keranjang' => 'Keranjang Anda kosong.']);
        }

        $metode = ShippingMethod::from($data['metode_pengiriman']);
        $pembayaran = PaymentMethod::from($data['metode_pembayaran']);
        $tanggal = $data['tanggal_ambil'];
        $totalPcs = $lines->sum(fn (CartLine $line) => $line->jumlah);

        $order = DB::transaction(function () use ($data, $lines, $metode, $pembayaran, $tanggal, $totalPcs) {
            $kuota = $this->lockKuota($tanggal);

            if ($kuota->sisa() < $totalPcs) {
                $label = PreOrder::format($tanggal);

                throw ValidationException::withMessages([
                    'tanggal_ambil' => $kuota->sisa() === 0
                        ? "Kuota produksi untuk {$label} sudah penuh. Silakan pilih tanggal lain."
                        : "Kuota produksi untuk {$label} tinggal {$kuota->sisa()} pcs, sedangkan pesanan Anda {$totalPcs} pcs. Pilih tanggal lain atau kurangi jumlah.",
                ]);
            }

            $variants = ProductVariant::with('product')
                ->whereIn('id', $lines->map(fn (CartLine $line) => $line->variant->id))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($lines as $line) {
                $variant = $variants->get($line->variant->id);

                if (! $variant || $variant->stok < $line->jumlah) {
                    throw ValidationException::withMessages([
                        'keranjang' => "Stok {$line->variant->nama_varian} tidak mencukupi (tersisa ".($variant->stok ?? 0).'). Silakan perbarui keranjang Anda.',
                    ]);
                }
            }

            $order = Order::create([
                'kode_pesanan' => $this->kodeUnik(),
                'nama_pelanggan' => $data['nama_pelanggan'],
                'nomor_hp' => $data['nomor_hp'],
                'alamat' => $metode === ShippingMethod::Diantar ? $data['alamat'] : null,
                'metode_pengiriman' => $metode,
                'metode_pembayaran' => $pembayaran,
                'tanggal_ambil' => $tanggal,
                'status' => $pembayaran->butuhBukti() ? OrderStatus::MenungguPembayaran : OrderStatus::Dikonfirmasi,
                // Harga dihitung ulang dari data varian yang baru dikunci.
                'total_harga' => $lines->sum(fn (CartLine $line) => $variants[$line->variant->id]->harga_akhir * $line->jumlah),
            ]);

            foreach ($lines as $line) {
                $variant = $variants[$line->variant->id];

                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'jumlah' => $line->jumlah,
                    'harga_satuan' => $variant->harga_akhir, // snapshot harga saat checkout
                    'subtotal' => $variant->harga_akhir * $line->jumlah,
                ]);

                $variant->decrement('stok', $line->jumlah);
            }

            $kuota->increment('kuota_terpakai', $totalPcs);

            if (! $pembayaran->butuhBukti()) {
                // Tunai: catat tagihan yang akan dilunasi saat serah terima.
                $order->payments()->create([
                    'metode' => PaymentMethod::Cash,
                    'jumlah' => $order->total_harga,
                    'status' => PaymentStatus::MenungguPembayaran,
                ]);
            }

            $order->statusLogs()->create([
                'status_baru' => $order->status,
                'keterangan' => $pembayaran->butuhBukti()
                    ? "Pesanan dibuat oleh pelanggan (checkout tamu, {$pembayaran->label()})."
                    : 'Pesanan tunai dibuat oleh pelanggan; dikonfirmasi otomatis, dibayar saat serah terima.',
            ]);

            return $order;
        });

        $this->cart->clear();

        return $order;
    }

    /**
     * Simpan bukti pembayaran (transfer/QRIS sesuai pilihan saat checkout) dan ubah status
     * pesanan menjadi Menunggu Verifikasi.
     *
     * @throws ValidationException jika pesanan tunai atau tidak sedang menunggu pembayaran
     */
    public function uploadProof(Order $order, UploadedFile $file): Payment
    {
        $this->ensureMenerimaBukti($order);

        $disk = config('burnbliss.bukti_bayar.disk');
        $path = $file->store(config('burnbliss.bukti_bayar.folder'), $disk); // nama file acak

        try {
            return DB::transaction(function () use ($order, $path) {
                // Kunci baris pesanan agar dua upload bersamaan tidak sama-sama diterima.
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $this->ensureMenerimaBukti($order);
                $metode = $order->metode_pembayaran;

                $payment = $order->payments()->create([
                    'metode' => $metode,
                    'jumlah' => $order->total_harga,
                    'status' => PaymentStatus::MenungguVerifikasi,
                    'bukti_transfer' => $path,
                ]);

                $statusLama = $order->status;
                $order->update(['status' => OrderStatus::MenungguVerifikasi]);

                $order->statusLogs()->create([
                    'status_lama' => $statusLama,
                    'status_baru' => OrderStatus::MenungguVerifikasi,
                    'keterangan' => "Bukti pembayaran ({$metode->label()}) diunggah pelanggan.",
                ]);

                return $payment;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path); // jangan tinggalkan file yatim

            throw $e;
        }
    }

    private function ensureMenerimaBukti(Order $order): void
    {
        if (! $order->metode_pembayaran->butuhBukti()) {
            throw ValidationException::withMessages([
                'bukti' => 'Pesanan ini dibayar tunai saat serah terima, tidak perlu mengunggah bukti pembayaran.',
            ]);
        }

        if (! $order->status->menerimaBuktiBayar()) {
            throw ValidationException::withMessages([
                'bukti' => "Pesanan ini berstatus \"{$order->status->label()}\", bukti pembayaran tidak bisa diunggah lagi.",
            ]);
        }
    }

    /**
     * Ambil & kunci baris kuota untuk tanggal tersebut; buat dengan kuota default jika belum ada.
     */
    private function lockKuota(string $tanggal): DailyQuota
    {
        DailyQuota::query()->insertOrIgnore([
            'tanggal' => $tanggal,
            'kuota_maksimal' => config('burnbliss.kuota_default'),
            'kuota_terpakai' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DailyQuota::where('tanggal', $tanggal)->lockForUpdate()->firstOrFail();
    }

    /**
     * Kode pesanan acak, mis. BNB-2026-K7QX9M (31^6 ≈ 887 juta kombinasi per tahun).
     */
    private function kodeUnik(): string
    {
        do {
            $acak = '';
            for ($i = 0; $i < self::PANJANG_KODE; $i++) {
                $acak .= self::ALFABET_KODE[random_int(0, strlen(self::ALFABET_KODE) - 1)];
            }
            $kode = 'BNB-'.now()->format('Y').'-'.$acak;
        } while (Order::where('kode_pesanan', $kode)->exists());

        return $kode;
    }
}
