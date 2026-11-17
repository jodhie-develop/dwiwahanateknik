<?php

namespace App\Support;

/** Jarak lurus antar dua titik koordinat (rumus haversine). Bukan jarak tempuh jalan. */
class Jarak
{
    private const RADIUS_BUMI_KM = 6371.0;

    /**
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    public static function km(array $a, array $b): float
    {
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLng = deg2rad($b['lng'] - $a['lng']);

        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;

        return 2 * self::RADIUS_BUMI_KM * asin(min(1.0, sqrt($h)));
    }
}
