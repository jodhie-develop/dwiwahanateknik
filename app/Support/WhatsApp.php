<?php

namespace App\Support;

/** Link wa.me (tanpa WhatsApp API berbayar). */
class WhatsApp
{
    /** 0812xxx / +62812xxx / 62812xxx → 62812xxx; null jika tidak valid. */
    public static function nomor(?string $hp): ?string
    {
        $angka = preg_replace('/\D/', '', (string) $hp);

        if ($angka === '') {
            return null;
        }

        if (str_starts_with($angka, '0')) {
            $angka = '62'.substr($angka, 1);
        } elseif (str_starts_with($angka, '8')) {
            $angka = '62'.$angka;
        }

        return strlen($angka) >= 10 ? $angka : null;
    }

    public static function link(?string $hp, string $pesan): ?string
    {
        $nomor = static::nomor($hp);

        return $nomor ? 'https://wa.me/'.$nomor.'?text='.rawurlencode($pesan) : null;
    }
}
