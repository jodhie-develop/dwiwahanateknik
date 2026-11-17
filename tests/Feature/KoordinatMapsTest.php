<?php

use App\Livewire\Master\PelangganDetail;
use App\Livewire\Master\PelangganIndex;
use App\Models\JenisPerangkat;
use App\Models\Pelanggan;
use App\Models\UnitPelanggan;
use App\Models\User;
use App\Support\KoordinatMaps;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

dataset('link_valid', [
    'koordinat polos' => ['-7.2575, 112.7521', -7.2575, 112.7521],
    'maps ?q=' => ['https://maps.google.com/?q=-7.2575,112.7521', -7.2575, 112.7521],
    'maps ?q= dari WA' => ['https://maps.google.com/maps?q=-7.2575%2C112.7521&z=17&hl=id', -7.2575, 112.7521],
    'place dengan !3d!4d' => ['https://www.google.com/maps/place/Toko+Abc/@-7.2600000,112.7500000,17z/data=!3m1!4b1!4m6!3m5!1s0x0:0x0!8m2!3d-7.2575123!4d112.7521456', -7.2575123, 112.7521456],
    'hanya @lat,lng' => ['https://www.google.com/maps/@-7.2575,112.7521,15z', -7.2575, 112.7521],
    'search' => ['https://www.google.com/maps/search/-7.2575,+112.7521?entry=tts', -7.2575, 112.7521],
    'api=1 query' => ['https://www.google.com/maps/search/?api=1&query=-7.2575,112.7521', -7.2575, 112.7521],
]);

test('membaca koordinat dari berbagai format link', function (string $link, float $lat, float $lng) {
    expect(KoordinatMaps::baca($link))->toBe(['lat' => $lat, 'lng' => $lng]);
})->with('link_valid');

test('teks tanpa koordinat atau di luar batas ditolak', function (string $teks) {
    expect(KoordinatMaps::baca($teks))->toBeNull();
})->with(['Jl. Mawar 1 Surabaya', 'https://www.google.com/maps/place/Surabaya', '95.0, 112.0', '0,0', '']);

test('link pendek diikuti redirect-nya', function () {
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/X/@-7.1,112.1,17z/data=!3d-7.2575!4d112.7521',
        ]),
    ]);

    expect(KoordinatMaps::baca('https://maps.app.goo.gl/AbCdEf123'))->toBe(['lat' => -7.2575, 'lng' => 112.7521]);
});

test('link pendek yang dialihkan ke domain bukan Google ditolak', function () {
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/?q=-7.2,112.7']),
        '*' => Http::response('jangan dipanggil', 200),
    ]);

    expect(KoordinatMaps::baca('https://maps.app.goo.gl/jahat'))->toBeNull();
    Http::assertSentCount(1);
});

test('domain lain tidak pernah diikuti', function () {
    Http::fake();

    expect(KoordinatMaps::baca('https://bit.ly/abc'))->toBeNull();
    Http::assertNothingSent();
});

test('form pelanggan: tempel link mengisi koordinat dan tersimpan', function () {
    $admin = User::factory()->adminCabang()->create();

    Livewire::actingAs($admin)->test(PelangganIndex::class)
        ->call('tambah')
        ->set('nama', 'Bu Lokasi')
        ->set('linkMaps', 'https://maps.google.com/?q=-7.2575,112.7521')
        ->assertSet('latitude', -7.2575)
        ->assertSet('longitude', 112.7521)
        ->assertSet('linkMaps', '')
        ->call('simpan')
        ->assertHasNoErrors();

    $p = Pelanggan::where('nama', 'Bu Lokasi')->first();
    expect($p->latitude)->toBe(-7.2575)->and($p->longitude)->toBe(112.7521);
});

test('form pelanggan: link tidak valid ditolak dan tidak tersimpan', function () {
    $admin = User::factory()->adminCabang()->create();

    Livewire::actingAs($admin)->test(PelangganIndex::class)
        ->call('tambah')
        ->set('nama', 'Pak Salah')
        ->set('linkMaps', 'bukan link')
        ->assertHasErrors('linkMaps')
        ->call('simpan')
        ->assertHasErrors('linkMaps');

    expect(Pelanggan::where('nama', 'Pak Salah')->exists())->toBeFalse();
});

test('hapus koordinat dari pelanggan', function () {
    $admin = User::factory()->adminCabang()->create();
    $p = Pelanggan::factory()->create(['latitude' => -7.25, 'longitude' => 112.75]);

    Livewire::actingAs($admin)->test(PelangganIndex::class)
        ->call('ubah', $p->id)
        ->assertSet('latitude', -7.25)
        ->call('hapusKoordinat')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($p->fresh()->punyaKoordinat())->toBeFalse();
});

test('koordinat unit dipakai jika ada, jika tidak ikut pelanggan', function () {
    $admin = User::factory()->adminCabang()->create();
    $p = Pelanggan::factory()->create(['latitude' => -7.25, 'longitude' => 112.75]);
    $jenis = JenisPerangkat::factory()->create();

    Livewire::actingAs($admin)->test(PelangganDetail::class, ['pelanggan' => $p])
        ->call('tambahUnit')
        ->set('jenis_perangkat_id', $jenis->id)
        ->set('lokasi', 'Rumah kedua')
        ->set('linkMaps', '-7.3, 112.8')
        ->call('simpanUnit')
        ->assertHasNoErrors();

    $unitBeda = UnitPelanggan::where('lokasi', 'Rumah kedua')->first();
    $unitSama = UnitPelanggan::factory()->create(['pelanggan_id' => $p->id]);

    expect($unitBeda->koordinatEfektif())->toBe(['lat' => -7.3, 'lng' => 112.8])
        ->and($unitSama->koordinatEfektif())->toBe(['lat' => -7.25, 'lng' => 112.75]);
});
