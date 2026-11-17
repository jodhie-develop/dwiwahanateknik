<?php

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\OrderServis;
use App\Models\Tim;
use App\Models\User;
use App\Notifications\JobBaruDitugaskan;
use App\Services\OrderService;
use App\Services\PembagianTim;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->cabang = Cabang::factory()->create(['kode' => 'SBY']);
    $this->admin = User::factory()->adminCabang($this->cabang)->create();
    $this->actingAs($this->admin);
    $this->pembagian = app(PembagianTim::class);

    $this->timA = timDenganAnggota($this->cabang, 'Tim A');
    $this->timB = timDenganAnggota($this->cabang, 'Tim B');
});

/** Buat order kunjungan hari ini dengan waktu input tertentu (menit dari jam 07:00). */
function orderMasuk(Cabang $cabang, int $menit, ?array $lokasi = null, array $timpa = []): OrderServis
{
    $factory = OrderServis::factory();
    if ($lokasi) {
        $factory = $factory->diLokasi(...$lokasi);
    }

    $order = $factory->create(['cabang_id' => $cabang->id, ...$timpa]);
    $order->forceFill(['created_at' => today()->setTime(7, 0)->addMinutes($menit)])->saveQuietly();

    return $order;
}

function petaUsulan(array $usulan): array
{
    return collect($usulan)->mapWithKeys(fn ($b) => [$b['order']->id => $b['tim']?->nama_tim])->all();
}

test('belum ada tim bergerak: order dibagi rata urut waktu input', function () {
    $o3 = orderMasuk($this->cabang, 30);
    $o1 = orderMasuk($this->cabang, 0);
    $o2 = orderMasuk($this->cabang, 10);
    $o4 = orderMasuk($this->cabang, 45);

    $usulan = $this->pembagian->usulan($this->cabang->id, today()->toDateString());

    // Diproses urut waktu input: o1, o2, o3, o4 → A, B, A, B
    expect(array_map(fn ($b) => $b['order']->id, $usulan))->toBe([$o1->id, $o2->id, $o3->id, $o4->id])
        ->and(petaUsulan($usulan))->toBe([$o1->id => 'Tim A', $o2->id => 'Tim B', $o3->id => 'Tim A', $o4->id => 'Tim B']);
});

test('pembagian rata memperhitungkan job yang sudah dimiliki tim', function () {
    $service = app(OrderService::class);
    foreach ([0, 5] as $m) {
        $service->tetapkanTim(orderMasuk($this->cabang, $m), $this->timA, $this->admin);
    }

    $baru1 = orderMasuk($this->cabang, 20);
    $baru2 = orderMasuk($this->cabang, 25);
    $baru3 = orderMasuk($this->cabang, 30);

    // A sudah 2 job, B 0 job → B, B, lalu seri 2-2 → A (urut nama).
    expect(petaUsulan($this->pembagian->usulan($this->cabang->id, today()->toDateString())))
        ->toBe([$baru1->id => 'Tim B', $baru2->id => 'Tim B', $baru3->id => 'Tim A']);
});

test('semua tim sudah bergerak: pilih tim dengan job terakhir paling dekat', function () {
    $service = app(OrderService::class);

    // Tim A terakhir di Surabaya Barat, Tim B terakhir di Surabaya Timur. Keduanya sudah berangkat.
    $a1 = orderMasuk($this->cabang, 0, [-7.2650, 112.6400]);
    $a2 = orderMasuk($this->cabang, 5, [-7.2700, 112.6500]); // terakhir Tim A (barat)
    $b1 = orderMasuk($this->cabang, 10, [-7.2900, 112.7900]); // terakhir Tim B (timur)

    $service->tetapkanTim($a1, $this->timA, $this->admin);
    $service->tetapkanTim($a2, $this->timA, $this->admin);
    $service->tetapkanTim($b1, $this->timB, $this->admin);
    $service->ubahStatus($a1, StatusOrder::Berangkat, $this->admin);
    $service->ubahStatus($b1, StatusOrder::Berangkat, $this->admin);

    $dekatTimur = orderMasuk($this->cabang, 60, [-7.2950, 112.7950]);
    $dekatBarat = orderMasuk($this->cabang, 70, [-7.2720, 112.6450]);
    $tanpaLokasi = orderMasuk($this->cabang, 80);

    $usulan = collect($this->pembagian->usulan($this->cabang->id, today()->toDateString()))->keyBy(fn ($b) => $b['order']->id);

    expect($usulan[$dekatTimur->id]['tim']->nama_tim)->toBe('Tim B')
        ->and($usulan[$dekatTimur->id]['jarak_km'])->toBeLessThan(2)
        ->and($usulan[$dekatBarat->id]['tim']->nama_tim)->toBe('Tim A')
        ->and($usulan[$dekatBarat->id]['alasan'])->toContain('paling dekat')
        // Tanpa lokasi → kembali bagi rata (A 3 job, B 2 job → B).
        ->and($usulan[$tanpaLokasi->id]['tim']->nama_tim)->toBe('Tim B')
        ->and($usulan[$tanpaLokasi->id]['alasan'])->toContain('dibagi rata');
});

