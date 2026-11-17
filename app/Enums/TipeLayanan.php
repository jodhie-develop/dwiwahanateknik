<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TipeLayanan: string
{
    use EnumHelpers;

    case Kunjungan = 'kunjungan';
    case Bengkel = 'bengkel';
    case Keduanya = 'keduanya';

    public function label(): string
    {
        return match ($this) {
            self::Kunjungan => 'Kunjungan',
            self::Bengkel => 'Bengkel',
            self::Keduanya => 'Kunjungan & Bengkel',
        };
    }
}
