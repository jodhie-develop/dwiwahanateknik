<?php

use App\Livewire\Master\BarangIndex;
use App\Livewire\Master\JenisPerangkatIndex;
use App\Livewire\Master\LayananIndex;
use App\Models\Barang;
use App\Models\Cabang;
use App\Models\JenisPerangkat;
use App\Models\Layanan;
use App\Models\LayananHarga;
use App\Models\User;
use Livewire\Livewire;

test('owner bisa mengelola jenis perangkat, nama unik', function () {
    $owner = User::factory()->owner()->create();

    Livewire::actingAs($owner)->test(JenisPerangkatIndex::class)
        ->call('tambah')->set('nama', 'AC')->call('simpan')->assertHasNoErrors();

    Livewire::actingAs($owner)->test(JenisPerangkatIndex::class)
        ->call('tambah')->set('nama', 'AC')->call('simpan')->assertHasErrors(['nama' => 'unique']);
});

test('owner bisa menambah barang dengan harga rupiah bulat', function () {
    $owner = User::factory()->owner()->create();

    Livewire::actingAs($owner)->test(BarangIndex::class)
        ->call('tambah')
        ->set('kode', 'frn-r32')
        ->set('nama', 'Freon R32')
        ->set('satuan', 'kg')
        ->set('harga_jual', '250000')
        ->call('simpan')
        ->assertHasNoErrors();

    $barang = Barang::where('kode', 'FRN-R32')->first();
    expect($barang->harga_jual)->toBe(250000);

    Livewire::actingAs($owner)->test(BarangIndex::class)
        ->call('tambah')->set('kode', 'X')->set('nama', 'X')->set('satuan', 'pcs')->set('harga_jual', '1500.5')
        ->call('simpan')->assertHasErrors(['harga_jual' => 'integer']);
});

test('admin cabang bisa melihat tapi tidak bisa mengubah master global', function () {
    $admin = User::factory()->adminCabang()->create();
    $barang = Barang::factory()->create();
    $jenis = JenisPerangkat::factory()->create();

    Livewire::actingAs($admin)->test(BarangIndex::class)->assertOk()->call('tambah')->assertForbidden();
    Livewire::actingAs($admin)->test(BarangIndex::class)->call('ubah', $barang->id)->assertForbidden();
    Livewire::actingAs($admin)->test(BarangIndex::class)->call('hapus', $barang->id)->assertForbidden();
    Livewire::actingAs($admin)->test(JenisPerangkatIndex::class)->call('hapus', $jenis->id)->assertForbidden();
    Livewire::actingAs($admin)->test(LayananIndex::class)->call('tambah')->assertForbidden();

    expect($barang->fresh()->trashed())->toBeFalse();
});

test('owner bisa menambah layanan dan mengatur harga semua cabang', function () {
    $owner = User::factory()->owner()->create();
    $jenis = JenisPerangkat::factory()->create(['nama' => 'AC']);
    [$a, $b] = Cabang::factory()->count(2)->create();

    Livewire::actingAs($owner)->test(LayananIndex::class)
        ->call('tambah')
        ->set('jenis_perangkat_id', $jenis->id)
        ->set('nama', 'Cuci AC')
        ->set('tipe', 'kunjungan')
        ->set('masa_garansi_hari', 7)
        ->set('interval_reminder_hari', 90)
        ->call('simpan')
        ->assertHasNoErrors();

    $layanan = Layanan::where('nama', 'Cuci AC')->first();
    expect($layanan->interval_reminder_hari)->toBe(90);

    Livewire::actingAs($owner)->test(LayananIndex::class)
        ->call('aturHarga', $layanan->id)
        ->set("harga.{$a->id}", '75000')
        ->set("harga.{$b->id}", '85000')
        ->call('simpanHarga')
        ->assertHasNoErrors();

    expect($layanan->hargaDiCabang($a->id))->toBe(75000)
        ->and($layanan->hargaDiCabang($b->id))->toBe(85000);

    // kosongkan harga = hapus harga cabang tsb
    Livewire::actingAs($owner)->test(LayananIndex::class)
        ->call('aturHarga', $layanan->id)
        ->set("harga.{$b->id}", '')
        ->call('simpanHarga');

    expect($layanan->hargaDiCabang($b->id))->toBeNull();
});

test('admin cabang hanya bisa mengatur harga cabangnya sendiri', function () {
    [$a, $b] = Cabang::factory()->count(2)->create();
    $admin = User::factory()->adminCabang($a)->create();
    $layanan = Layanan::factory()->create();

    $komponen = Livewire::actingAs($admin)->test(LayananIndex::class)->call('aturHarga', $layanan->id);

    expect(array_keys($komponen->get('harga')))->toBe([$a->id]);

    $komponen->set("harga.{$a->id}", '60000')
        ->set("harga.{$b->id}", '1') // dicoba diselundupkan
        ->call('simpanHarga')
        ->assertHasNoErrors();

    expect(LayananHarga::withoutGlobalScopes()->where('layanan_id', $layanan->id)->pluck('harga', 'cabang_id')->all())
        ->toBe([$a->id => 60000]);
});

test('teks paginasi berbahasa Indonesia', function () {
    $owner = User::factory()->owner()->create();
    Barang::factory()->count(25)->create();

    Livewire::actingAs($owner)->test(BarangIndex::class)
        ->assertSeeInOrder(['Menampilkan', '1', 'sampai', '20', 'dari', '25', 'data'])
        ->assertDontSee('Showing');
});

test('filter status dan pencarian bekerja', function () {
    $owner = User::factory()->owner()->create();
    Barang::factory()->create(['nama' => 'Kapasitor 35uF', 'aktif' => true]);
    Barang::factory()->create(['nama' => 'Pipa AC lama', 'aktif' => false]);

    Livewire::actingAs($owner)->test(BarangIndex::class)
        ->assertSee('Kapasitor 35uF')->assertDontSee('Pipa AC lama')
        ->set('status', 'nonaktif')
        ->assertSee('Pipa AC lama')->assertDontSee('Kapasitor 35uF')
        ->set('status', 'semua')->set('cari', 'kapasitor')
        ->assertSee('Kapasitor 35uF')->assertDontSee('Pipa AC lama');
});
