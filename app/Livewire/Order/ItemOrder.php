<?php

namespace App\Livewire\Order;

use App\Enums\JenisItem;
use App\Enums\TipeLayanan;
use App\Models\Barang;
use App\Models\Layanan;
use App\Models\OrderServis;
use App\Services\OrderService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Daftar item (layanan & barang) sebuah order + total. Dipakai di detail order admin dan halaman job teknisi.
 * Harga mengikuti master (harga layanan per cabang / harga jual barang); hanya admin yang boleh mengubah harga.
 */
class ItemOrder extends Component
{
    #[Locked]
    public int $orderId;

    public bool $mobile = false;

    public string $jenis = 'layanan';

    public ?int $sumber_id = null;

    public int|float|string $qty = 1;

    public int|string|null $harga = null;

    public int|string $diskon = 0;

    public function mount(int $orderId, bool $mobile = false): void
    {
        $this->orderId = $orderId;
        $this->mobile = $mobile;
        $this->diskon = $this->order()->diskon;
    }

    protected function order(): OrderServis
    {
        $order = OrderServis::query()->findOrFail($this->orderId);
        $this->authorize('view', $order);

        return $order;
    }

    public function updatedJenis(): void
    {
        $this->reset('sumber_id', 'harga');
    }

    public function tambah(OrderService $service): void
    {
        $order = $this->order();
        $this->authorize('kerjakan', $order);

        $this->validate([
            'jenis' => ['required', Rule::enum(JenisItem::class)],
            'sumber_id' => ['required', 'integer'],
            'qty' => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'harga' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
        ], attributes: ['sumber_id' => $this->jenis === 'barang' ? 'barang' : 'layanan', 'qty' => 'jumlah']);

        // Teknisi tidak boleh menentukan harga sendiri.
        $harga = auth()->user()->can('aturPenugasan', $order) && filled($this->harga) ? (int) $this->harga : null;

        $service->tambahItem($order, JenisItem::from($this->jenis), (int) $this->sumber_id, (float) $this->qty, $harga, auth()->user());

        $this->reset('sumber_id', 'qty', 'harga');
        $this->dispatch('order-berubah');
    }

    public function hapus(int $itemId, OrderService $service): void
    {
        $order = $this->order();
        $this->authorize('kerjakan', $order);

        // Biaya tetap (mis. biaya cek) hanya boleh dihapus admin.
        $item = $order->item()->findOrFail($itemId);
        if ($item->jenis === JenisItem::Biaya) {
            $this->authorize('aturPenugasan', $order);
        }

        $service->hapusItem($order, $itemId);
        $this->dispatch('order-berubah');
    }

    public function simpanDiskon(OrderService $service): void
    {
        $order = $this->order();
        $this->authorize('aturPenugasan', $order);

        $this->validate(['diskon' => ['required', 'integer', 'min:0']]);
        $service->ubahDiskon($order, (int) $this->diskon);
        $this->dispatch('order-berubah');
    }

    public function render()
    {
        $order = $this->order()->load(['item', 'unit']);
        $bolehUbah = ! $order->terkunci() && auth()->user()->can('kerjakan', $order);

        $pilihan = collect();
        if ($bolehUbah) {
            $pilihan = $this->jenis === JenisItem::Barang->value
                ? Barang::query()->aktif()->orderBy('nama')->get(['id', 'nama', 'satuan', 'harga_jual'])
                    ->map(fn ($b) => ['id' => $b->id, 'label' => "{$b->nama} ({$b->satuan}) — ".rupiah($b->harga_jual)])
                : Layanan::query()->aktif()
                    ->where('tipe', '!=', $order->tipe->value === 'kunjungan' ? TipeLayanan::Bengkel->value : TipeLayanan::Kunjungan->value)
                    ->where('jenis_perangkat_id', $order->unit?->jenis_perangkat_id)
                    ->with(['harga' => fn ($q) => $q->withoutGlobalScopes()->where('cabang_id', $order->cabang_id)])
                    ->orderBy('nama')->get()
                    ->map(fn ($l) => ['id' => $l->id, 'label' => $l->nama.' — '.(($h = $l->harga->first()?->harga) !== null ? rupiah($h) : 'harga belum diatur')]);
        }

        return view('livewire.order.item-order', [
            'order' => $order,
            'bolehUbah' => $bolehUbah,
            'bolehAturHarga' => auth()->user()->can('aturPenugasan', $order),
            'pilihan' => $pilihan,
        ]);
    }
}
