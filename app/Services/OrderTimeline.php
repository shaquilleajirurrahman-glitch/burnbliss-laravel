<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Support\PreOrder;
use App\Support\Rupiah;
use Carbon\CarbonInterface;

/**
 * Data publik untuk halaman Lacak Pesanan: ringkasan pesanan + timeline status.
 * Sengaja TIDAK memuat nama petugas, nomor HP lengkap, atau catatan internal.
 */
class OrderTimeline
{
    /**
     * @return array{order: array<string, mixed>, timeline: list<array<string, mixed>>}
     */
    public function toArray(Order $order): array
    {
        $order->loadMissing(['items.productVariant', 'statusLogs', 'latestPayment']);
        $payment = $order->latestPayment;
        $ditolak = $payment?->status === PaymentStatus::Ditolak && $order->status === OrderStatus::MenungguPembayaran;

        return [
            'order' => [
                'kode' => $order->kode_pesanan,
                'dibuat' => $this->waktu($order->created_at),
                'nama' => $order->nama_pelanggan,
                'nomor_hp' => $order->nomorHpTersamar(),
                'total_formatted' => Rupiah::format($order->total_harga),
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'pengambilan' => $order->metode_pengiriman->label().' · '.PreOrder::format($order->tanggal_ambil->toImmutable()),
                'metode_pembayaran' => $order->metode_pembayaran->label(),
                'status_pembayaran' => $payment?->status->label(),
                'items' => $order->items->map(fn ($item) => [
                    'nama' => $item->productVariant->nama_varian,
                    'jumlah' => $item->jumlah,
                    'subtotal_formatted' => Rupiah::format($item->subtotal),
                ])->values()->all(),
                // Satu-satunya catatan yang ditampilkan ke pelanggan: alasan bukti bayar ditolak.
                'catatan_penolakan' => $ditolak ? ($payment->catatan ?: 'Bukti pembayaran tidak sesuai.') : null,
                'pembayaran_url' => $order->metode_pembayaran->butuhBukti() && $order->status->menerimaBuktiBayar()
                    ? route('checkout.pembayaran', $order)
                    : null,
            ],
            'timeline' => $this->langkah($order),
        ];
    }

    /**
     * Langkah-langkah status dengan state: done | current | upcoming | cancelled.
     *
     * @return list<array{status: string, label: string, keterangan: string, state: string, waktu: ?string}>
     */
    private function langkah(Order $order): array
    {
        // Tunai tidak melewati tahap pembayaran/verifikasi (langsung dikonfirmasi).
        $alur = $order->metode_pembayaran->butuhBukti()
            ? [OrderStatus::MenungguPembayaran, OrderStatus::MenungguVerifikasi, OrderStatus::Dikonfirmasi, OrderStatus::Diproses, OrderStatus::Siap, OrderStatus::Selesai]
            : [OrderStatus::Dikonfirmasi, OrderStatus::Diproses, OrderStatus::Siap, OrderStatus::Selesai];

        $logs = $order->statusLogs;
        $dibatalkan = $order->status === OrderStatus::Dibatalkan;

        // Posisi terakhir yang dicapai dalam alur (untuk pesanan dibatalkan: status sebelum batal).
        $posisiStatus = $dibatalkan
            ? $logs->where('status_baru', OrderStatus::Dibatalkan)->last()?->status_lama
            : $order->status;
        $indeksSaatIni = array_search($posisiStatus, $alur, true);
        $indeksSaatIni = $indeksSaatIni === false ? -1 : $indeksSaatIni;

        $langkah = [];
        foreach ($alur as $i => $status) {
            if ($dibatalkan && $i > $indeksSaatIni) {
                break; // langkah setelah pembatalan tidak relevan
            }

            $state = match (true) {
                $i < $indeksSaatIni => 'done',
                $i === $indeksSaatIni && ($dibatalkan || $status === OrderStatus::Selesai) => 'done',
                $i === $indeksSaatIni => 'current',
                default => 'upcoming',
            };

            $langkah[] = $this->item($status, $state, $logs->where('status_baru', $status)->last(), $order);
        }

        if ($dibatalkan) {
            $langkah[] = $this->item(OrderStatus::Dibatalkan, 'cancelled', $logs->where('status_baru', OrderStatus::Dibatalkan)->last(), $order);
        }

        return $langkah;
    }

    /**
     * @return array{status: string, label: string, keterangan: string, state: string, waktu: ?string}
     */
    private function item(OrderStatus $status, string $state, ?OrderStatusLog $log, Order $order): array
    {
        return [
            'status' => $status->value,
            'label' => $status->label(),
            'keterangan' => $this->keterangan($status, $order),
            'state' => $state,
            'waktu' => $state === 'upcoming' ? null : $this->waktu($log?->created_at),
        ];
    }

    private function keterangan(OrderStatus $status, Order $order): string
    {
        $diantar = $order->metode_pengiriman === ShippingMethod::Diantar;

        return match ($status) {
            OrderStatus::MenungguPembayaran => 'Selesaikan pembayaran lalu unggah bukti transfer/QRIS.',
            OrderStatus::MenungguVerifikasi => 'Tim kami sedang memeriksa pembayaran Anda.',
            OrderStatus::Dikonfirmasi => $order->metode_pembayaran->butuhBukti()
                ? 'Pembayaran diterima, pesanan masuk antrean produksi.'
                : 'Pesanan tunai dikonfirmasi dan masuk antrean produksi.',
            OrderStatus::Diproses => 'Cheesecake Anda sedang dipanggang.',
            OrderStatus::Siap => $diantar ? 'Pesanan siap dan segera diantar.' : 'Pesanan siap diambil di toko.',
            OrderStatus::Selesai => 'Pesanan telah diterima. Terima kasih!',
            OrderStatus::Dibatalkan => 'Pesanan dibatalkan.',
        };
    }

    private function waktu(?CarbonInterface $waktu): ?string
    {
        return $waktu?->locale('id')->translatedFormat('j M Y, H.i');
    }
}
