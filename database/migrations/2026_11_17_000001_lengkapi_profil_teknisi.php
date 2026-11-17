<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sejak menu Teknisi digabung ke Pengguna, setiap user ber-role teknisi wajib punya profil teknisi.
 * Migration ini membuatkan profil (bagian default: lapangan) untuk user teknisi yang belum punya.
 * Tidak menghapus/mengubah data yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tanpaProfil = DB::table('users')
            ->where('role', 'teknisi')
            ->whereNull('deleted_at')
            ->whereNotExists(fn ($q) => $q->from('teknisi')->whereColumn('teknisi.user_id', 'users.id'))
            ->get(['id', 'nama', 'cabang_id', 'aktif']);

        foreach ($tanpaProfil as $user) {
            if (! $user->cabang_id) {
                continue; // data tidak valid (teknisi tanpa cabang); perbaiki manual lewat menu Pengguna
            }

            DB::table('teknisi')->insert([
                'cabang_id' => $user->cabang_id,
                'user_id' => $user->id,
                'nama' => $user->nama,
                'bagian' => 'lapangan',
                'aktif' => $user->aktif,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Tidak dibalik: profil yang dibuat bisa saja sudah dipakai di tim.
    }
};
