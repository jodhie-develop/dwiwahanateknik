<?php

namespace App\Livewire\Bengkel;

use App\Enums\BagianTeknisi;
use App\Enums\PeranTim;
use App\Enums\StatusOrder;
use App\Enums\TipeOrder;
use App\Models\OrderServis;
use App\Models\Teknisi;
use App\Models\User;
use App\Notifications\JobBaruDitugaskan;
use App\Services\OrderService;
use App\Support\KirimNotifikasi;
use App\Support\WhatsApp;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

class BengkelDetail extends Component
{
    public OrderServis $order;

    /** @var array<int|string, string> teknisi_id => peran ('' = tidak ikut) */
    public array $pelaksana = [];

    public string $lokasi_simpan = '';

    public function mount(OrderServis $order): void
    {
        abort_unless($order->tipe === TipeOrder::Bengkel, 404);
        $this->authorize('view', $order);
        $this->order = $order;
        $this->isiForm();
    }

    #[On('order-berubah')]
    public function segarkan(): void
    {
        $this->order->refresh();
    }

    protected function isiForm(): void
    {
        $this->pelaksana = $this->order->teknisi()->pluck('peran', 'teknisi_id')
            ->map(fn ($p) => $p instanceof PeranTim ? $p->value : (string) $p)->all();
        $this->lokasi_simpan = (string) $this->order->penerimaan?->lokasi_simpan;
    }

    public function ubahStatus(string $ke, OrderService $service): void
    {
        $tujuan = StatusOrder::from($ke);
        $this->authorize('ubahStatus', [$this->order, $tujuan]);

        $service->ubahStatus($this->order, $tujuan, auth()->user());
        $this->order->refresh();
        Flux::toast(variant: 'success', text: "Status menjadi {$tujuan->label()}.");
    }

    public function simpanPelaksana(OrderService $service): void
    {
        $this->authorize('aturPenugasan', $this->order);

        $lama = $this->order->teknisi()->pluck('teknisi_id')->all();
        $baru = array_filter($this->pelaksana, fn ($peran) => in_array($peran, PeranTim::values(), true));
        $service->aturTeknisi($this->order, $baru, auth()->user());
        $this->order->refresh();
        $this->isiForm();

        $tambahan = Teknisi::query()->whereIn('id', array_diff(array_keys($baru), $lama))->pluck('user_id')->filter();
        $users = User::query()->with('pushSubscriptions')->whereIn('id', $tambahan)->get();
        if ($users->isNotEmpty()) {
            KirimNotifikasi::aman($users, new JobBaruDitugaskan([$this->order->id]));
        }

        Flux::toast(variant: 'success', text: 'Teknisi disimpan.');
    }

    public function simpanLokasi(OrderService $service): void
    {
        $this->authorize('update', $this->order);
        $this->validate(['lokasi_simpan' => ['nullable', 'string', 'max:100']]);

        $service->ubahPenerimaan($this->order, ['lokasi_simpan' => $this->lokasi_simpan ?: null]);
        $this->order->refresh();
        Flux::toast(variant: 'success', text: 'Lokasi simpan diperbarui.');
    }

    public function render()
    {
        $o = $this->order->load([
            'pelanggan', 'unit.jenisPerangkat', 'penerimaan.penerima:id,nama', 'pembuat:id,nama', 'cabang',
            'teknisi.teknisi:id,nama', 'log.user:id,nama', 'estimasiTerakhir',
        ]);

        // Tombol status manual: menunggu_konfirmasi & batal diatur lewat panel estimasi.
        $tujuan = collect($o->status->tujuanSah($o->tipe))
            ->reject(fn ($s) => in_array($s, [StatusOrder::MenungguKonfirmasi, StatusOrder::Batal], true))
            ->reject(fn ($s) => $o->status === StatusOrder::MenungguKonfirmasi && $s === StatusOrder::Dikerjakan)
            ->filter(fn ($s) => auth()->user()->can('ubahStatus', [$o, $s]));

        $pesanTandaTerima = implode("\n", array_filter([
            "Halo {$o->pelanggan?->nama}, terima kasih telah servis di {$o->cabang?->nama}.",
            "No. servis: {$o->no_order}",
            'Barang: '.trim("{$o->unit?->jenisPerangkat?->nama} {$o->unit?->merk} {$o->unit?->model}"),
            "Keluhan: {$o->keluhan}",
            'Kelengkapan: '.($o->penerimaan?->kelengkapanTeks() ?? '-'),
            $o->penerimaan?->kondisi_fisik ? "Kondisi: {$o->penerimaan->kondisi_fisik}" : null,
            'Tanggal terima: '.tgl($o->created_at),
            'Simpan nomor servis ini untuk pengambilan barang.',
        ]));

        return view('livewire.bengkel.bengkel-detail', [
            'o' => $o,
            'tujuanStatus' => $tujuan,
            'bisaAturTeknisi' => ! $o->terkunci() && auth()->user()->can('aturPenugasan', $o),
            'pilihanTeknisi' => Teknisi::query()->aktif()->where('cabang_id', $o->cabang_id)
                ->whereIn('bagian', [BagianTeknisi::Bengkel->value, BagianTeknisi::Keduanya->value])
                ->orderBy('nama')->get(['id', 'nama']),
            'linkWaTandaTerima' => WhatsApp::link($o->pelanggan?->hp, $pesanTandaTerima),
        ])->title($o->no_order);
    }
}
