<?php

use App\Livewire\Master\CabangIndex;
use App\Models\Cabang;
use App\Models\User;
use Livewire\Livewire;

test('owner bisa melihat dan menambah cabang', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get(route('master.cabang'))->assertOk();

    Livewire::actingAs($owner)->test(CabangIndex::class)
        ->call('tambah')
        ->set('kode', 'mlg')
        ->set('nama', 'Malang')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Cabang::where('kode', 'MLG')->exists())->toBeTrue();
});

test('kode cabang harus unik', function () {
    $owner = User::factory()->owner()->create();
    Cabang::factory()->create(['kode' => 'SBY']);

    Livewire::actingAs($owner)->test(CabangIndex::class)
        ->call('tambah')
        ->set('kode', 'SBY')
        ->set('nama', 'Surabaya 2')
        ->call('simpan')
        ->assertHasErrors(['kode' => 'unique']);
});

test('hapus cabang memakai soft delete', function () {
    $owner = User::factory()->owner()->create();
    $cabang = Cabang::factory()->create();

    Livewire::actingAs($owner)->test(CabangIndex::class)->call('hapus', $cabang->id);

    expect($cabang->fresh()->trashed())->toBeTrue();
});

test('admin cabang tidak bisa mengelola cabang', function () {
    $admin = User::factory()->adminCabang()->create();

    $this->actingAs($admin)->get(route('master.cabang'))->assertForbidden();
});
