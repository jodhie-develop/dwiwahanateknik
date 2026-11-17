<?php

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

if (! function_exists('rupiah')) {
    /** 1250000 → "Rp 1.250.000" */
    function rupiah(int|string|null $nominal, bool $denganPrefix = true): string
    {
        $angka = number_format((int) $nominal, 0, ',', '.');

        return $denganPrefix ? 'Rp '.$angka : $angka;
    }
}

if (! function_exists('tgl')) {
    /** Format tanggal tampilan d/m/Y (atau d/m/Y H:i jika $denganJam). */
    function tgl(CarbonInterface|string|null $tanggal, bool $denganJam = false): string
    {
        if (blank($tanggal)) {
            return '-';
        }

        $tanggal = $tanggal instanceof CarbonInterface ? $tanggal : Carbon::parse($tanggal);

        return $tanggal->timezone(config('app.timezone'))->format($denganJam ? 'd/m/Y H:i' : 'd/m/Y');
    }
}

if (! function_exists('berkas_url')) {
    /** URL sementara untuk file di disk default (bucket Supabase privat). */
    function berkas_url(?string $path, int $menit = 30): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::temporaryUrl($path, now()->addMinutes($menit));
    }
}
