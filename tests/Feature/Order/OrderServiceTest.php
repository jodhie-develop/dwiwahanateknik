<?php

use App\Enums\JenisItem;
use App\Enums\StatusOrder;
use App\Models\Barang;
use App\Models\Cabang;
use App\Models\Layanan;
use App\Models\LayananHarga;
use App\Models\Tim;
use App\Models\UnitPelanggan;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PenomoranDokumen;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->cabang = Cabang::factory()->create(['kode' => 'SBY']);
    $this->admin = User::factory()->adminCabang($this->cabang)->create();
    $this->actingAs($this->admin);
    $this->service = app(OrderService::class);
});

function dataOrder(Cabang $cabang, array $timpa = []): array
{
    $unit = UnitPelanggan::factory()->create();

    return [
        'cabang_id' => $cabang->id,
        'tipe' => 'kunjungan',
        'pelanggan_id' => $unit->pelanggan_id,
        'unit_pelanggan_id' => $unit->id,
        'keluhan' => 'AC bocor',
        'tgl_jadwal' => today()->toDateString(),
        ...$timpa,
    ];
}

test('nomor order berurutan per cabang per bulan dengan awalan sesuai tipe', function () {
    $this->travelTo(now()->setDate(2026, 11, 20));

    $a = $this->service->buat(dataOrder($this->cabang), $this->admin);
    $b = $this->service->buat(dataOrder($this->cabang), $this->admin);

    expect($a->no_order)->toBe('KJG/SBY/2611/0001')
        ->and($b->no_order)->toBe('KJG/SBY/2611/0002');

    // Bulan baru mulai dari 1 lagi; cabang lain punya urutan sendiri.
    $this->travelTo(now()->setDate(2026, 12, 1));
    expect($this->service->buat(dataOrder($this->cabang), $this->admin)->no_order)->toBe('KJG/SBY/2612/0001');

    $lain = Cabang::factory()->create(['kode' => 'MLG']);
    expect(app(PenomoranDokumen::class)->berikutnya($lain, 'KJG'))->toBe('KJG/MLG/2612/0001')
        ->and(app(PenomoranDokumen::class)->berikutnya($this->cabang, 'SRV'))->toBe('SRV/SBY/2612/0001');
});

test('order baru berstatus dijadwalkan, tercatat di log, dan total dari layanan', function () {
    $unit = UnitPelanggan::factory()->create();
    $layanan = Layanan::factory()->create(['jenis_perangkat_id' => $unit->jenis_perangkat_id]);
    LayananHarga::withoutGlobalScopes()->create(['layanan_id' => $layanan->id, 'cabang_id' => $this->cabang->id, 'harga' => 75000]);

    $order = $this->service->buat(
        dataOrder($this->cabang, ['pelanggan_id' => $unit->pelanggan_id, 'unit_pelanggan_id' => $unit->id]),
        $this->admin,
        [['layanan_id' => $layanan->id, 'qty' => 2]],
    );

    expect($order->status)->toBe(StatusOrder::Dijadwalkan)
        ->and($order->total)->toBe(150000)
        ->and($order->log()->count())->toBe(1)
        ->and($order->log()->first()->ke_status)->toBe(StatusOrder::Dijadwalkan)
        ->and($order->log()->first()->user_id)->toBe($this->admin->id);
});

test('alur status kunjungan yang sah dan transisi yang ditolak', function () {
    $tim = timDenganAnggota($this->cabang);
    $order = $this->service->buat(dataOrder($this->cabang), $this->admin);

    // Belum ada tim → tidak boleh berangkat.
    expect(fn () => $this->service->ubahStatus($order, StatusOrder::Berangkat, $this->admin))
        ->toThrow(ValidationException::class);

    $this->service->tetapkanTim($order, $tim, $this->admin);

    // Loncat langsung ke selesai ditolak.
    expect(fn () => $this->service->ubahStatus($order, StatusOrder::Selesai, $this->admin))
        ->toThrow(ValidationException::class, 'tidak bisa diubah');

    foreach ([StatusOrder::Berangkat, StatusOrder::Dikerjakan, StatusOrder::Selesai] as $s) {
        $this->service->ubahStatus($order, $s, $this->admin);
    }

    $order->refresh();
    expect($order->status)->toBe(StatusOrder::Selesai)
        ->and($order->selesai_at)->not->toBeNull()
        ->and($order->log()->where('dari_status', StatusOrder::Dikerjakan)->where('ke_status', StatusOrder::Selesai)->exists())->toBeTrue();

    // Setelah selesai tidak bisa dibatalkan & item terkunci.
    expect(fn () => $this->service->ubahStatus($order, StatusOrder::Batal, $this->admin, 'x'))->toThrow(ValidationException::class);
    expect(fn () => $this->service->tambahItem($order, JenisItem::Barang, Barang::factory()->create()->id, 1, null, $this->admin))
        ->toThrow(ValidationException::class);
});

