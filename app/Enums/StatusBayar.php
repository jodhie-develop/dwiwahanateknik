<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum StatusBayar: string
{
    use EnumHelpers;

    case Belum = 'belum';
    case Sebagian = 'sebagian';
    case Lunas = 'lunas';

    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum bayar',
            self::Sebagian => 'Bayar sebagian',
            self::Lunas => 'Lunas',
        };
    }
}
