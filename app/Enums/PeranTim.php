<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PeranTim: string
{
    use EnumHelpers;

    case Ketua = 'ketua';
    case Anggota = 'anggota';

    public function label(): string
    {
        return match ($this) {
            self::Ketua => 'Ketua',
            self::Anggota => 'Anggota',
        };
    }
}
