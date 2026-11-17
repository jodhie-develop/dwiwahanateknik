<?php

namespace App\Services;

use App\Models\Cabang;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Nomor dokumen otomatis per cabang per bulan, mis. KJG/SBY/2611/0001.
 *
 * Aman dari race condition:
 *  1. baris counter dibuat dengan INSERT ... ON CONFLICT DO NOTHING (tidak pernah gagal/abort transaksi),
 *  2. lalu dikunci dengan SELECT ... FOR UPDATE sebelum dinaikkan.
 * Panggil di DALAM transaksi yang sama dengan penyimpanan dokumen agar nomor tidak loncat bila gagal.
 */
class PenomoranDokumen
{
    public function berikutnya(Cabang $cabang, string $prefix, ?CarbonInterface $tanggal = null): string
    {
        $periode = ($tanggal ?? now())->format('ym');
        $kunci = ['cabang_id' => $cabang->id, 'prefix' => $prefix, 'periode' => $periode];

        $nomor = DB::transaction(function () use ($kunci) {
            DB::table('penomoran_dokumen')->insertOrIgnore($kunci + [
                'nomor_terakhir' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $baris = DB::table('penomoran_dokumen')->where($kunci)->lockForUpdate()->first();
            $nomor = $baris->nomor_terakhir + 1;

            DB::table('penomoran_dokumen')->where('id', $baris->id)->update([
                'nomor_terakhir' => $nomor,
                'updated_at' => now(),
            ]);

            return $nomor;
        });

        return sprintf('%s/%s/%s/%04d', $prefix, $cabang->kode, $periode, $nomor);
    }
}
