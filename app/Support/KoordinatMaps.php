<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Membaca latitude/longitude dari link Google Maps atau teks koordinat.
 *
 * Didukung:
 *  - "-7.2575, 112.7521"
 *  - https://maps.google.com/?q=-7.2575,112.7521  (juga ll=, query=, destination=, center=)
 *  - https://www.google.com/maps/place/Nama/@-7.2575,112.7521,17z/data=...!3d-7.2575!4d112.7521
 *  - https://www.google.com/maps/search/-7.2575,+112.7521
 *  - link pendek https://maps.app.goo.gl/xxxx atau https://goo.gl/maps/xxxx (diikuti redirect-nya)
 */
class KoordinatMaps
{
    /** Host yang boleh dibuka untuk mengikuti link pendek (mencegah server diarahkan ke alamat lain). */
    private const HOST_LINK_PENDEK = ['maps.app.goo.gl', 'goo.gl'];

    private const ANGKA = '(-?\d{1,3}(?:\.\d+)?)';

    /** @return array{lat: float, lng: float}|null */
    public static function baca(string $teks, bool $ikutiLinkPendek = true): ?array
    {
        $teks = trim(urldecode($teks));

        if ($teks === '') {
            return null;
        }

        if ($ikutiLinkPendek && static::linkPendek($teks)) {
            $tujuan = static::ikutiRedirect($teks);

            return $tujuan ? static::baca($tujuan, false) : null;
        }

        $a = self::ANGKA;
        $pola = [
            // Titik tempat yang dipilih (paling akurat) pada link /maps/place/
            "/!3d{$a}!4d{$a}/",
            // Parameter query
            "/[?&](?:q|ll|query|destination|center|daddr)=(?:loc:)?{$a}\s*,\s*{$a}/",
            // /maps/search/-7.25,+112.75 atau /maps/dir//-7.25,112.75
            "#/maps/(?:search|dir|place)/(?:[^/]*/)?{$a}\s*,\s*\+?{$a}#",
            // Posisi kamera peta @lat,lng,zoom
            "/@{$a},{$a}/",
            // Koordinat polos
            "/^{$a}\s*,\s*{$a}$/",
        ];

        foreach ($pola as $regex) {
            if (preg_match($regex, $teks, $m)) {
                return static::valid((float) $m[1], (float) $m[2]);
            }
        }

        return null;
    }

    public static function linkPendek(string $teks): bool
    {
        $host = parse_url($teks, PHP_URL_HOST);

        return is_string($host) && in_array(Str::lower($host), self::HOST_LINK_PENDEK, true);
    }

    /** URL Google Maps untuk membuka titik koordinat. */
    public static function urlMaps(float|string $lat, float|string $lng): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.$lat.','.$lng;
    }

    /** @return array{lat: float, lng: float}|null */
    private static function valid(float $lat, float $lng): ?array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat == 0.0 && $lng == 0.0)) {
            return null;
        }

        return ['lat' => round($lat, 7), 'lng' => round($lng, 7)];
    }

    private static function ikutiRedirect(string $url): ?string
    {
        try {
            $sekarang = $url;

            // Ikuti redirect manual (maks 5 langkah) dan pastikan tetap di domain Google.
            for ($i = 0; $i < 5; $i++) {
                $respons = Http::timeout(8)->withoutRedirecting()->get($sekarang);
                $lokasi = $respons->header('Location');

                if (! $respons->redirect() || $lokasi === '') {
                    return $sekarang;
                }

                $sekarang = $lokasi;
                $host = Str::lower((string) parse_url($sekarang, PHP_URL_HOST));

                if (! static::hostGoogle($host)) {
                    return null;
                }

                if (static::baca($sekarang, false)) {
                    return $sekarang;
                }
            }

            return $sekarang;
        } catch (Throwable) {
            return null;
        }
    }

    private static function hostGoogle(string $host): bool
    {
        return in_array($host, self::HOST_LINK_PENDEK, true)
            || preg_match('/(^|\.)google\.(com|co\.id)$/', $host) === 1;
    }
}
