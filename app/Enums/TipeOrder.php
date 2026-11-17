<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TipeOrder: string
{
    use EnumHelpers;

    case Kunjungan = 'kunjungan';
    case Bengkel = 'bengkel';

    public function label(): string
    {
        return match ($this) {
            self::Kunjungan => 'Kunjungan',
            self::Bengkel => 'Bengkel',
        };
    }

    /** Awalan nomor order: KJG/SBY/2611/0001 atau SRV/SBY/2611/0001. */
    public function prefixNomor(): string
    {
        return match ($this) {
            self::Kunjungan => 'KJG',
            self::Bengkel => 'SRV',
        };
    }

    public function statusAwal(): StatusOrder
    {
        return match ($this) {
            self::Kunjungan => StatusOrder::Dijadwalkan,
            self::Bengkel => StatusOrder::Diterima,
        };
    }
}
