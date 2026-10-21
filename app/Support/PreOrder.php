<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Aturan tanggal ambil/kirim pre-order (lihat config/burnbliss.php).
 */
final class PreOrder
{
    public static function tanggalMin(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays(config('burnbliss.preorder.min_hari'));
    }

    public static function tanggalMaks(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays(config('burnbliss.preorder.maks_hari'));
    }

    public static function hariLibur(CarbonImmutable|string $tanggal): bool
    {
        $tanggal = is_string($tanggal) ? CarbonImmutable::parse($tanggal) : $tanggal;

        return in_array($tanggal->dayOfWeek, config('burnbliss.preorder.hari_libur'), true);
    }

    /**
     * "2026-10-21" -> "Rabu, 21 Oktober 2026".
     */
    public static function format(CarbonImmutable|string $tanggal): string
    {
        $tanggal = is_string($tanggal) ? CarbonImmutable::parse($tanggal) : $tanggal;

        return $tanggal->locale('id')->translatedFormat('l, j F Y');
    }
}