test('batal wajib ada alasan', function () {
    $order = $this->service->buat(dataOrder($this->cabang), $this->admin);

    expect(fn () => $this->service->ubahStatus($order, StatusOrder::Batal, $this->admin))->toThrow(ValidationException::class, 'Alasan');

    $this->service->ubahStatus($order, StatusOrder::Batal, $this->admin, 'Pelanggan tidak di rumah');
    expect($order->fresh()->status)->toBe(StatusOrder::Batal)
        ->and($order->log()->latest('id')->first()->catatan)->toBe('Pelanggan tidak di rumah');
});

test('tetapkan tim mengisi order_teknisi dari anggota aktif, admin bisa mengganti orangnya', function () {
    $tim = timDenganAnggota($this->cabang);
    $order = $this->service->buat(dataOrder($this->cabang), $this->admin);

    $userIds = $this->service->tetapkanTim($order, $tim, $this->admin);

    expect($userIds)->toHaveCount(2)
        ->and($order->teknisi()->count())->toBe(2)
        ->and($order->teknisi()->where('peran', 'ketua')->count())->toBe(1);

    $pengganti = User::factory()->teknisi($this->cabang)->create()->teknisi()->withoutGlobalScopes()->first();
    $ketua = $order->teknisi()->where('peran', 'ketua')->value('teknisi_id');

    $this->service->aturTeknisi($order, [$ketua => 'ketua', $pengganti->id => 'anggota'], $this->admin);

    expect($order->teknisi()->pluck('teknisi_id')->sort()->values()->all())
        ->toBe(collect([$ketua, $pengganti->id])->sort()->values()->all())
        ->and($order->fresh()->tim_id)->toBe($tim->id);
});

test('teknisi yang dihapus keluar dari tim dan tidak ikut ditugaskan', function () {
    $tim = timDenganAnggota($this->cabang, 'Tim A', 2);
    $order = $this->service->buat(dataOrder($this->cabang), $this->admin);
    $korban = $tim->anggota()->first()->teknisi()->withoutGlobalScopes()->first();

    $korban->user->delete(); // hapus akun → profil teknisi ikut terhapus

    expect($tim->anggotaAktif()->count())->toBe(1);
    $this->service->tetapkanTim($order, $tim, $this->admin);
    expect($order->teknisi()->pluck('teknisi_id')->all())->not->toContain($korban->id)
        ->and($order->teknisi()->count())->toBe(1);
});

test('tim cabang lain atau tanpa anggota ditolak', function () {
    $order = $this->service->buat(dataOrder($this->cabang), $this->admin);
    $kosong = Tim::factory()->create(['cabang_id' => $this->cabang->id]);
    $lain = sebagaiSistem(fn () => timDenganAnggota(Cabang::factory()->create()));

    expect(fn () => $this->service->tetapkanTim($order, $kosong, $this->admin))->toThrow(ValidationException::class, 'anggota');
    expect(fn () => $this->service->tetapkanTim($order, $lain, $this->admin))->toThrow(ValidationException::class);
});

test('item barang & diskon menghitung ulang total', function () {
    $order = $this->service->buat(dataOrder($this->cabang), $this->admin);
    $freon = Barang::factory()->create(['harga_jual' => 120000]);

    $item = $this->service->tambahItem($order, JenisItem::Barang, $freon->id, 1.5, null, $this->admin);
    expect($item->subtotal)->toBe(180000)->and($order->fresh()->total)->toBe(180000);

    $this->service->ubahDiskon($order, 30000);
    expect($order->fresh()->total)->toBe(150000);

    expect(fn () => $this->service->ubahDiskon($order, 999999))->toThrow(ValidationException::class);

    $this->service->hapusItem($order, $item->id);
    expect($order->fresh())->subtotal->toBe(0)->total->toBe(0)->diskon->toBe(0);
});

test('mengubah tanggal order melepas tim', function () {
    $tim = timDenganAnggota($this->cabang);
    $order = $this->service->buat(dataOrder($this->cabang), $this->admin);
    $this->service->tetapkanTim($order, $tim, $this->admin);

    $this->service->ubahData($order, ['tgl_jadwal' => today()->addDay()->toDateString(), 'keluhan' => 'Ganti'], $this->admin);

    expect($order->fresh()->tim_id)->toBeNull()->and($order->teknisi()->count())->toBe(0);
});

test('tanda tangan disimpan sebagai PNG', function () {
    Storage::fake(config('filesystems.default'));
    $order = $this->service->buat(dataOrder($this->cabang), $this->admin);

    $img = imagecreatetruecolor(10, 10);
    ob_start();
    imagepng($img);
    $png = base64_encode(ob_get_clean());

    $this->service->simpanTandaTangan($order, 'data:image/png;base64,'.$png);
    Storage::assertExists($order->fresh()->tanda_tangan);

    expect(fn () => $this->service->simpanTandaTangan($order, 'data:image/png;base64,'.base64_encode('<?php echo 1;')))
        ->toThrow(ValidationException::class);
});
