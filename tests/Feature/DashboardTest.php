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
        ->assertSee('67% selesai');
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
