<?php

namespace App\Livewire\Teknisi;

use App\Enums\StatusOrder;
use App\Enums\TipeOrder;
use App\Models\OrderServis;
use App\Services\OrderService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/** Halaman satu job untuk teknisi di lapangan (mobile). */
#[Layout('components.layouts.teknisi')]
class JobDetail extends Component
{
    public OrderServis $order;

    public function mount(OrderServis $order): void
    {
        // Route model binding sudah melewati JobTeknisiScope; policy sebagai lapis kedua.
        $this->authorize('view', $order);
        $this->order = $order;
    }

    #[On('order-berubah')]
    public function segarkan(): void
    {
        $this->order->refresh();
    }

    public function ubahStatus(string $ke, OrderService $service): void
    {
        $tujuan = StatusOrder::from($ke);
        $this->authorize('ubahStatus', [$this->order, $tujuan]);

        $service->ubahStatus($this->order, $tujuan, auth()->user());
        $this->order->refresh();
        Flux::toast(variant: 'success', text: "Status: {$tujuan->label()}");
    }

    public function checkin(float $lat, float $lng, OrderService $service): void
    {
        $this->authorize('kerjakan', $this->order);
        $service->checkin($this->order, $lat, $lng);
        $this->order->refresh();
        Flux::toast(variant: 'success', text: 'Check-in tersimpan.');
    }

    public function simpanTandaTangan(string $dataUrl, OrderService $service): void
    {
        $this->authorize('kerjakan', $this->order);
        $service->simpanTandaTangan($this->order, $dataUrl);
        $this->order->refresh();
        Flux::modal('tanda-tangan')->close();
        Flux::toast(variant: 'success', text: 'Tanda tangan tersimpan.');
    }

    public function render()
    {
        $o = $this->order->load(['pelanggan', 'unit.jenisPerangkat', 'unit.pelanggan', 'tim:id,nama_tim', 'teknisi.teknisi:id,nama', 'penerimaan']);

        // Untuk bengkel, menunggu konfirmasi & persetujuan diatur lewat panel estimasi.
        $tujuan = collect($o->status->tujuanSah($o->tipe))
            ->reject(fn ($s) => $s === StatusOrder::MenungguKonfirmasi)
            ->reject(fn ($s) => $o->status === StatusOrder::MenungguKonfirmasi && $s === StatusOrder::Dikerjakan)
            ->filter(fn ($s) => auth()->user()->can('ubahStatus', [$o, $s]));

        return view('livewire.teknisi.job-detail', [
            'tujuanStatus' => $tujuan,
            'koordinat' => $o->koordinat(),
            'bengkel' => $o->tipe === TipeOrder::Bengkel,
        ])->title($o->pelanggan?->nama ?? 'Job');
    }
}