test('order yang ditaruh di akhir antrian menjadi titik acuan berikutnya', function () {
    $service = app(OrderService::class);
    $a = orderMasuk($this->cabang, 0, [-7.25, 112.60]);
    $b = orderMasuk($this->cabang, 1, [-7.25, 112.80]);
    $service->tetapkanTim($a, $this->timA, $this->admin);
    $service->tetapkanTim($b, $this->timB, $this->admin);
    $service->ubahStatus($a, StatusOrder::Berangkat, $this->admin);
    $service->ubahStatus($b, StatusOrder::Berangkat, $this->admin);

    // Order pertama di tengah agak ke barat → Tim A, lalu posisi akhir Tim A pindah ke 112.69.
    $baru1 = orderMasuk($this->cabang, 10, [-7.25, 112.69]);
    // Order kedua di 112.74: lebih dekat ke posisi akhir Tim A yang baru (112.69) daripada Tim B (112.80).
    $baru2 = orderMasuk($this->cabang, 11, [-7.25, 112.74]);

    expect(petaUsulan($this->pembagian->usulan($this->cabang->id, today()->toDateString())))
        ->toBe([$baru1->id => 'Tim A', $baru2->id => 'Tim A']);
});

test('order batal, tanggal lain, dan cabang lain tidak ikut dibagi', function () {
    orderMasuk($this->cabang, 0, timpa: ['status' => StatusOrder::Batal]);
    orderMasuk($this->cabang, 0, timpa: ['tgl_jadwal' => today()->addDay()->toDateString()]);
    sebagaiSistem(fn () => orderMasuk(Cabang::factory()->create(), 0));
    $ikut = orderMasuk($this->cabang, 0);

    expect(petaUsulan($this->pembagian->usulan($this->cabang->id, today()->toDateString())))->toBe([$ikut->id => 'Tim A']);
});

test('terapkan menugaskan tim dan mengirim notifikasi ke anggota tim', function () {
    Notification::fake();
    $o1 = orderMasuk($this->cabang, 0);
    $o2 = orderMasuk($this->cabang, 5);

    $jumlah = $this->pembagian->terapkan([$o1->id => $this->timA->id, $o2->id => $this->timA->id], $this->admin);

    expect($jumlah)->toBe(2)->and($o1->fresh()->tim_id)->toBe($this->timA->id);

    $anggotaA = $this->timA->anggota()->with(['teknisi' => fn ($q) => $q->withoutGlobalScopes()])->get()->map(fn ($a) => $a->teknisi->user_id);
    foreach ($anggotaA as $userId) {
        Notification::assertSentTo(User::find($userId), JobBaruDitugaskan::class, fn ($n) => $n->orderIds === [$o1->id, $o2->id]);
    }

    $anggotaB = $this->timB->anggota()->with(['teknisi' => fn ($q) => $q->withoutGlobalScopes()])->get()->map(fn ($a) => User::find($a->teknisi->user_id));
    Notification::assertNotSentTo($anggotaB, JobBaruDitugaskan::class);
});

test('gagal kirim push tidak membatalkan penugasan', function () {
    Notification::shouldReceive('send')->andThrow(new RuntimeException('FCM tidak bisa dihubungi'));
    $o = orderMasuk($this->cabang, 0);

    expect($this->pembagian->terapkan([$o->id => $this->timA->id], $this->admin))->toBe(1)
        ->and($o->fresh()->tim_id)->toBe($this->timA->id);
});

test('isi pesan push notification', function () {
    $o = orderMasuk($this->cabang, 0);
    $user = User::factory()->teknisi($this->cabang)->create();

    $satu = (new JobBaruDitugaskan([$o->id]))->toWebPush($user, new JobBaruDitugaskan([$o->id]))->toArray();
    $banyak = (new JobBaruDitugaskan([1, 2, 3]))->toWebPush($user, new JobBaruDitugaskan([1, 2, 3]))->toArray();

    expect($satu['title'])->toContain($o->pelanggan->nama)
        ->and($satu['data']['url'])->toBe("/teknisi/job/{$o->id}")
        ->and($banyak['title'])->toBe('3 job baru untuk tim Anda');
});
