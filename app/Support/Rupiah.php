<?php

namespace App\Support;

final class Rupiah
{
    /**
     * 25000 -> "Rp 25.000"
     */
    public static function format(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
