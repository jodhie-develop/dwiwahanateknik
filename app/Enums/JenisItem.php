<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum JenisItem: string
{
    use EnumHelpers;

    case Layanan = 'layanan';
    case Barang = 'barang';
    case Biaya = 'biaya'; // biaya lain tanpa master, mis. biaya cek bengkel

    public function label(): string
    {
        return match ($this) {
            self::Layanan => 'Layanan',
            self::Barang => 'Barang',
            self::Biaya => 'Biaya',
        };
    }
}
