<?php

use App\Livewire\Master\PelangganDetail;
use App\Livewire\Master\PelangganIndex;
use App\Models\JenisPerangkat;
use App\Models\Pelanggan;
use App\Models\UnitPelanggan;
use App\Models\User;
use Livewire\Livewire;

test('admin cabang bisa menambah pelanggan lalu diarahkan ke detail', function () {
    $admin = User::factory()->adminCabang()->create();

    Livewire::actingAs($admin)->test(PelangganIndex::class)
        ->call('tambah')
        ->set('nama', 'Bu Sari')
        ->set('hp', '0812-3456-7890')
        ->set('alamat', 'Jl. Mawar 1')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertRedirect(route('master.pelanggan.detail', Pelanggan::first()));

    expect(Pelanggan::first()->hp)->toBe('081234567890');
});

test('nomor HP pelanggan divalidasi', function () {
    $admin = User::factory()->adminCabang()->create();

    Livewire::actingAs($admin)->test(PelangganIndex::class)
        ->call('tambah')->set('nama', 'X')->set('hp', '12345')->call('simpan')
        ->assertHasErrors(['hp' => 'regex']);
});

test('pelanggan lintas cabang: admin cabang mana pun bisa melihat', function () {
    $pelanggan = Pelanggan::factory()->create(['nama' => 'Pak Lintas']);

    $this->actingAs(User::factory()->adminCabang()->create())
        ->get(route('master.pelanggan'))->assertSee('Pak Lintas');

    $this->actingAs(User::factory()->adminCabang()->create())
        ->get(route('master.pelanggan.detail', $pelanggan))->assertOk();
});

test('kelola unit pelanggan', function () {
    $admin = User::factory()->adminCabang()->create();
    $pelanggan = Pelanggan::factory()->create();
    $jenis = JenisPerangkat::factory()->create(['nama' => 'AC']);

    $komponen = Livewire::actingAs($admin)->test(PelangganDetail::class, ['pelanggan' => $pelanggan]);

    $komponen->call('tambahUnit')
        ->set('jenis_perangkat_id', $jenis->id)
        ->set('merk', 'Daikin')
        ->set('ukuran', '1 PK')
        ->set('lokasi', 'Kamar depan')
        ->call('simpanUnit')
        ->assertHasNoErrors()
        ->assertSee('Daikin');

    $unit = $pelanggan->unit()->first();
    expect($unit->lokasi)->toBe('Kamar depan')->and($unit->alamat)->toBeNull()
        ->and($unit->alamatEfektif())->toBe($pelanggan->alamat);

    $komponen->call('hapusUnit', $unit->id);
    expect($unit->fresh()->trashed())->toBeTrue();
});

test('unit pelanggan lain tidak bisa diubah dari halaman pelanggan ini', function () {
    $admin = User::factory()->adminCabang()->create();
    $pelanggan = Pelanggan::factory()->create();
    $unitLain = UnitPelanggan::factory()->create();

    Livewire::actingAs($admin)->test(PelangganDetail::class, ['pelanggan' => $pelanggan])
        ->call('hapusUnit', $unitLain->id)
        ->assertNotFound();

    expect($unitLain->fresh()->trashed())->toBeFalse();
});
