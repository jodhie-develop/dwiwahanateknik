<?php

namespace App\Livewire;

use App\Enums\JenisItem;
use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\OrderServis;
use App\Models\Tim;
use App\Support\KonteksCabang;
use App\Support\PengaturanBengkel;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard admin.
 * - Admin cabang: monitor progres job kunjungan tiap tim (tampilan layar TV) + ringkasan bengkel.
 * - Owner: tab "Ringkasan" (KPI, jadwal tim, order terbaru, alur bengkel) dan tab "Monitor TV".
 * Data ikut dibatasi cabang (admin cabang = cabangnya; owner = filter cabang / semua).
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    public const TAMPILAN = ['ringkasan', 'monitor'];

    public const FILTER_ORDER = ['semua', 'kunjungan', 'bengkel'];

    #[Url(except: '')]
    public string $tanggal = '';

    /** Hanya berlaku untuk owner; admin cabang selalu melihat monitor. */
    #[Url(as: 'tampil', except: 'ringkasan')]
    public string $tampilan = 'ringkasan';

    public string $filterOrder = 'semua';

    public function mount(): void
    {
        $this->authorize('akses-admin');
        $this->tanggal = $this->tanggal ?: today()->toDateString();
    }

    public function geserHari(int $hari): void
    {
        $this->tanggal = now()->parse($this->hariIni())->addDays($hari)->toDateString();
    }

    protected function hariIni(): string
    {
        return $this->tanggal ?: today()->toDateString();
    }

    protected function tampilanAktif(): string
    {
        if (! auth()->user()->isOwner()) {
            return 'monitor';
        }

        return in_array($this->tampilan, self::TAMPILAN, true) ? $this->tampilan : 'ringkasan';
    }

    public function render()
    {
        $tanggal = $this->hariIni();
        $tampilan = $this->tampilanAktif();
        $cabang = KonteksCabang::idPembatas() ?? 0;

        $box = $this->boxTim($tanggal);

        $data = [
            'tampilanAktif' => $tampilan,
            'isOwner' => auth()->user()->isOwner(),
            'box' => $box,
            'ringkasJob' => $this->ringkasJob($box),
            'tanggalTampil' => $tanggal,
            'belumDibagi' => OrderServis::query()->kunjungan()->padaTanggal($tanggal)
                ->whereNull('tim_id')->where('status', StatusOrder::Dijadwalkan->value)->count(),
            'tampilCabang' => auth()->user()->isOwner() && ! KonteksCabang::filterOwner(),
            'namaCabang' => ($id = KonteksCabang::idPembatas()) ? Cabang::query()->whereKey($id)->value('nama') : null,
            'ringkasBengkel' => $this->ringkasBengkel(),
            'belumDiambilLewat' => OrderServis::query()->belumDiambilLewat(PengaturanBengkel::batasHariAmbil($cabang))->count(),
            'batasHariAmbil' => PengaturanBengkel::batasHariAmbil($cabang),
        ];

        if ($tampilan === 'ringkasan') {
            $data += $this->dataRingkasan($tanggal, $data['ringkasBengkel'], $box);
        }

        return view('livewire.dashboard', $data);
    }

    /**
     * Kartu tiap tim aktif: anggota, rekap status, dan daftar job kunjungan pada tanggal tsb.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function boxTim(string $tanggal): Collection
    {
        $daftarTim = Tim::query()->aktif()
            ->with(['cabang:id,nama', 'anggotaAktif' => fn ($q) => $q->orderByDesc('peran'), 'anggotaAktif.teknisi:id,nama'])
            ->orderBy('cabang_id')->orderBy('nama_tim')
            ->get();

        $jobPerTim = OrderServis::query()->kunjungan()->padaTanggal($tanggal)
            ->whereNotNull('tim_id')
            ->with(['pelanggan:id,nama,alamat', 'unit:id,pelanggan_id,jenis_perangkat_id,merk,alamat', 'unit.jenisPerangkat:id,nama', 'item:id,order_id,jenis,nama'])
            ->orderByRaw('jam_janji is null')->orderBy('jam_janji')
            ->urutAntrian()
            ->get()
            ->groupBy('tim_id');

        return $daftarTim->map(function (Tim $tim) use ($jobPerTim) {
            /** @var Collection<int, OrderServis> $order */
            $order = $jobPerTim->get($tim->id, collect());
            $hitung = fn (StatusOrder ...$s) => $order->filter(fn (OrderServis $o) => in_array($o->status, $s, true))->count();

            $batal = $hitung(StatusOrder::Batal);
            $total = $order->count() - $batal;
            $selesai = $hitung(StatusOrder::Selesai);

            $jobs = $order->reject(fn (OrderServis $o) => $o->status === StatusOrder::Batal)
                ->map(fn (OrderServis $o) => $this->ringkasOrder($o))
                ->values();

            return [
                'tim' => $tim,
                'total' => $total,
                'selesai' => $selesai,
                'berangkat' => $hitung(StatusOrder::Berangkat),
                'dikerjakan' => $hitung(StatusOrder::Dikerjakan),
                'berjalan' => $hitung(StatusOrder::Berangkat, StatusOrder::Dikerjakan),
                'terjadwal' => $hitung(StatusOrder::Dijadwalkan),
                'batal' => $batal,
                'persen' => $total > 0 ? (int) round($selesai / $total * 100) : 0,
                'jobs' => $jobs,
                // Job yang sedang ditangani (dikerjakan didahulukan dari berangkat).
                'aktif' => $jobs->firstWhere('status', StatusOrder::Dikerjakan) ?? $jobs->firstWhere('status', StatusOrder::Berangkat),
            ];
        });
    }

    /** @return array<string, mixed> */
    protected function ringkasOrder(OrderServis $o): array
    {
        $layanan = $o->item->where('jenis', JenisItem::Layanan)->pluck('nama')->filter()->unique();
        $perangkat = trim(($o->unit?->jenisPerangkat?->nama ?? '').' '.($o->unit?->merk ?? ''));

        return [
            'order' => $o,
            'jam' => $o->jam_janji ? substr((string) $o->jam_janji, 0, 5) : null,
            'mulai' => $o->checkin_at?->timezone(config('app.timezone'))->format('H:i'),
            'kerja' => $layanan->isNotEmpty() ? $layanan->implode(', ') : ($o->keluhan ?: ($perangkat ?: 'Servis')),
            'tempat' => collect([$o->pelanggan?->nama, $o->unit?->alamat ?: $o->pelanggan?->alamat])->filter()->implode(' · '),
            'status' => $o->status,
        ];
    }

    /** @return array<string, int> */
    protected function ringkasJob(Collection $box): array
    {
        $r = [
            'tim' => $box->count(),
            'total' => $box->sum('total'),
            'selesai' => $box->sum('selesai'),
            'dikerjakan' => $box->sum('dikerjakan'),
            'berangkat' => $box->sum('berangkat'),
            'terjadwal' => $box->sum('terjadwal'),
        ];
        $r['persen'] = $r['total'] > 0 ? (int) round($r['selesai'] / $r['total'] * 100) : 0;
        $r['lebarSelesai'] = $r['total'] > 0 ? round($r['selesai'] / $r['total'] * 100, 1) : 0;
        $r['lebarJalan'] = $r['total'] > 0 ? round(($r['dikerjakan'] + $r['berangkat']) / $r['total'] * 100, 1) : 0;

        return $r;
    }

    /** Barang yang masih ada di cabang, per status. */
    protected function ringkasBengkel(): Collection
    {
        $perStatus = OrderServis::query()->bengkel()
            ->whereNotIn('status', [StatusOrder::Diambil->value, StatusOrder::Dikembalikan->value])
            ->selectRaw('status, count(*) as jumlah')->groupBy('status')
            ->pluck('jumlah', 'status')
            ->mapWithKeys(fn ($j, $s) => [($s instanceof StatusOrder ? $s->value : $s) => (int) $j]);

        return collect([
            StatusOrder::Diterima, StatusOrder::Diagnosa, StatusOrder::MenungguKonfirmasi,
            StatusOrder::Dikerjakan, StatusOrder::MenungguSparepart, StatusOrder::Selesai, StatusOrder::Batal,
        ])->map(fn (StatusOrder $s) => [
            'status' => $s,
            'label' => match ($s) {
                StatusOrder::Selesai => 'Selesai, belum diambil',
                StatusOrder::Batal => 'Batal, belum dikembalikan',
                default => $s->label(),
            },
            'jumlah' => $perStatus->get($s->value, 0),
        ]);
    }

    /** Data tambahan tab Ringkasan (owner). */
    protected function dataRingkasan(string $tanggal, Collection $ringkasBengkel, Collection $box): array
    {
        $tidakBatal = [StatusOrder::Batal->value, StatusOrder::Dikembalikan->value];

        $kunjunganHariIni = OrderServis::query()->kunjungan()->padaTanggal($tanggal)->whereNotIn('status', $tidakBatal)->count();
        $bengkelHariIni = OrderServis::query()->bengkel()->whereDate('created_at', $tanggal)->whereNotIn('status', $tidakBatal)->count();

        // Alur bengkel: tiap tahap menampilkan maks. 2 barang terlama.
        $tahap = [StatusOrder::Diterima, StatusOrder::Diagnosa, StatusOrder::MenungguKonfirmasi, StatusOrder::Dikerjakan, StatusOrder::MenungguSparepart, StatusOrder::Selesai];
        $barang = OrderServis::query()->bengkel()
            ->whereIn('status', array_map(fn ($s) => $s->value, $tahap))
            ->with(['pelanggan:id,nama', 'unit:id,jenis_perangkat_id,merk,model', 'unit.jenisPerangkat:id,nama'])
            ->urutAntrian()
            ->get()
            ->groupBy(fn (OrderServis $o) => $o->status->value);

        $alurBengkel = collect($tahap)->map(fn (StatusOrder $s) => [
            'status' => $s,
            'label' => $s === StatusOrder::Selesai ? 'Siap diambil' : $s->label(),
            'jumlah' => $ringkasBengkel->firstWhere('status', $s)['jumlah'] ?? 0,
            'unit' => $barang->get($s->value, collect())->take(2),
        ]);

        $orderTerbaru = OrderServis::query()
            ->when($this->filterOrder === 'kunjungan', fn ($q) => $q->kunjungan())
            ->when($this->filterOrder === 'bengkel', fn ($q) => $q->bengkel())
            ->with([
                'pelanggan:id,nama', 'tim:id,nama_tim', 'cabang:id,kode',
                'item:id,order_id,jenis,nama', 'unit:id,jenis_perangkat_id,merk', 'unit.jenisPerangkat:id,nama',
                'teknisi:id,order_id,teknisi_id', 'teknisi.teknisi:id,nama',
            ])
            ->latest('created_at')->latest('id')
            ->limit(8)
            ->get();

        return [
            'kpi' => [
                'kunjungan' => $kunjunganHariIni,
                'bengkelMasuk' => $bengkelHariIni,
                'timJalan' => $box->where('berjalan', '>', 0)->count(),
                'timTuntas' => $box->filter(fn ($b) => $b['total'] > 0 && $b['selesai'] === $b['total'])->count(),
                'timBelumMulai' => $box->filter(fn ($b) => $b['total'] > 0 && $b['berjalan'] === 0 && $b['selesai'] === 0)->count(),
                'unitBengkel' => $ringkasBengkel->sum('jumlah'),
                'sparepart' => $ringkasBengkel->firstWhere('status', StatusOrder::MenungguSparepart)['jumlah'] ?? 0,
            ],
            'alurBengkel' => $alurBengkel,
            'orderTerbaru' => $orderTerbaru,
        ];
    }
}
