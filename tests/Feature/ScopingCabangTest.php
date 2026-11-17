<?php

use App\Livewire\Master\PenggunaIndex;
use App\Livewire\Master\TimIndex;
use App\Models\Cabang;
use App\Models\Layanan;
use App\Models\LayananHarga;
use App\Models\Teknisi;
use App\Models\Tim;
use App\Models\User;
use App\Support\KonteksCabang;
use Livewire\Livewire;

beforeEach(function () {
    $this->cabangA = Cabang::factory()->create(['kode' => 'AAA']);
    $this->cabangB = Cabang::factory()->create(['kode' => 'BBB']);
    $this->adminA = User::factory()->adminCabang($this->cabangA)->create();
    $this->owner = User::factory()->owner()->create();

    $this->userTeknisiA = User::factory()->teknisi($this->cabangA)->create(['nama' => 'Teknisi Alpha']);
    $this->userTeknisiB = User::factory()->teknisi($this->cabangB)->create(['nama' => 'Teknisi Beta']);
    $this->teknisiA = $this->userTeknisiA->teknisi()->withoutGlobalScopes()->first();
    $this->teknisiB = $this->userTeknisiB->teknisi()->withoutGlobalScopes()->first();
});

test('admin cabang hanya melihat teknisi di cabangnya', function () {
    $this->actingAs($this->adminA);

    expect(Teknisi::pluck('id')->all())->toBe([$this->teknisiA->id]);

    $this->get(route('master.pengguna'))->assertSee('Teknisi Alpha')->assertDontSee('Teknisi Beta');
});

test('owner melihat teknisi semua cabang', function () {
    $this->actingAs($this->owner);

    expect(Teknisi::count())->toBe(2);
});

test('filter cabang owner membatasi data', function () {
    $this->actingAs($this->owner)
        ->post(route('filter-cabang'), ['cabang_id' => $this->cabangB->id])
        ->assertRedirect();

    expect(session(KonteksCabang::SESSION_KEY))->toBe($this->cabangB->id);
    $this->get(route('master.pengguna'))->assertSee('Teknisi Beta')->assertDontSee('Teknisi Alpha');
    expect(Teknisi::pluck('id')->all())->toBe([$this->teknisiB->id]);

    // kosongkan filter = semua cabang
    $this->post(route('filter-cabang'), ['cabang_id' => null]);
    $this->get(route('master.pengguna'))->assertSee('Teknisi Beta')->assertSee('Teknisi Alpha');
});

test('admin cabang tidak bisa memakai filter cabang', function () {
    $this->actingAs($this->adminA)
        ->post(route('filter-cabang'), ['cabang_id' => $this->cabangB->id])
        ->assertForbidden();
});

test('admin cabang tidak bisa mengubah teknisi cabang lain', function () {
    Livewire::actingAs($this->adminA)
        ->test(PenggunaIndex::class)
        ->call('ubah', $this->userTeknisiB->id)
        ->assertNotFound();
});

test('admin cabang tidak bisa menghapus teknisi cabang lain', function () {
    Livewire::actingAs($this->adminA)
        ->test(PenggunaIndex::class)
        ->call('hapus', $this->userTeknisiB->id)
        ->assertNotFound();

    expect($this->userTeknisiB->fresh()->trashed())->toBeFalse()
        ->and(Teknisi::withoutGlobalScopes()->find($this->teknisiB->id))->not->toBeNull();
});

test('data yang dibuat admin cabang selalu masuk ke cabangnya walau cabang_id dimanipulasi', function () {
    Livewire::actingAs($this->adminA)
        ->test(TimIndex::class)
        ->call('tambah')
        ->set('cabang_id', $this->cabangB->id)
        ->set('nama_tim', 'Tim Selundupan')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Tim::withoutGlobalScopes()->where('nama_tim', 'Tim Selundupan')->value('cabang_id'))->toBe($this->cabangA->id);
});

test('admin cabang tidak bisa melihat atau mengubah user cabang lain', function () {
    $userB = User::factory()->teknisi($this->cabangB)->create(['nama' => 'Pengguna Beta']);

    $this->actingAs($this->adminA)->get(route('master.pengguna'))->assertDontSee('Pengguna Beta');

    Livewire::actingAs($this->adminA)
        ->test(PenggunaIndex::class)
        ->call('ubah', $userB->id)
        ->assertNotFound();
});

test('harga layanan dibatasi per cabang untuk admin', function () {
    $layanan = Layanan::factory()->create();
    LayananHarga::withoutGlobalScopes()->insert([
        ['layanan_id' => $layanan->id, 'cabang_id' => $this->cabangA->id, 'harga' => 100000],
        ['layanan_id' => $layanan->id, 'cabang_id' => $this->cabangB->id, 'harga' => 150000],
    ]);

    $this->actingAs($this->adminA);
    expect(LayananHarga::pluck('harga')->all())->toBe([100000]);

    $this->actingAs($this->owner);
    expect(LayananHarga::count())->toBe(2);
});

test('user non-owner tanpa cabang tidak melihat data apa pun', function () {
    $rusak = User::factory()->adminCabang()->create();
    $rusak->forceFill(['cabang_id' => null])->saveQuietly();

    $this->actingAs($rusak->fresh());

    expect(Teknisi::count())->toBe(0);
});
