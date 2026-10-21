<?php

namespace App\Enums;

enum PaymentStatus: string
{
    /** Tunai: uang belum diterima (dibayar saat pengambilan/pengiriman). */
    case MenungguPembayaran = 'menunggu_pembayaran';
    /** Transfer/QRIS: bukti sudah diunggah, menunggu dicek admin. */
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::MenungguPembayaran => 'Menunggu Pembayaran',
            self::MenungguVerifikasi => 'Menunggu Verifikasi',
            self::Diterima => 'Diterima',
            self::Ditolak => 'Ditolak',
        };
    }
}
