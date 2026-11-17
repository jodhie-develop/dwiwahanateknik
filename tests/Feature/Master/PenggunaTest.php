<?php

use App\Enums\Role;
use App\Livewire\Master\PenggunaIndex;
use App\Models\Cabang;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('owner bisa membuat admin cabang', function () {
    $owner = User::factory()->owner()->create();
    $cabang = Cabang::factory()->create();

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('tambah')
        ->set('nama', 'Budi')
        ->set('username', 'Budi.Admin')
        ->set('role', Role::AdminCabang->value)
        ->set('cabang_id', $cabang->id)
        ->set('password', 'rahasia123')
        ->call('simpan')
        ->assertHasNoErrors();

    $user = User::where('username', 'budi.admin')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(Role::AdminCabang)
        ->and($user->cabang_id)->toBe($cabang->id)
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

test('role selain owner wajib punya cabang', function () {
    $owner = User::factory()->owner()->create();

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('tambah')
        ->set('nama', 'Tanpa Cabang')
        ->set('username', 'tanpacabang')
        ->set('role', Role::AdminCabang->value)
        ->set('cabang_id', null)
        ->set('password', 'rahasia123')
        ->call('simpan')
        ->assertHasErrors('cabang_id');
});

test('owner yang dibuat tidak terikat cabang', function () {
    $owner = User::factory()->owner()->create();
    $cabang = Cabang::factory()->create();

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('tambah')
        ->set('nama', 'Owner Dua')
        ->set('username', 'owner2')
        ->set('role', Role::Owner->value)
        ->set('cabang_id', $cabang->id)
        ->set('password', 'rahasia123')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(User::where('username', 'owner2')->value('cabang_id'))->toBeNull();
});

test('admin cabang hanya bisa membuat akun teknisi di cabangnya', function () {
    $cabang = Cabang::factory()->create();
    $lain = Cabang::factory()->create();
    $admin = User::factory()->adminCabang($cabang)->create();

    Livewire::actingAs($admin)->test(PenggunaIndex::class)
        ->call('tambah')
        ->set('nama', 'Calon Owner')
        ->set('username', 'calonowner')
        ->set('role', Role::Owner->value)
        ->set('password', 'rahasia123')
        ->call('simpan')
        ->assertHasErrors('role');

    Livewire::actingAs($admin)->test(PenggunaIndex::class)
        ->call('tambah')
        ->set('nama', 'Teknisi Baru')
        ->set('username', 'teknisi.baru')
        ->set('role', Role::Teknisi->value)
        ->set('cabang_id', $lain->id)
        ->set('password', 'rahasia123')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(User::where('username', 'teknisi.baru')->value('cabang_id'))->toBe($cabang->id);
});

test('admin cabang tidak bisa mengubah admin lain atau owner', function () {
    $cabang = Cabang::factory()->create();
    $admin = User::factory()->adminCabang($cabang)->create();
    $adminLain = User::factory()->adminCabang($cabang)->create();

    Livewire::actingAs($admin)->test(PenggunaIndex::class)
        ->call('ubah', $adminLain->id)
        ->assertForbidden();
});

test('username harus unik dan berformat benar', function () {
    $owner = User::factory()->owner()->create(['username' => 'dipakai']);

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('tambah')
        ->set('nama', 'X')
        ->set('username', 'dipakai')
        ->set('role', Role::Owner->value)
        ->set('password', 'rahasia123')
        ->call('simpan')
        ->assertHasErrors(['username' => 'unique']);

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('tambah')
        ->set('nama', 'X')
        ->set('username', 'ada spasi')
        ->set('role', Role::Owner->value)
        ->set('password', 'rahasia123')
        ->call('simpan')
        ->assertHasErrors(['username' => 'regex']);
});

test('password kosong saat ubah tidak mengganti password', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->teknisi()->create();
    $hashLama = $user->password;

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('ubah', $user->id)
        ->set('nama', 'Nama Baru')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($user->fresh())->nama->toBe('Nama Baru')->password->toBe($hashLama);
});

test('tidak bisa menonaktifkan atau menghapus akun sendiri', function () {
    $owner = User::factory()->owner()->create();

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('toggleAktif', $owner->id)
        ->assertForbidden();

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('hapus', $owner->id)
        ->assertForbidden();

    expect($owner->fresh())->aktif->toBeTrue();
});
