<?php

namespace App\Livewire\Order;

use App\Enums\PeranTim;
use App\Enums\StatusOrder;
use App\Enums\TipeOrder;
use App\Models\OrderServis;
use App\Models\Teknisi;
use App\Models\Tim;
use App\Models\User;
use App\Notifications\JobBaruDitugaskan;
use App\Services\OrderService;
use App\Support\KirimNotifikasi;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderDetail extends Component
{
    public OrderServis $order;

    public string $alasanBatal = '';

    public ?int $tim_id = null;

    /** @var array<int|string, string> teknisi_id => peran ('' = tidak ikut) */
    public array $pelaksana = [];

    public function mount(OrderServis $order): void
    {
        $this->authorize('view', $order);

        if ($order->tipe === TipeOrder::Bengkel) {
            $this->redirectRoute('bengkel.detail', $order, navigate: true);

            return;
        }

        $this->order = $order;
        $this->isiPenugasan();
    }

    #[On('order-berubah')]
    public function segarkan(): void
    {
        $this->order->refresh();
    }

    protected function isiPenugasan(): void
    {
        $this->tim_id = $this->order->tim_id;
        $this->pelaksana = $this->order->teknisi()->pluck('peran', 'teknisi_id')
            ->map(fn ($p) => $p instanceof PeranTim ? $p->value : (string) $p)->all();
    }

    public function ubahStatus(string $ke, OrderService $service): void
    {
        $tujuan = StatusOrder::from($ke);
        $this->authorize('ubahStatus', [$this->order, $tujuan]);

        $service->ubahStatus($this->order, $tujuan, auth()->user());
        $this->order->refresh();
        Flux::toast(variant: 'success', text: "Status menjadi {$tujuan->label()}.");
    }

    public function batalkan(OrderService $service): void
    {
        $this->authorize('ubahStatus', [$this->order, StatusOrder::Batal]);
        $this->validate(['alasanBatal' => ['required', 'string', 'max:500']], attributes: ['alasanBatal' => 'alasan']);

        $service->ubahStatus($this->order, StatusOrder::Batal, auth()->user(), $this->alasanBatal);
        $this->order->refresh();
        $this->reset('alasanBatal');
        Flux::modal('batal-order')->close();
        Flux::toast('Order dibatalkan.');
    }

    public function simpanTim(OrderService $service): void
    {
        $this->authorize('aturPenugasan', $this->order);
        $this->validate(['tim_id' => ['required', 'integer']], attributes: ['tim_id' => 'tim']);

        $tim = Tim::query()->findOrFail($this->tim_id);
        $userIds = $service->tetapkanTim($this->order, $tim, auth()->user());
        $this->order->refresh();
        $this->isiPenugasan();

        $this->kirimNotifikasi($userIds);
        Flux::toast(variant: 'success', text: "Ditugaskan ke {$tim->nama_tim}.");
    }

    public function simpanPelaksana(OrderService $service): void
    {
        $this->authorize('aturPenugasan', $this->order);

        $lama = $this->order->teknisi()->pluck('teknisi_id')->all();
        $baru = array_filter($this->pelaksana, fn ($peran) => in_array($peran, PeranTim::values(), true));
        $service->aturTeknisi($this->order, $baru, auth()->user());
        $this->order->refresh();
        $this->isiPenugasan();

        $tambahan = array_diff(array_keys($baru), $lama);
        $this->kirimNotifikasi(Teknisi::query()->whereIn('id', $tambahan)->pluck('user_id')->filter()->all());
        Flux::toast(variant: 'success', text: 'Teknisi pelaksana disimpan.');
    }

    /** @param  list<int>  $userIds */
    private function kirimNotifikasi(array $userIds): void
    {
        $users = User::query()->with('pushSubscriptions')->whereIn('id', $userIds)->get();
        if ($users->isNotEmpty()) {
            KirimNotifikasi::aman($users, new JobBaruDitugaskan([$this->order->id]));
        }
    }

    public function render()
    {
        $this->order->load([
            'pelanggan', 'unit.jenisPerangkat', 'tim', 'pembuat:id,nama', 'cabang:id,nama,kode',
            'teknisi.teknisi:id,nama,hp', 'log.user:id,nama',
        ]);

        $bisaAturTim = $this->order->status === StatusOrder::Dijadwalkan
            && auth()->user()->can('aturPenugasan', $this->order);

        return view('livewire.order.order-detail', [
            'tujuanStatus' => collect($this->order->status->tujuanSah($this->order->tipe))
                ->reject(fn ($s) => $s === StatusOrder::Batal),
            'bisaBatal' => $this->order->status->bolehKe(StatusOrder::Batal, $this->order->tipe)
                && auth()->user()->can('ubahStatus', [$this->order, StatusOrder::Batal]),
            'bisaAturTim' => $bisaAturTim,
            'pilihanTim' => $bisaAturTim
                ? Tim::query()->aktif()->where('cabang_id', $this->order->cabang_id)->with('anggotaAktif.teknisi:id,nama')->orderBy('nama_tim')->get()
                : collect(),
            'pilihanTeknisi' => $bisaAturTim
                ? Teknisi::query()->aktif()->where('cabang_id', $this->order->cabang_id)->orderBy('nama')->get(['id', 'nama', 'bagian'])
                : collect(),
        ])->title($this->order->no_order);
    }
}
