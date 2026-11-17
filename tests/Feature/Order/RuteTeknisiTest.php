<?php

use App\Enums\StatusOrder;
use App\Livewire\Master\CabangIndex;
use App\Models\Cabang;
use App\Models\OrderServis;
use App\Models\User;
use App\Services\OrderService;
use App\Services\RuteTeknisi;
use Livewire\Livewire;

beforeEach(function () {
    $this->cabang = Cabang::factory()->create(['nama' => 'Surabaya', 'latitude' => -7.2500, 'longitude' => 112.7500]);
    $this->admin = User::factory()->adminCabang($this->cabang)->create();
    $this->actingAs($this->admin);
    $this->tim = timDenganAnggota($this->cabang, 'Tim A');
    $this->teknisi = $this->tim->anggota()->first()->teknisi()->withoutGlobalScopes()->first()->user;
    $this->rute = app(RuteTeknisi::class);
});

/** Order hari ini untuk tim, input pada menit ke-$menit, di lokasi tertentu (null = tanpa lokasi). */
function jobRute($tim, int $menit, ?array $lokasi, StatusOrder $status = StatusOrder::Dijadwalkan): OrderServis
{
    $f = OrderServis::factory();
    if ($lokasi) {
        $f = $f->diLokasi(...$lokasi);
    }
    $o = $f->create(['cabang_id' => $tim->cabang_id]);
    $o->forceFill(['created_at' => today()->setTime(7, 0)->addMinutes($menit)])->saveQuietly();
    app(OrderService::class)->tetapkanTim($o, $tim, auth()->user());
    $o->forceFill(['status' => $status])->saveQuietly();

    return $o;
}

test('belum mulai: rute dari kantor ke semua order urut antrian', function () {
    $b = jobRute($this->tim, 20, [-7.27, 112.76]);
    $a = jobRute($this->tim, 10, [-7.26, 112.75]);
    $tanpa = jobRute($this->tim, 30, null);

    $hasil = $this->rute->untuk($this->teknisi);

    expect($hasil['mode'])->toBe('awal')
        ->and(array_column($hasil['titik'], 'jenis'))->toBe(['kantor', 'order', 'order'])
        ->and(array_column($hasil['titik'], 'order_id'))->toBe([null, $a->id, $b->id])
        ->and($hasil['titik'][0]['label'])->toBe('Kantor Surabaya')
        ->and($hasil['tanpaLokasi'])->toBe(['3. '.$tanpa->pelanggan->nama]);
});

test('sudah mengerjakan: rute dari order sekarang ke order berikutnya', function () {
    $satu = jobRute($this->tim, 0, [-7.26, 112.75], StatusOrder::Selesai);
    $dua = jobRute($this->tim, 10, [-7.27, 112.76], StatusOrder::Dikerjakan);
    $tiga = jobRute($this->tim, 20, [-7.28, 112.77]);
    jobRute($this->tim, 30, [-7.29, 112.78]);

    $hasil = $this->rute->untuk($this->teknisi);

    expect($hasil['mode'])->toBe('lanjut')
        ->and(array_column($hasil['titik'], 'order_id'))->toBe([$dua->id, $tiga->id])
        ->and(array_column($hasil['titik'], 'nomor'))->toBe([2, 3])
        ->and($hasil['titik'][0]['jenis'])->toBe('aktif');
});

test('di antara job (yang terakhir selesai): rute dari order selesai terakhir ke berikutnya', function () {
    $satu = jobRute($this->tim, 0, [-7.26, 112.75], StatusOrder::Selesai);
    $dua = jobRute($this->tim, 10, [-7.27, 112.76]);

    $hasil = $this->rute->untuk($this->teknisi);

    expect(array_column($hasil['titik'], 'order_id'))->toBe([$satu->id, $dua->id])
        ->and($hasil['pesan'])->toBeNull();
});

test('semua selesai: hanya titik terakhir dengan pesan tidak ada job berikutnya', function () {
    $satu = jobRute($this->tim, 0, [-7.26, 112.75], StatusOrder::Selesai);

    $hasil = $this->rute->untuk($this->teknisi);

    expect(array_column($hasil['titik'], 'order_id'))->toBe([$satu->id])
        ->and($hasil['pesan'])->toBe('Tidak ada job berikutnya hari ini.');
});

test('kantor tanpa lokasi: rute mulai dari job pertama dengan peringatan', function () {
    $this->cabang->update(['latitude' => null, 'longitude' => null]);
    jobRute($this->tim, 0, [-7.26, 112.75]);

    $hasil = $this->rute->untuk($this->teknisi);

    expect(array_column($hasil['titik'], 'jenis'))->toBe(['order'])
        ->and($hasil['pesan'])->toContain('Lokasi kantor');
});

test('job teknisi lain dan order batal tidak masuk rute', function () {
    $timB = timDenganAnggota($this->cabang, 'Tim B');
    jobRute($timB, 0, [-7.3, 112.8]);
    jobRute($this->tim, 5, [-7.26, 112.75], StatusOrder::Batal);

    expect($this->rute->untuk($this->teknisi)['mode'])->toBe('kosong');
});

test('link Google Maps multi-titik', function () {
    $titik = [
        ['lat' => -7.25, 'lng' => 112.75], ['lat' => -7.26, 'lng' => 112.76], ['lat' => -7.27, 'lng' => 112.77],
    ];

    expect(RuteTeknisi::linkGoogleMaps($titik))
        ->toBe('https://www.google.com/maps/dir/?api=1&origin=-7.25,112.75&destination=-7.27,112.77&waypoints=-7.26%2C112.76&travelmode=driving')
        ->and(RuteTeknisi::linkGoogleMaps([]))->toBeNull();
});

test('halaman peta & tombol Lihat Peta untuk teknisi', function () {
    jobRute($this->tim, 0, [-7.26, 112.75]);

    $this->actingAs($this->teknisi)->get(route('teknisi.beranda'))->assertSee('Lihat Peta')
        ->assertDontSee('Buka 1'); // regresi: icon:trailing tanpa nilai menampilkan "1"
    $this->actingAs($this->teknisi)->get(route('teknisi.peta'))->assertOk()
        ->assertSee('Rute dari kantor')->assertSee('leaflet')->assertSee('router.project-osrm.org')
        ->assertSee('Navigasi di Google Maps');

    $this->actingAs($this->admin)->get(route('teknisi.peta'))->assertForbidden();
});

test('owner mengisi lokasi kantor cabang lewat link Maps', function () {
    $owner = User::factory()->owner()->create();

    Livewire::actingAs($owner)->test(CabangIndex::class)
        ->call('ubah', $this->cabang->id)
        ->assertSet('latitude', -7.25)
        ->set('linkMaps', 'https://maps.google.com/?q=-7.2600,112.7400')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($this->cabang->fresh()->koordinat())->toBe(['lat' => -7.26, 'lng' => 112.74]);
});
