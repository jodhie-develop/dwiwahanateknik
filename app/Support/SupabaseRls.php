<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Mengaktifkan Row Level Security (tanpa policy) di semua tabel schema public,
 * supaya tabel tidak bisa diakses lewat Supabase REST API (anon/authenticated key).
 * Laravel tetap bisa mengakses karena terhubung sebagai pemilik tabel.
 *
 * Dipanggil otomatis setiap selesai `php artisan migrate`, dan bisa dijalankan manual
 * lewat `php artisan supabase:rls`.
 */
class SupabaseRls
{
    /** @return list<string> tabel yang baru diaktifkan RLS-nya */
    public static function aktifkanSemua(?string $koneksi = null): array
    {
        $db = DB::connection($koneksi);

        if ($db->getDriverName() !== 'pgsql') {
            return [];
        }

        $tabel = $db->table('pg_tables')
            ->where('schemaname', 'public')
            ->where('rowsecurity', false)
            ->pluck('tablename')
            ->all();

        foreach ($tabel as $nama) {
            $db->statement('ALTER TABLE public.'.$db->getQueryGrammar()->wrap($nama).' ENABLE ROW LEVEL SECURITY');
        }

        return $tabel;
    }
}
