<?php

use App\Enums\StatusOrder;
use App\Livewire\Order\JadwalTim;
use App\Livewire\Order\OrderDetail;
use App\Livewire\Order\OrderForm;
use App\Livewire\Teknisi\JobDetail;
use App\Models\Cabang;
use App\Models\JenisPerangkat;
use App\Models\OrderServis;
use App\Models\Pelanggan;
use App\Models\UnitPelanggan;
use App\Models\User;
use App\Services\OrderService;
use Livewire\Livewire;

beforeEach(function () {
    $this->cabang = Cabang::factory()->create(['kode' => 'SBY']);
    $this->admin = User::factory()->adminCabang($this->cabang)->create();
    $this->tim = timDenganAnggota($this->cabang, 'Tim A');
    $this->order = OrderServis::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->actingAs($this->admin);
    app(OrderService::class)->tetapkanTim($this->order, $this->tim, $this->admin);

    $this->teknisiTim = $this->tim->anggota()->first()->teknisi()->withoutGlobalScopes()->first()->user;
});

test('admin bisa membuka halaman order, jadwal, form, dan detail', function () {
    $this->get(route('order.index'))->assertOk()->assertSee($this->order->no_order);
    $this->get(route('order.jadwal'))->assertOk()->assertSee('Tim A');
    $this->get(route('order.baru'))->assertOk();
    $this->get(route('order.detail', $this->order))->assertOk()->assertSee($this->order->no_order);
});

test('admin cabang lain tidak bisa melihat order', function () {
    $lain = User::factory()->adminCabang(Cabang::factory()->create())->create();

    $this->actingAs($lain)->get(route('order.detail', $this->order))->assertNotFound();
    $this->actingAs($lain)->get(route('order.index'))->assertDontSee($this->order->no_order);
});

test('teknisi hanya melihat job miliknya', function () {
    $teknisiLain = User::factory()->teknisi($this->cabang)->create();

    $this->actingAs($this->teknisiTim)->get(route('teknisi.beranda'))->assertOk()->assertSee($this->order->pelanggan->nama);
    $this->actingAs($this->teknisiTim)->get(route('teknisi.job', $this->order))->assertOk();

    $this->actingAs($teknisiLain)->get(route('teknisi.beranda'))->assertDontSee($this->order->pelanggan->nama);
    $this->actingAs($teknisiLain)->get(route('teknisi.job', $this->order))->assertNotFound();
    expect(OrderServis::count())->toBe(0);

    // Teknisi tidak bisa membuka halaman order admin.
    $this->actingAs($this->teknisiTim)->get(route('order.detail', $this->order))->assertForbidden();
});

test('teknisi bisa mengubah status lapangan tapi tidak bisa membatalkan', function () {
    $komponen = Livewire::actingAs($this->teknisiTim)->test(JobDetail::class, ['order' => $this->order]);

    $komponen->call('ubahStatus', 'berangkat')->assertHasNoErrors();
    expect($this->order->fresh()->status)->toBe(StatusOrder::Berangkat);

    $komponen->call('ubahStatus', 'batal')->assertForbidden();
});

test('teknisi check-in GPS dan tanda tangan', function () {
    Storage::fake(config('filesystems.default'));
    $img = imagecreatetruecolor(5, 5);
    ob_start();
    imagepng($img);
    $png = 'data:image/png;base64,'.base64_encode(ob_get_clean());

    Livewire::actingAs($this->teknisiTim)->test(JobDetail::class, ['order' => $this->order])
        ->call('checkin', -7.25, 112.75)
        ->call('simpanTandaTangan', $png)
        ->assertHasNoErrors();

    $o = $this->order->fresh();
    expect($o->checkin_lat)->toBe(-7.25)->and($o->tanda_tangan)->not->toBeNull();
});

test('form order: pelanggan & unit baru dibuat sekaligus', function () {
    $jenis = JenisPerangkat::factory()->create(['nama' => 'AC']);

    Livewire::test(OrderForm::class)
        ->call('mulaiPelangganBaru')
        ->set('pb_nama', 'Bu Rina')
        ->set('pb_hp', '081299998888')
        ->set('pb_alamat', 'Jl. Kenanga 5')
        ->set('linkMaps', '-7.26, 112.74')
        ->set('ub_jenis_perangkat_id', $jenis->id)
        ->set('ub_lokasi', 'Kamar tamu')
        ->set('keluhan', 'AC netes')
        ->set('jam_janji', '13:00')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertRedirect();

    $p = Pelanggan::where('nama', 'Bu Rina')->first();
    $order = OrderServis::where('pelanggan_id', $p->id)->first();

    expect($p->latitude)->toBe(-7.26)
        ->and($order->unit->lokasi)->toBe('Kamar tamu')
        ->and($order->status)->toBe(StatusOrder::Dijadwalkan)
        ->and($order->tim_id)->toBeNull()
        ->and($order->koordinat())->toBe(['lat' => -7.26, 'lng' => 112.74])
        ->and($order->no_order)->toStartWith('KJG/SBY/');
});

test('form order: unit harus milik pelanggan yang dipilih', function () {
    $p = Pelanggan::factory()->create();
    $unitLain = UnitPelanggan::factory()->create();

    Livewire::test(OrderForm::class)
        ->call('pilihPelanggan', $p->id)
        ->set('unitBaru', false)
        ->set('unit_pelanggan_id', $unitLain->id)
        ->set('keluhan', 'x')
        ->call('simpan')
        ->assertHasErrors('unit_pelanggan_id');
});

test('detail order: admin ganti tim dan batalkan dengan alasan', function () {
    $timB = timDenganAnggota($this->cabang, 'Tim B');

    Livewire::test(OrderDetail::class, ['order' => $this->order])
        ->set('tim_id', $timB->id)
        ->call('simpanTim')
        ->assertHasNoErrors()
        ->set('alasanBatal', 'Dobel input')
        ->call('batalkan')
        ->assertHasNoErrors();

    expect($this->order->fresh())->tim_id->toBe($timB->id)->status->toBe(StatusOrder::Batal);
});

test('jadwal tim: bagi tim lalu terapkan', function () {
    Notification::fake();
    $baru = OrderServis::factory()->create(['cabang_id' => $this->cabang->id]);

    Livewire::test(JadwalTim::class)
        ->call('bagiTim')
        ->assertSet("usulan.{$baru->id}", $this->tim->id)
        ->call('terapkan')
        ->assertHasNoErrors();

    expect($baru->fresh()->tim_id)->toBe($this->tim->id);
});

test('jadwal tim: admin tidak bisa menerapkan order cabang lain', function () {
    $lain = sebagaiSistem(fn () => OrderServis::factory()->create());
    expect($lain->cabang_id)->not->toBe($this->cabang->id);

    Livewire::test(JadwalTim::class)
        ->set('usulan', [$lain->id => $this->tim->id])
        ->call('terapkan')
        ->assertForbidden();
});
