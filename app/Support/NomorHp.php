<?php

namespace App\Support;

final class NomorHp
{
    /**
     * Seragamkan nomor HP Indonesia ke format 08xxxxxxxxxx.
     * "+62 812-3456-7890", "62812...", "0812 3456 7890" -> "081234567890".
     */
    public static function normalisasi(string $nomor): string
    {
        $digit = preg_replace('/\D+/', '', $nomor) ?? '';

        if (str_starts_with($digit, '62')) {
            return '0'.substr($digit, 2);
        }

        if (str_starts_with($digit, '8')) {
            return '0'.$digit;
        }

        return $digit;
    }

    /**
     * "081234567890" -> "0812****7890" (untuk halaman publik).
     */
    public static function samarkan(string $nomor): string
    {
        if (strlen($nomor) <= 8) {
            return $nomor;
        }

        return substr($nomor, 0, 4).str_repeat('*', strlen($nomor) - 8).substr($nomor, -4);
    }
}
