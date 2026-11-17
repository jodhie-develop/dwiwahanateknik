<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Cabang;
use App\Models\JenisPerangkat;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data awal: 1 cabang, 1 owner, 1 admin cabang, dan jenis perangkat umum.
 * Aman dijalankan ulang (memakai firstOrCreate). Password WAJIB diisi lewat .env.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $passwordOwner = env('SEED_OWNER_PASSWORD');
        $passwordAdmin = env('SEED_ADMIN_PASSWORD');

        if (blank($passwordOwner) || blank($passwordAdmin)) {
            throw new RuntimeException('Isi SEED_OWNER_PASSWORD dan SEED_ADMIN_PASSWORD di .env sebelum menjalankan seeder.');
        }

        $cabang = Cabang::firstOrCreate(
            ['kode' => strtoupper(env('SEED_CABANG_KODE', 'SBY'))],
            ['nama' => env('SEED_CABANG_NAMA', 'Surabaya'), 'aktif' => true],
        );

        User::firstOrCreate(
            ['username' => strtolower(env('SEED_OWNER_USERNAME', 'owner'))],
            ['nama' => 'Owner', 'password' => $passwordOwner, 'role' => Role::Owner, 'cabang_id' => null, 'aktif' => true],
        );

        User::firstOrCreate(
            ['username' => strtolower(env('SEED_ADMIN_USERNAME', 'admin'))],
            ['nama' => 'Admin '.$cabang->nama, 'password' => $passwordAdmin, 'role' => Role::AdminCabang, 'cabang_id' => $cabang->id, 'aktif' => true],
        );

        foreach (['AC', 'TV', 'Kulkas', 'Mesin Cuci'] as $nama) {
            JenisPerangkat::firstOrCreate(['nama' => $nama], ['aktif' => true]);
        }
    }
}
