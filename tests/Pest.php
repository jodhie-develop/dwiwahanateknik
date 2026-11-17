<?php

use App\Enums\PeranTim;
use App\Models\Cabang;
use App\Models\OrderServis;
use App\Models\Tim;
use App\Models\UnitPelanggan;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| Helper bersama untuk test.
*/

/** Tim aktif dengan $jumlah anggota (anggota pertama = ketua), masing-masing punya akun teknisi. */
function timDenganAnggota(Cabang $cabang, string $nama = 'Tim A', int $jumlah = 2): Tim
{
    $tim = Tim::factory()->create(['cabang_id' => $cabang->id, 'nama_tim' => $nama]);

    foreach (range(1, $jumlah) as $i) {
        $user = User::factory()->teknisi($cabang)->create();
        $tim->anggota()->create([
            'teknisi_id' => $user->teknisi()->withoutGlobalScopes()->value('id'),
            'peran' => $i === 1 ? PeranTim::Ketua : PeranTim::Anggota,
            'tgl_mulai' => today()->subMonth(),
        ]);
    }

    return $tim;
}

/**
 * Jalankan $fn tanpa user login. Dipakai untuk menyiapkan data cabang lain:
 * saat admin cabang login, BerCabang memaksa data baru masuk ke cabang admin.
 */
function sebagaiSistem(callable $fn): mixed
{
    $guard = auth()->guard();
    $user = $guard->user();
    $guard->forgetUser();

    try {
        return $fn();
    } finally {
        if ($user) {
            $guard->setUser($user);
        }
    }
}

/** Terima barang servis bengkel (helper test). */
function terimaBarang(Cabang $cabang, User $oleh, array $teknisi = []): OrderServis
{
    $unit = UnitPelanggan::factory()->create();

    return app(OrderService::class)->terimaBengkel([
        'cabang_id' => $cabang->id,
        'pelanggan_id' => $unit->pelanggan_id,
        'unit_pelanggan_id' => $unit->id,
        'keluhan' => 'TV mati total',
    ], [
        'kondisi_fisik' => 'Layar gores',
        'kelengkapan' => ['Remote', 'Kabel power'],
        'lokasi_simpan' => 'Rak A-1',
    ], $oleh, $teknisi);
}
