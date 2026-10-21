<?php

namespace App\Services;

use App\Models\Order;
use App\Support\NomorHp;
use Illuminate\Contracts\Session\Session;

/**
 * Hak akses tamu ke halaman pesanannya (pembayaran, upload bukti, selesai).
 *
 * Tamu tidak punya akun, jadi akses diberikan lewat session: otomatis setelah checkout,
 * atau setelah membuktikan nomor HP pemesan (mis. membuka link dari perangkat lain).
 * Dengan begitu kode pesanan saja tidak cukup untuk melihat data pelanggan.
 */
class OrderAccess
{
    public const SESSION_KEY = 'pesanan_saya';

    private const MAKS_DISIMPAN = 10;

    public function __construct(private Session $session) {}

    public function grant(Order $order): void
    {
        $kode = collect($this->session->get(self::SESSION_KEY, []))
            ->reject(fn (string $k) => $k === $order->kode_pesanan)
            ->push($order->kode_pesanan)
            ->take(-self::MAKS_DISIMPAN)
            ->values()
            ->all();

        $this->session->put(self::SESSION_KEY, $kode);
    }

    public function allows(Order $order): bool
    {
        return in_array($order->kode_pesanan, $this->session->get(self::SESSION_KEY, []), true);
    }

    /**
     * Cocokkan nomor HP yang dimasukkan dengan nomor HP pemesan.
     */
    public function verify(Order $order, string $nomorHp): bool
    {
        return hash_equals($order->nomor_hp, NomorHp::normalisasi($nomorHp));
    }
}
