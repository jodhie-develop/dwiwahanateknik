<?php

namespace App\Livewire\Order;

use App\Enums\StatusOrder;
use App\Enums\TipeOrder;
use App\Models\OrderServis;
use App\Models\Tim;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Order')]
class OrderIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $tanggal = '';

    #[Url(except: '')]
    public string $status = '';

    /** '' = semua, 'belum' = belum dibagi, atau id tim */
    #[Url(except: '')]
    public string $tim = '';

    public function mount(): void
    {
        $this->authorize('viewAny', OrderServis::class);
    }

    public function updating($properti): void
    {
        if (in_array($properti, ['cari', 'tanggal', 'status', 'tim'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $cari = trim($this->cari);

        $daftar = OrderServis::query()
            ->kunjungan()
            ->when($this->tanggal !== '', fn ($q) => $q->padaTanggal($this->tanggal))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->tim === 'belum', fn ($q) => $q->whereNull('tim_id'))
            ->when(ctype_digit($this->tim), fn ($q) => $q->where('tim_id', $this->tim))
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('no_order', $like, "%{$cari}%")
                ->orWhereHas('pelanggan', fn ($p) => $p->where('nama', $like, "%{$cari}%")->orWhere('hp', $like, "%{$cari}%"))))
            ->with(['pelanggan:id,nama,alamat', 'unit.jenisPerangkat:id,nama', 'tim:id,nama_tim', 'cabang:id,kode'])
            ->orderByDesc('tgl_jadwal')
            ->urutAntrian()
            ->paginate(20);

        return view('livewire.order.order-index', [
            'daftar' => $daftar,
            'pilihanStatus' => StatusOrder::untukTipe(TipeOrder::Kunjungan),
            'pilihanTim' => Tim::query()->aktif()->orderBy('nama_tim')->get(['id', 'nama_tim']),
        ]);
    }
}
