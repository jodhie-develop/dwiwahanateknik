<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum StatusEstimasi: string
{
    use EnumHelpers;

    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu jawaban',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Menunggu => 'amber',
            self::Disetujui => 'green',
            self::Ditolak => 'red',
        };
    }
}
