<?php

use App\Enums\StatusOrder;
use App\Livewire\Dashboard;
use App\Models\Cabang;
use App\Models\OrderServis;
use App\Models\User;
use App\Services\OrderService;
use Livewire\Livewire;

beforeEach(function () {
    $this->cabang = Cabang::factory()->create();
    $this->admin = User::factory()->adminCabang($this->cabang)->create();
    $this->actingAs($this->admin);
    $this->service = app(OrderService::class);
});

function jobTim($tim, StatusOrder $status, ?string $tanggal = null): OrderServis
{
    $order = OrderServis::factory()->create(['cabang_id' => $tim->cabang_id, 'tgl_jadwal' => $tanggal ?? today()->toDateString()]);
    app(OrderService::class)->tetapkanTim($order, $tim, auth()->user());
    $order->forceFill(['status' => $status])->saveQuietly();

    return $order;
}

test('box tiap tim menampilkan anggota, jumlah job, dan yang selesai', function () {
    $timA = timDenganAnggota($this->cabang, 'Tim Alfa');
    $timB = timDenganAnggota($this->cabang, 'Tim Bravo');

    jobTim($timA, StatusOrder::Selesai);
    jobTim($timA, StatusOrder::Selesai);
    jobTim($timA, StatusOrder::Dikerjakan);
    jobTim($timA, StatusOrder::Batal);            // batal tidak dihitung sebagai job
    jobTim($timA, StatusOrder::Selesai, today()->addDay()->toDateString()); // hari lain

    $box = Livewire::test(Dashboard::class)->viewData('box')->keyBy(fn ($b) => $b['tim']->nama_tim);

    expect($box['Tim Alfa'])->toMatchArray(['total' => 3, 'selesai' => 2, 'berjalan' => 1, 'batal' => 1, 'persen' => 67])
        ->and($box['Tim Bravo'])->toMatchArray(['total' => 0, 'selesai' => 0]);

    $anggota = $timA->anggotaAktif()->with('teknisi')->get()->pluck('teknisi.nama');
    Livewire::test(Dashboard::class)
        ->assertSee('Tim Alfa')->assertSee('Tim Bravo')
        ->assertSee($anggota->all())
        ->assertSee('2 dari 3 job selesai (67%)');
});

test('tanggal bisa diganti', function () {
    $tim = timDenganAnggota($this->cabang, 'Tim Alfa');
    jobTim($tim, StatusOrder::Selesai, today()->addDay()->toDateString());

    $box = Livewire::test(Dashboard::class)->call('geserHari', 1)->viewData('box');
    expect($box->first())->toMatchArray(['total' => 1, 'selesai' => 1, 'persen' => 100]);
});

test('menampilkan jumlah order yang belum dibagi', function () {
    timDenganAnggota($this->cabang, 'Tim Alfa');
    OrderServis::factory()->count(2)->create(['cabang_id' => $this->cabang->id]);

    Livewire::test(Dashboard::class)->assertSee('2 order belum dibagi ke tim');
});

test('admin cabang hanya melihat tim cabangnya', function () {
    timDenganAnggota($this->cabang, 'Tim Sendiri');
    sebagaiSistem(fn () => timDenganAnggota(Cabang::factory()->create(), 'Tim Cabang Lain'));

    $this->get(route('dashboard'))->assertOk()->assertSee('Tim Sendiri')->assertDontSee('Tim Cabang Lain');
});

test('admin cabang selalu melihat monitor, tanpa tab ringkasan', function () {
    timDenganAnggota($this->cabang, 'Tim Alfa');

    Livewire::withQueryParams(['tampil' => 'ringkasan'])->test(Dashboard::class)
        ->assertViewHas('tampilanAktif', 'monitor')
        ->assertSee('Job Progress Monitoring')
        ->assertDontSee('Monitor TV')
        ->assertDontSee('Order terbaru');
});

test('monitor menampilkan daftar job tiap tim', function () {
    $tim = timDenganAnggota($this->cabang, 'Tim Alfa');
    $order = jobTim($tim, StatusOrder::Dijadwalkan);
    $order->forceFill(['keluhan' => 'AC netes di kamar'])->saveQuietly();
    jobTim($tim, StatusOrder::Dikerjakan);

    $box = Livewire::test(Dashboard::class)->viewData('box')->first();
    expect($box['jobs'])->toHaveCount(2)
        ->and($box['aktif']['status'])->toBe(StatusOrder::Dikerjakan);

    Livewire::test(Dashboard::class)->assertSee('AC netes di kamar')->assertSee('SEDANG DIKERJAKAN');
});

test('owner melihat tab ringkasan dan bisa pindah ke monitor', function () {
    $owner = User::factory()->owner()->create();
    $this->actingAs($owner);
    $tim = timDenganAnggota($this->cabang, 'Tim Alfa');
    jobTim($tim, StatusOrder::Dikerjakan);
    jobTim($tim, StatusOrder::Selesai);

    Livewire::test(Dashboard::class)
        ->assertViewHas('tampilanAktif', 'ringkasan')
        ->assertSee(['Ringkasan', 'Monitor TV', 'Jadwal tim hari ini', 'Order terbaru', 'Alur servis bengkel', 'Segera hadir'])
        ->assertViewHas('kpi', fn ($kpi) => $kpi['kunjungan'] === 2 && $kpi['timJalan'] === 1)
        ->set('tampilan', 'monitor')
        ->assertViewHas('tampilanAktif', 'monitor')
        ->assertSee('Job Progress Monitoring');
});

test('order terbaru bisa difilter per tipe', function () {
    $owner = User::factory()->owner()->create();
    $this->actingAs($owner);
    OrderServis::factory()->create(['cabang_id' => $this->cabang->id]);

    Livewire::test(Dashboard::class)
        ->assertViewHas('orderTerbaru', fn ($o) => $o->count() === 1)
        ->set('filterOrder', 'bengkel')
        ->assertViewHas('orderTerbaru', fn ($o) => $o->isEmpty());
});
