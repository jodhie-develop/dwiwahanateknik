<?php

use App\Enums\JenisItem;
use App\Enums\MediaPersetujuan;
use App\Enums\StatusEstimasi;
use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\OrderServis;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\OrderService;
use App\Support\PengaturanBengkel;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->cabang = Cabang::factory()->create(['kode' => 'SBY']);
    $this->admin = User::factory()->adminCabang($this->cabang)->create();
    $this->actingAs($this->admin);
    $this->service = app(OrderService::class);
    $this->teknisi = User::factory()->teknisi($this->cabang, ['bagian' => 'bengkel'])->create()
        ->teknisi()->withoutGlobalScopes()->first();
});

test('terima barang: nomor SRV, status diterima, tanda terima tersimpan', function () {
    $this->travelTo(now()->setDate(2026, 11, 20));
    $order = terimaBarang($this->cabang, $this->admin, [$this->teknisi->id => 'ketua']);

    expect($order->no_order)->toBe('SRV/SBY/2611/0001')
        ->and($order->status)->toBe(StatusOrder::Diterima)
        ->and($order->tgl_jadwal->isToday())->toBeTrue()
        ->and($order->penerimaan->kelengkapan)->toBe(['Remote', 'Kabel power'])
        ->and($order->penerimaan->kelengkapanTeks())->toBe('Remote, Kabel power')
        ->and($order->penerimaan->diterima_oleh)->toBe($this->admin->id)
        ->and($order->teknisi()->value('teknisi_id'))->toBe($this->teknisi->id)
        ->and($order->total)->toBe(0); // biaya cek nonaktif secara default

    // Nomor kunjungan & bengkel punya urutan terpisah.
    expect(terimaBarang($this->cabang, $this->admin)->no_order)->toBe('SRV/SBY/2611/0002');
});

test('biaya cek aktif otomatis ditagihkan', function () {
    Pengaturan::simpan(PengaturanBengkel::BIAYA_CEK_AKTIF, '1', $this->cabang->id);
    Pengaturan::simpan(PengaturanBengkel::BIAYA_CEK_NOMINAL, '50000', $this->cabang->id);

    $order = terimaBarang($this->cabang, $this->admin);

    expect($order->item()->first())
        ->jenis->toBe(JenisItem::Biaya)
        ->nama->toBe('Biaya cek')
        ->subtotal->toBe(50000)
        ->and($order->total)->toBe(50000);
});

test('pengaturan cabang menimpa default global', function () {
    Pengaturan::simpan(PengaturanBengkel::BATAS_HARI_AMBIL, '30');
    expect(PengaturanBengkel::batasHariAmbil($this->cabang->id))->toBe(30);

    Pengaturan::simpan(PengaturanBengkel::BATAS_HARI_AMBIL, '7', $this->cabang->id);
    expect(PengaturanBengkel::batasHariAmbil($this->cabang->id))->toBe(7)
        ->and(PengaturanBengkel::batasHariAmbil(Cabang::factory()->create()->id))->toBe(30);

    // Default bawaan 14 hari bila belum diatur sama sekali.
    Pengaturan::query()->delete();
    expect(PengaturanBengkel::batasHariAmbil($this->cabang->id))->toBe(14);
});

