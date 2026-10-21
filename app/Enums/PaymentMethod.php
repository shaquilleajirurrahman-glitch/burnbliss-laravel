<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer Bank',
            self::Qris => 'QRIS',
            self::Cash => 'Tunai',
        };
    }

    /**
     * Keterangan singkat untuk pilihan di form checkout.
     */
    public function deskripsi(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer ke rekening toko, lalu unggah bukti',
            self::Qris => 'Scan QRIS dari aplikasi bank/e-wallet, lalu unggah bukti',
            self::Cash => 'Bayar saat ambil/diantar, tanpa unggah bukti',
        };
    }

    /**
     * Transfer & QRIS dibayar di muka dan butuh bukti; tunai dibayar saat serah terima.
     */
    public function butuhBukti(): bool
    {
        return $this !== self::Cash;
    }
}
