<?php

use App\Enums\StatusOrder;
use App\Livewire\Bengkel\BengkelDetail;
use App\Livewire\Bengkel\BengkelIndex;
use App\Livewire\Bengkel\TerimaBarang;
use App\Livewire\Dashboard;
use App\Livewire\Order\EstimasiOrder;
use App\Livewire\Order\ItemOrder;
use App\Livewire\Order\OrderDetail;
use App\Livewire\Pengaturan\PengaturanCabang;
use App\Livewire\Teknisi\JobDetail;
use App\Models\Cabang;
use App\Models\JenisPerangkat;
use App\Models\OrderServis;
use App\Models\Pengaturan;
use App\Models\User;
use App\Notifications\JobBaruDitugaskan;
use App\Services\OrderService;
use App\Support\PengaturanBengkel;
use App\Support\WhatsApp;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.default'));
    $this->cabang = Cabang::factory()->create(['kode' => 'SBY']);
    $this->admin = User::factory()->adminCabang($this->cabang)->create();
    $this->userTeknisi = User::factory()->teknisi($this->cabang, ['bagian' => 'bengkel'])->create();
    $this->teknisi = $this->userTeknisi->teknisi()->withoutGlobalScopes()->first();
    $this->actingAs($this->admin);
    $this->order = terimaBarang($this->cabang, $this->admin, [$this->teknisi->id => 'ketua']);
});

test('form terima barang: pelanggan & barang baru, kelengkapan, foto, teknisi', function () {
    Notification::fake();
    Pengaturan::simpan(PengaturanBengkel::BIAYA_CEK_AKTIF, '1', $this->cabang->id);
    Pengaturan::simpan(PengaturanBengkel::BIAYA_CEK_NOMINAL, '30000', $this->cabang->id);
    $tv = JenisPerangkat::factory()->create(['nama' => 'TV']);

    Livewire::test(TerimaBarang::class)
        ->assertSee('Biaya cek Rp 30.000')
        ->call('mulaiPelangganBaru')
        ->set('pb_nama', 'Pak Darto')
        ->set('pb_hp', '081277776666')
        ->set('ub_jenis_perangkat_id', $tv->id)
        ->set('ub_merk', 'Sharp')
        ->set('ub_nomor_seri', 'SN123')
        ->set('keluhan', 'Gambar tidak muncul')
        ->set('kelengkapan', ['Remote'])
        ->set('kelengkapan_lain', 'Bracket, Baut 4 pcs')
        ->set('lokasi_simpan', 'Rak B-2')
        ->set('teknisi', [$this->teknisi->id])
        ->set('foto', [UploadedFile::fake()->image('tv.jpg')])
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertRedirect();

    $order = OrderServis::query()->bengkel()->latest('id')->first();
    expect($order->pelanggan->nama)->toBe('Pak Darto')
        ->and($order->unit->nomor_seri)->toBe('SN123')
        ->and($order->penerimaan->kelengkapan)->toBe(['Remote', 'Bracket', 'Baut 4 pcs'])
        ->and($order->foto()->where('kategori', 'penerimaan')->count())->toBe(1)
        ->and($order->total)->toBe(30000)
        ->and($order->teknisi()->value('teknisi_id'))->toBe($this->teknisi->id);

    Notification::assertSentTo($this->userTeknisi, JobBaruDitugaskan::class);
});

test('halaman daftar, detail, dan cetak bisa dibuka admin', function () {
    $this->get(route('bengkel.index'))->assertOk()->assertSee($this->order->no_order);
    $this->get(route('bengkel.terima'))->assertOk();
    $this->get(route('bengkel.detail', $this->order))->assertOk()->assertSee('Rak A-1');
    $this->get(route('bengkel.cetak.tanda-terima', $this->order))->assertOk()
        ->assertSee('TANDA TERIMA SERVIS')->assertSee('UNTUK PELANGGAN')->assertSee('ARSIP TOKO')->assertSee('Remote, Kabel power');
    $this->get(route('bengkel.cetak.label', [$this->order, 'jumlah' => 5]))->assertOk()
        ->assertSeeInOrder(array_fill(0, 5, $this->order->no_order));
});

test('order kunjungan tidak bisa dibuka lewat halaman bengkel, dan sebaliknya diarahkan', function () {
    $kunjungan = OrderServis::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->get(route('bengkel.detail', $kunjungan))->assertNotFound();
    $this->get(route('bengkel.cetak.label', $kunjungan))->assertNotFound();

    Livewire::test(OrderDetail::class, ['order' => $this->order])
        ->assertRedirect(route('bengkel.detail', $this->order));
});

test('admin cabang lain tidak bisa melihat order bengkel', function () {
    $lain = sebagaiSistem(fn () => User::factory()->adminCabang(Cabang::factory()->create())->create());

    $this->actingAs($lain)->get(route('bengkel.detail', $this->order))->assertNotFound();
    $this->actingAs($lain)->get(route('bengkel.cetak.tanda-terima', $this->order))->assertNotFound();
});