test('alur lengkap: diagnosa → estimasi → disetujui → sparepart → selesai → diambil', function () {
    $order = terimaBarang($this->cabang, $this->admin);

    // Tanpa teknisi tidak bisa diagnosa.
    expect(fn () => $this->service->ubahStatus($order, StatusOrder::Diagnosa, $this->admin))->toThrow(ValidationException::class, 'teknisi');
    $this->service->aturTeknisi($order, [$this->teknisi->id => 'ketua'], $this->admin);
    $this->service->ubahStatus($order, StatusOrder::Diagnosa, $this->admin);

    // Tidak bisa langsung menunggu konfirmasi tanpa estimasi.
    expect(fn () => $this->service->ubahStatus($order, StatusOrder::MenungguKonfirmasi, $this->admin))->toThrow(ValidationException::class, 'estimasi');

    $this->service->ajukanEstimasi($order, 350000, 'Ganti kapasitor', $this->admin);
    expect($order->fresh()->status)->toBe(StatusOrder::MenungguKonfirmasi);

    // Belum disetujui → tidak boleh dikerjakan.
    expect(fn () => $this->service->ubahStatus($order, StatusOrder::Dikerjakan, $this->admin))
        ->toThrow(ValidationException::class, 'belum disetujui');

    $this->service->catatPersetujuan($order, true, MediaPersetujuan::WhatsApp, $this->admin, 'OK lanjut');
    $order->refresh();
    expect($order->status)->toBe(StatusOrder::Dikerjakan)
        ->and($order->estimasiTerakhir->status)->toBe(StatusEstimasi::Disetujui)
        ->and($order->estimasiTerakhir->media)->toBe(MediaPersetujuan::WhatsApp)
        ->and($order->estimasiTerakhir->dicatat_oleh)->toBe($this->admin->id);

    foreach ([StatusOrder::MenungguSparepart, StatusOrder::Dikerjakan, StatusOrder::Selesai, StatusOrder::Diambil] as $s) {
        $this->service->ubahStatus($order, $s, $this->admin);
    }

    $order->refresh();
    expect($order->status)->toBe(StatusOrder::Diambil)
        ->and($order->selesai_at)->not->toBeNull()
        ->and($order->diambil_at)->not->toBeNull()
        ->and($order->log()->where('catatan', 'like', '%disetujui pelanggan via WhatsApp%')->exists())->toBeTrue();
});

test('estimasi ditolak: order batal, biaya cek tetap, lalu dikembalikan', function () {
    Pengaturan::simpan(PengaturanBengkel::BIAYA_CEK_AKTIF, '1', $this->cabang->id);
    Pengaturan::simpan(PengaturanBengkel::BIAYA_CEK_NOMINAL, '25000', $this->cabang->id);
    $order = terimaBarang($this->cabang, $this->admin, [$this->teknisi->id => 'ketua']);
    $this->service->ubahStatus($order, StatusOrder::Diagnosa, $this->admin);
    $this->service->ajukanEstimasi($order, 900000, 'Panel rusak', $this->admin);

    // Batal langsung (tanpa mencatat jawaban) ditolak.
    expect(fn () => $this->service->ubahStatus($order, StatusOrder::Batal, $this->admin, 'x'))->toThrow(ValidationException::class);

    $this->service->catatPersetujuan($order, false, MediaPersetujuan::Telepon, $this->admin, 'Terlalu mahal');
    $order->refresh();
    expect($order->status)->toBe(StatusOrder::Batal)->and($order->total)->toBe(25000);

    $this->service->ubahStatus($order, StatusOrder::Dikembalikan, $this->admin);
    expect($order->fresh())->status->toBe(StatusOrder::Dikembalikan)->diambil_at->not->toBeNull();
});

test('revisi estimasi menggantikan estimasi yang masih menunggu', function () {
    $order = terimaBarang($this->cabang, $this->admin, [$this->teknisi->id => 'ketua']);
    $this->service->ubahStatus($order, StatusOrder::Diagnosa, $this->admin);
    $this->service->ajukanEstimasi($order, 300000, 'Awal', $this->admin);
    $this->service->ajukanEstimasi($order, 450000, 'Ternyata perlu ganti IC juga', $this->admin);

    $order->refresh();
    expect($order->estimasi()->count())->toBe(2)
        ->and($order->estimasiTerakhir->nominal)->toBe(450000)
        ->and($order->estimasiTerakhir->status)->toBe(StatusEstimasi::Menunggu)
        ->and($order->estimasi()->where('status', 'menunggu')->count())->toBe(1);
});

test('persetujuan tanpa estimasi menunggu ditolak', function () {
    $order = terimaBarang($this->cabang, $this->admin);

    expect(fn () => $this->service->catatPersetujuan($order, true, MediaPersetujuan::Langsung, $this->admin))
        ->toThrow(ValidationException::class);
});

test('daftar belum diambil lewat batas hari', function () {
    $lama = terimaBarang($this->cabang, $this->admin);
    $baru = terimaBarang($this->cabang, $this->admin);
    $lama->forceFill(['status' => StatusOrder::Selesai, 'selesai_at' => now()->subDays(20)])->saveQuietly();
    $baru->forceFill(['status' => StatusOrder::Selesai, 'selesai_at' => now()->subDays(3)])->saveQuietly();

    expect(OrderServis::query()->belumDiambilLewat(14)->pluck('id')->all())->toBe([$lama->id]);
});
