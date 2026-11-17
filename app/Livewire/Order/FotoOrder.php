<?php

namespace App\Livewire\Order;

use App\Enums\KategoriFoto;
use App\Models\OrderServis;
use App\Services\OrderService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Upload & galeri foto order (sebelum/sesudah/lainnya). Dipakai di admin dan halaman teknisi. */
class FotoOrder extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $orderId;

    public string $kategori = 'sebelum';

    /** @var array<int, TemporaryUploadedFile> */
    public array $berkas = [];

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

    /** Upload langsung setelah foto dipilih/diambil dari kamera. */
    public function updatedBerkas(OrderService $service): void
    {
        $order = $this->order();
        $this->authorize('kerjakan', $order);

        $this->validate([
            'kategori' => ['required', Rule::enum(KategoriFoto::class)],
            'berkas' => ['array', 'max:10'],
            'berkas.*' => ['image', 'max:8192'],
        ], attributes: ['berkas.*' => 'foto']);

        foreach ($this->berkas as $file) {
            $service->tambahFoto($order, $file, KategoriFoto::from($this->kategori), auth()->user());
        }

        $this->reset('berkas');
    }

    public function hapus(int $fotoId, OrderService $service): void
    {
        $order = $this->order();
        $this->authorize('kerjakan', $order);
        $service->hapusFoto($order, $fotoId);
    }

    public function render()
    {
        $order = $this->order()->load('foto');

        return view('livewire.order.foto-order', [
            'order' => $order,
            'bolehUbah' => ! $order->terkunci() && auth()->user()->can('kerjakan', $order),
            'perKategori' => $order->foto->groupBy(fn ($f) => $f->kategori->value),
            'pilihanKategori' => collect(KategoriFoto::cases())->reject(fn ($k) => $k === KategoriFoto::Penerimaan),
        ]);
    }
}
