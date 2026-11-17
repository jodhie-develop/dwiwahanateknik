<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum Role: string
{
    use EnumHelpers;

    case Owner = 'owner';
    case AdminCabang = 'admin_cabang';
    case Teknisi = 'teknisi';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::AdminCabang => 'Admin Cabang',
            self::Teknisi => 'Teknisi',
        };
    }

    /** Role selain owner wajib terikat ke satu cabang. */
    public function butuhCabang(): bool
    {
        return $this !== self::Owner;
    }
}
