<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum BagianTeknisi: string
{
    use EnumHelpers;

    case Lapangan = 'lapangan';
    case Bengkel = 'bengkel';
    case Keduanya = 'keduanya';

    public function label(): string
    {
        return match ($this) {
            self::Lapangan => 'Lapangan',
            self::Bengkel => 'Bengkel',
            self::Keduanya => 'Lapangan & Bengkel',
        };
    }
}
