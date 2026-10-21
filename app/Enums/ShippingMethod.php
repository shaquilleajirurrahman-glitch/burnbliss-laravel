<?php

namespace App\Enums;

enum ShippingMethod: string
{
    case AmbilSendiri = 'ambil_sendiri';
    case Diantar = 'diantar';

    public function label(): string
    {
        return match ($this) {
            self::AmbilSendiri => 'Ambil sendiri di toko',
            self::Diantar => 'Diantar (ojek online)',
        };
    }
}
