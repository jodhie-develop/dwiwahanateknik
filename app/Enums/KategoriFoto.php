<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum KategoriFoto: string
{
    use EnumHelpers;

    case Sebelum = 'sebelum';
    case Sesudah = 'sesudah';
    case Penerimaan = 'penerimaan';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Sebelum => 'Sebelum',
            self::Sesudah => 'Sesudah',
            self::Penerimaan => 'Penerimaan',
            self::Lainnya => 'Lainnya',
        };
    }
}
