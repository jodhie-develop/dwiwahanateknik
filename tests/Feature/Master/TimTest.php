<?php

use App\Enums\PeranTim;
use App\Livewire\Master\TimIndex;
use App\Models\Cabang;
use App\Models\Teknisi;
use App\Models\Tim;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->cabang = Cabang::factory()->create();
    $this->admin = User::factory()->adminCabang($this->cabang)->create();
});

test('kelola anggota tim: tambah, satu ketua, keluarkan', function () {
    $tim = Tim::factory()->create(['cabang_id' => $this->cabang->id]);
    [$a, $b, $c] = Teknisi::factory()->count(3)->create(['cabang_id' => $this->cabang->id]);

    $komponen = Livewire::actingAs($this->admin)->test(TimIndex::class)->call('kelolaAnggota', $tim->id);

    $komponen->set('teknisi_id', $a->id)->set('peran', PeranTim::Ketua->value)->call('tambahAnggota')->assertHasNoErrors();
    $komponen->set('teknisi_id', $b->id)->set('peran', PeranTim::Anggota->value)->call('tambahAnggota')->assertHasNoErrors();

    // ketua kedua ditolak
    $komponen->set('teknisi_id', $c->id)->set('peran', PeranTim::Ketua->value)->call('tambahAnggota')->assertHasErrors('peran');

    // teknisi yang sama tidak boleh dobel
    $komponen->set('teknisi_id', $b->id)->set('peran', PeranTim::Anggota->value)->call('tambahAnggota')->assertHasErrors('teknisi_id');

    expect($tim->anggotaAktif()->count())->toBe(2);

    $anggotaB = $tim->anggota()->where('teknisi_id', $b->id)->first();
    $komponen->call('keluarkanAnggota', $anggotaB->id);

    expect($tim->anggotaAktif()->pluck('teknisi_id')->all())->toBe([$a->id])
        ->and($tim->anggota()->count())->toBe(2); // riwayat tetap tersimpan
});

test('teknisi dari cabang lain tidak bisa dimasukkan ke tim', function () {
    $tim = Tim::factory()->create(['cabang_id' => $this->cabang->id]);
    $owner = User::factory()->owner()->create();
    $teknisiLain = Teknisi::factory()->create();

    Livewire::actingAs($owner)->test(TimIndex::class)
        ->call('kelolaAnggota', $tim->id)
        ->set('teknisi_id', $teknisiLain->id)
        ->call('tambahAnggota')
        ->assertHasErrors('teknisi_id');
});