test('teknisi bengkel: diagnosa, estimasi, catat persetujuan dari HP, tapi tidak bisa serah terima', function () {
    $this->actingAs($this->userTeknisi);
    $this->get(route('teknisi.beranda'))->assertSee('Barang di bengkel')->assertSee($this->order->no_order);

    Livewire::test(JobDetail::class, ['order' => $this->order])
        ->assertSee('Mulai diagnosa')->assertDontSee('Check-in lokasi')
        ->call('ubahStatus', 'diagnosa')->assertHasNoErrors();

    Livewire::test(EstimasiOrder::class, ['orderId' => $this->order->id])
        ->set('catatan', 'Mainboard short')
        ->set('nominal', 400000)
        ->call('ajukan')
        ->assertHasNoErrors()
        ->set('media', 'telepon')
        ->call('putuskan', true)
        ->assertHasNoErrors();

    expect($this->order->fresh()->status)->toBe(StatusOrder::Dikerjakan);

    app(OrderService::class)->ubahStatus($this->order, StatusOrder::Selesai, $this->userTeknisi);
    Livewire::test(JobDetail::class, ['order' => $this->order->fresh()])
        ->call('ubahStatus', 'diambil')->assertForbidden();
});

test('tolak estimasi wajib alasan', function () {
    app(OrderService::class)->ubahStatus($this->order, StatusOrder::Diagnosa, $this->admin);
    app(OrderService::class)->ajukanEstimasi($this->order, 100000, 'x', $this->admin);

    Livewire::test(EstimasiOrder::class, ['orderId' => $this->order->id])
        ->call('putuskan', false)->assertHasErrors('catatanKeputusan')
        ->set('catatanKeputusan', 'Mahal')
        ->call('putuskan', false)->assertHasNoErrors();

    expect($this->order->fresh()->status)->toBe(StatusOrder::Batal);
});

test('teknisi tidak bisa menghapus biaya cek, admin bisa', function () {
    Pengaturan::simpan(PengaturanBengkel::BIAYA_CEK_AKTIF, '1', $this->cabang->id);
    Pengaturan::simpan(PengaturanBengkel::BIAYA_CEK_NOMINAL, '20000', $this->cabang->id);
    $order = terimaBarang($this->cabang, $this->admin, [$this->teknisi->id => 'ketua']);
    $biaya = $order->item()->first();

    Livewire::actingAs($this->userTeknisi)->test(ItemOrder::class, ['orderId' => $order->id])
        ->call('hapus', $biaya->id)->assertForbidden();

    Livewire::actingAs($this->admin)->test(ItemOrder::class, ['orderId' => $order->id])
        ->call('hapus', $biaya->id)->assertHasNoErrors();

    expect($order->fresh()->total)->toBe(0);
});

test('detail: admin ubah lokasi simpan dan tandai diambil', function () {
    $this->order->forceFill(['status' => StatusOrder::Selesai, 'selesai_at' => now()])->saveQuietly();

    Livewire::test(BengkelDetail::class, ['order' => $this->order])
        ->set('lokasi_simpan', 'Rak ambil')
        ->call('simpanLokasi')
        ->call('ubahStatus', 'diambil')
        ->assertHasNoErrors();

    expect($this->order->fresh())->status->toBe(StatusOrder::Diambil)
        ->and($this->order->penerimaan()->value('lokasi_simpan'))->toBe('Rak ambil');
});

test('daftar belum diambil dengan tombol WA pengingat', function () {
    $this->order->forceFill(['status' => StatusOrder::Selesai, 'selesai_at' => now()->subDays(20)])->saveQuietly();
    $this->order->pelanggan->update(['hp' => '081234567890']);

    Livewire::test(BengkelIndex::class)
        ->set('tampil', 'belum_diambil')
        ->assertSee($this->order->no_order)
        ->assertSee('Ingatkan via WA')
        ->assertSee('https://wa.me/6281234567890', false);
});

test('pengaturan: admin mengatur cabangnya, owner mengatur default', function () {
    Livewire::test(PengaturanCabang::class)
        ->set('biaya_cek_aktif', true)
        ->set('biaya_cek_nominal', 0)
        ->call('simpan')->assertHasErrors('biaya_cek_nominal')
        ->set('biaya_cek_nominal', 40000)
        ->set('batas_hari_ambil', 10)
        ->call('simpan')->assertHasNoErrors();

    expect(PengaturanBengkel::biayaCek($this->cabang->id))->toBe(40000)
        ->and(PengaturanBengkel::batasHariAmbil($this->cabang->id))->toBe(10);

    $owner = User::factory()->owner()->create();
    Livewire::actingAs($owner)->test(PengaturanCabang::class)
        ->assertSet('lingkup', '')
        ->set('batas_hari_ambil', 21)
        ->call('simpan')->assertHasNoErrors();

    $cabangLain = Cabang::factory()->create();
    expect(PengaturanBengkel::batasHariAmbil($cabangLain->id))->toBe(21)
        ->and(PengaturanBengkel::batasHariAmbil($this->cabang->id))->toBe(10);
});

test('normalisasi nomor WhatsApp', function () {
    expect(WhatsApp::nomor('0812-3456-7890'))->toBe('6281234567890')
        ->and(WhatsApp::nomor('+62 812 3456 7890'))->toBe('6281234567890')
        ->and(WhatsApp::nomor('812345678'))->toBe('62812345678')
        ->and(WhatsApp::nomor(''))->toBeNull()
        ->and(WhatsApp::link('08123456789', 'Halo & tes'))->toBe('https://wa.me/628123456789?text=Halo%20%26%20tes');
});

test('dashboard menampilkan ringkasan bengkel', function () {
    Livewire::test(Dashboard::class)->assertSee('Barang di bengkel')->assertSee('Menunggu konfirmasi');
});
