<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Rupiah;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Perubahan status pesanan oleh tim internal: verifikasi pembayaran, alur produksi,
 * dan pelunasan tunai. Setiap aksi mengunci baris pesanan (lockForUpdate) agar dua
 * petugas yang menekan tombol bersamaan tidak menghasilkan perubahan ganda.
 */
class OrderWorkflow
{
    /**
     * Terima/tolak bukti transfer/QRIS.
     *  - Terima: pembayaran Diterima, pesanan -> Dikonfirmasi (masuk antrean produksi).
     *  - Tolak : pembayaran Ditolak, pesanan -> Menunggu Pembayaran (pelanggan bisa unggah ulang).
     *
     * @throws ValidationException jika pesanan tidak sedang menunggu verifikasi
     */
    public function verifyPayment(Order $order, User $petugas, bool $terima, ?string $catatan = null): Order
    {
        return DB::transaction(function () use ($order, $petugas, $terima, $catatan) {
            $order = $this->lock($order);

            $payment = $order->payments()
                ->where('status', PaymentStatus::MenungguVerifikasi)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($order->status !== OrderStatus::MenungguVerifikasi || ! $payment) {
                throw ValidationException::withMessages([
                    'status' => "Pesanan {$order->kode_pesanan} tidak sedang menunggu verifikasi (status: {$order->status->label()}).",
                ]);
            }

            $payment->update([
                'status' => $terima ? PaymentStatus::Diterima : PaymentStatus::Ditolak,
                'diverifikasi_oleh' => $petugas->id,
                'diverifikasi_pada' => now(),
                'catatan' => $catatan,
            ]);

            $this->transition(
                $order,
                $terima ? OrderStatus::Dikonfirmasi : OrderStatus::MenungguPembayaran,
                $petugas,
                $terima
                    ? "Pembayaran {$payment->metode->label()} diverifikasi dan diterima."
                    : 'Bukti pembayaran ditolak'.($catatan ? ": {$catatan}" : '.').' Menunggu pelanggan mengunggah ulang.',
            );

            return $order;
        });
    }

    /**
     * Majukan status produksi satu langkah: Dikonfirmasi -> Diproses -> Siap -> Selesai.
     *
     * @throws ValidationException jika target bukan langkah berikutnya (loncat/mundur)
     */
    public function advanceProduction(Order $order, OrderStatus $target, User $petugas): Order
    {
        return DB::transaction(function () use ($order, $target, $petugas) {
            $order = $this->lock($order);

            if ($order->status->nextProduction() !== $target) {
                throw ValidationException::withMessages([
                    'status' => "Status {$order->kode_pesanan} tidak bisa diubah dari \"{$order->status->label()}\" ke \"{$target->label()}\". "
                        .'Urutan produksi: Dikonfirmasi → Diproses → Siap → Selesai (tidak boleh loncat atau mundur).',
                ]);
            }

            $this->transition($order, $target, $petugas, "Status produksi diperbarui menjadi {$target->label()}.");

            return $order;
        });
    }

    /**
     * Tandai pembayaran tunai sudah diterima (saat pesanan Siap atau Selesai diserahkan).
     *
     * @throws ValidationException jika bukan pesanan tunai, belum siap, atau sudah lunas
     */
    public function markCashPaid(Order $order, User $petugas): Order
    {
        return DB::transaction(function () use ($order, $petugas) {
            $order = $this->lock($order);

            if ($order->metode_pembayaran !== PaymentMethod::Cash) {
                throw ValidationException::withMessages(['status' => "Pesanan {$order->kode_pesanan} bukan pesanan tunai."]);
            }

            if (! in_array($order->status, [OrderStatus::Siap, OrderStatus::Selesai], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Pembayaran tunai baru bisa ditandai lunas saat pesanan Siap Diambil/Dikirim atau Selesai.',
                ]);
            }

            $payment = $order->payments()
                ->where('metode', PaymentMethod::Cash)
                ->where('status', PaymentStatus::MenungguPembayaran)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw ValidationException::withMessages(['status' => "Pembayaran tunai {$order->kode_pesanan} sudah ditandai lunas."]);
            }

            $payment->update([
                'status' => PaymentStatus::Diterima,
                'diverifikasi_oleh' => $petugas->id,
                'diverifikasi_pada' => now(),
                'catatan' => 'Uang tunai '.Rupiah::format($payment->jumlah).' diterima.',
            ]);

            return $order;
        });
    }

    private function lock(Order $order): Order
    {
        return Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    private function transition(Order $order, OrderStatus $tujuan, User $petugas, string $keterangan): void
    {
        // Pengaman terakhir: semua perubahan tetap harus sesuai OrderStatus::nextAllowed().
        if (! $order->status->canTransitionTo($tujuan)) {
            throw ValidationException::withMessages([
                'status' => "Perubahan status dari \"{$order->status->label()}\" ke \"{$tujuan->label()}\" tidak diizinkan.",
            ]);
        }

        $statusLama = $order->status;
        $order->update(['status' => $tujuan]);

        $order->statusLogs()->create([
            'status_lama' => $statusLama,
            'status_baru' => $tujuan,
            'keterangan' => $keterangan,
            'created_by' => $petugas->id,
        ]);
    }
}
