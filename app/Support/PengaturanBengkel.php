<?php

namespace App\Support;

use App\Models\Pengaturan;

/**
 * Pengaturan servis bengkel per cabang (nilai cabang menimpa nilai global).
 * Keputusan pemilik: biaya cek (jika aktif) SELALU ditagih, baik lanjut diperbaiki maupun batal.
 */
class PengaturanBengkel
{
    public const BIAYA_CEK_AKTIF = 'bengkel.biaya_cek_aktif';

    public const BIAYA_CEK_NOMINAL = 'bengkel.biaya_cek_nominal';

    public const BATAS_HARI_AMBIL = 'bengkel.batas_hari_ambil';

    public const DEFAULT_BATAS_HARI_AMBIL = 14;

    public static function biayaCekAktif(int $cabangId): bool
    {
        return (bool) (int) Pengaturan::ambil(self::BIAYA_CEK_AKTIF, '0', $cabangId);
    }

    public static function biayaCekNominal(int $cabangId): int
    {
        return max(0, (int) Pengaturan::ambil(self::BIAYA_CEK_NOMINAL, '0', $cabangId));
    }

    /** Nominal biaya cek yang harus ditagih (0 jika tidak aktif). */
    public static function biayaCek(int $cabangId): int
    {
        return self::biayaCekAktif($cabangId) ? self::biayaCekNominal($cabangId) : 0;
    }

    public static function batasHariAmbil(int $cabangId): int
    {
        return max(1, (int) Pengaturan::ambil(self::BATAS_HARI_AMBIL, (string) self::DEFAULT_BATAS_HARI_AMBIL, $cabangId));
    }
}
