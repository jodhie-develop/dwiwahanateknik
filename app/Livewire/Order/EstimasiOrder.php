<?php

namespace App\Livewire\Order;

use App\Enums\MediaPersetujuan;
use App\Enums\StatusOrder;
use App\Models\OrderServis;
use App\Services\OrderService;
use App\Support\WhatsApp;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Estimasi biaya servis bengkel + pencatatan persetujuan pelanggan.
 * Dipakai di detail bengkel admin dan halaman job teknisi.
 */
class EstimasiOrder extends Component
{
    #[Locked]
    public int $orderId;

    public int|string $nominal = '';

    public string $catatan = '';

    public string $media = 'wa';

    public string $catatanKeputusan = '';

    public function mount(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    protected function order(): OrderServis
    {
        $order = OrderServis::query()->findOrFail($this->orderId);
        $this->authorize('view', $order);

        return $order;
    }

    #[On('order-berubah')]
    public function segarkan(): void
    {
        // render ulang
    }

    public function ajukan(OrderService $service): void
    {
        $order = $this->order();
        $this->authorize('kerjakan', $order);

        $this->validate([
            'nominal' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'catatan' => ['required', 'string', 'max:2000'],
        ], attributes: ['nominal' => 'nominal estimasi', 'catatan' => 'hasil diagnosa']);

        $service->ajukanEstimasi($order, (int) $this->nominal, $this->catatan, auth()->user());

        $this->reset('nominal', 'catatan');
        Flux::toast(variant: 'success', text: 'Estimasi diajukan. Hubungi pelanggan untuk konfirmasi.');
        $this->dispatch('order-berubah');
    }

    public function putuskan(bool $setuju, OrderService $service): void
    {
        $order = $this->order();
        $this->authorize('kerjakan', $order);

        $this->validate([
            'media' => ['required', Rule::enum(MediaPersetujuan::class)],
            'catatanKeputusan' => [$setuju ? 'nullable' : 'required', 'string', 'max:500'],
        ], ['catatanKeputusan.required' => 'Tulis alasan pelanggan menolak.'], ['media' => 'media konfirmasi']);

        $service->catatPersetujuan($order, $setuju, MediaPersetujuan::from($this->media), auth()->user(), $this->catatanKeputusan ?: null);

        $this->reset('catatanKeputusan');
        Flux::toast(variant: 'success', text: $setuju ? 'Disetujui. Order masuk status Dikerjakan.' : 'Ditolak. Order dibatalkan, barang siap dikembalikan.');
        $this->dispatch('order-berubah');
    }

    public function render()
    {
        $order = $this->order()->load(['estimasi.pembuat:id,nama', 'estimasi.pencatat:id,nama', 'pelanggan:id,nama,hp', 'unit.jenisPerangkat:id,nama']);
        $terakhir = $order->estimasi->sortByDesc('id')->first();
        $bolehUbah = auth()->user()->can('kerjakan', $order);

        $pesanWa = $terakhir ? implode("\n", [
            "Halo {$order->pelanggan?->nama},",
            "Hasil pengecekan servis {$order->unit?->jenisPerangkat?->nama} {$order->unit?->merk} (No. {$order->no_order}):",
            $terakhir->catatan,
            'Estimasi biaya perbaikan: '.rupiah($terakhir->nominal).'.',
            'Mohon balas SETUJU untuk dilanjutkan atau BATAL jika tidak. Terima kasih.',
        ]) : '';

        return view('livewire.order.estimasi-order', [
            'order' => $order,
            'riwayat' => $order->estimasi->sortByDesc('id'),
            'terakhir' => $terakhir,
            'bisaAjukan' => $bolehUbah && in_array($order->status, [StatusOrder::Diagnosa, StatusOrder::MenungguKonfirmasi], true),
            'bisaPutuskan' => $bolehUbah && $order->status === StatusOrder::MenungguKonfirmasi,
            'linkWa' => $terakhir ? WhatsApp::link($order->pelanggan?->hp, $pesanWa) : null,
            'pilihanMedia' => MediaPersetujuan::options(),
        ]);
    }
}
