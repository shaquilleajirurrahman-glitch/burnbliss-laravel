<?php

namespace App\Support;

final class Csv
{
    /**
     * Cegah CSV/formula injection: teks dari input pengguna (mis. nama pelanggan) yang diawali
     * = + - @ atau tab/CR bisa dieksekusi sebagai rumus oleh Excel/Sheets. Awali dengan tanda kutip.
     */
    public static function aman(string $teks): string
    {
        return preg_match('/^[=+\-@\t\r]/', $teks) ? "'".$teks : $teks;
    }
}
