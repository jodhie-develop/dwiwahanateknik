<?php

namespace App\Enums\Concerns;

trait EnumHelpers
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> value => label, untuk dropdown */
    public static function options(): array
    {
        $hasil = [];
        foreach (self::cases() as $case) {
            $hasil[$case->value] = $case->label();
        }

        return $hasil;
    }
}
