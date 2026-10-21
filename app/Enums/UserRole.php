<?php

namespace App\Enums;

/**
 * Level user internal yang bisa login, hierarkis: Admin > Koordinator > Staf.
 * Pelanggan bukan user: mereka memesan sebagai tamu (guest).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Koordinator = 'koordinator';
    case Staf = 'staf';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Koordinator => 'Koordinator Produksi',
            self::Staf => 'Staf/Kasir',
        };
    }

    /**
     * Ringkasan hak akses per level (ditampilkan di halaman kelola pengguna).
     */
    public function deskripsi(): string
    {
        return match ($this) {
            self::Admin => 'Akses penuh: produk, pengguna, verifikasi pembayaran, laporan, kuota.',
            self::Koordinator => 'Verifikasi pembayaran, kelola pesanan & kuota produksi, lihat laporan.',
            self::Staf => 'Antrean produksi: ubah status produksi & terima pembayaran tunai.',
        };
    }

    /**
     * Tingkat dalam hierarki; makin besar makin tinggi.
     */
    public function level(): int
    {
        return match ($this) {
            self::Admin => 3,
            self::Koordinator => 2,
            self::Staf => 1,
        };
    }

    /**
     * True jika role ini setara atau di atas $role (mis. Admin->isAtLeast(Staf) === true).
     */
    public function isAtLeast(self $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Nama route dashboard tujuan setelah login.
     */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Koordinator => 'koordinator.dashboard',
            self::Staf => 'staf.dashboard',
        };
    }
}
