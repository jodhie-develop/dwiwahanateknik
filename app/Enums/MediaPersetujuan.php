<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MediaPersetujuan: string
{
    use EnumHelpers;

    case WhatsApp = 'wa';
    case Telepon = 'telepon';
    case Langsung = 'langsung';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Telepon => 'Telepon',
            self::Langsung => 'Langsung di toko',
        };
    }
}
