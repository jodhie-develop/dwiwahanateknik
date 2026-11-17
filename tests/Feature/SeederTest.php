<?php

use App\Enums\Role;
use App\Models\Cabang;
use App\Models\JenisPerangkat;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

function setEnvSeeder(?string $owner, ?string $admin): void
{
    foreach (['SEED_OWNER_PASSWORD' => $owner, 'SEED_ADMIN_PASSWORD' => $admin] as $kunci => $nilai) {
        $_ENV[$kunci] = $_SERVER[$kunci] = $nilai ?? '';
    }
}

afterEach(fn () => setEnvSeeder(null, null));

test('seeder menolak jalan tanpa password di env', function () {
    setEnvSeeder(null, null);

    $this->seed(DatabaseSeeder::class);
})->throws(RuntimeException::class);

test('seeder membuat data awal dan aman dijalankan ulang', function () {
    setEnvSeeder('owner-rahasia', 'admin-rahasia');

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Cabang::count())->toBe(1)
        ->and(User::where('role', Role::Owner)->count())->toBe(1)
        ->and(User::where('role', Role::AdminCabang)->first()->cabang_id)->toBe(Cabang::first()->id)
        ->and(JenisPerangkat::count())->toBe(4);
});
