<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Hak akses pesanan di panel internal (hierarki Admin > Koordinator > Staf).
 *
 *  - Admin & Koordinator : semua pesanan, data keuangan, verifikasi bayar, tandai lunas, ubah status.
 *  - Staf/Kasir          : hanya antrean produksi (Dikonfirmasi/Diproses/Siap), ubah status produksi,
 *                          dan tandai lunas tunai saat serah terima; tidak bisa verifikasi transfer/QRIS
 *                          atau melihat bukti & data pembayaran.
 */
class OrderPolicy
{
    /**
     * Semua akun internal boleh membuka halaman kelola pesanan (isinya dibatasi per role).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Total tagihan, metode & bukti pembayaran.
     */
    public function viewFinancials(User $user): Response
    {
        return $this->koordinatorKeAtas($user, 'Hanya Admin atau Koordinator yang dapat melihat data pembayaran.');
    }

    public function verifyPayment(User $user, Order $order): Response
    {
        return $this->koordinatorKeAtas($user, 'Hanya Admin atau Koordinator yang dapat memverifikasi pembayaran.');
    }

    /**
     * Tandai lunas tunai: Staf/Kasir yang menerima uang saat serah terima juga boleh,
     * selama pesanan sudah Siap atau Selesai (aturan tunai lainnya dicek OrderWorkflow).
     */
    public function markCashPaid(User $user, Order $order): Response
    {
        if ($user->role->isAtLeast(UserRole::Koordinator)
            || in_array($order->status, [OrderStatus::Siap, OrderStatus::Selesai], true)) {
            return Response::allow();
        }

        return Response::deny('Pembayaran tunai baru bisa ditandai lunas saat pesanan Siap Diambil/Dikirim atau Selesai.');
    }

    /**
     * Ubah status produksi. Staf hanya untuk pesanan yang ada di antrean produksi.
     */
    public function updateStatus(User $user, Order $order): Response
    {
        if ($user->role->isAtLeast(UserRole::Koordinator) || $order->status->isAntreanProduksi()) {
            return Response::allow();
        }

        return Response::deny('Pesanan ini belum masuk antrean produksi.');
    }

    private function koordinatorKeAtas(User $user, string $pesan): Response
    {
        return $user->role->isAtLeast(UserRole::Koordinator)
            ? Response::allow()
            : Response::deny($pesan);
    }
}
