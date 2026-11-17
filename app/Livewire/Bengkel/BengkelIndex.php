<?php

namespace App\Livewire\Bengkel;

use App\Enums\StatusOrder;
use App\Enums\TipeOrder;
use App\Models\OrderServis;
use App\Support\KonteksCabang;
use App\Support\PengaturanBengkel;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Order Bengkel')]
class BengkelIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $cari = '';

    /** proses | belum_diambil | semua | atau nilai status */
    #[Url(except: 'proses')]
    public string $tampil = 'proses';

    public function mount(): void
    {
        $this->authorize('viewAny', OrderServis::class);
    }

    public function updating($properti): void
    {
        if (in_array($properti, ['cari', 'tampil'], true)) {
            $this->resetPage();
        }
    }

    /** Batas hari ambil: per cabang admin, atau cabang filter owner, atau default global. */
    protected function batasHari(): int
    {
        $cabang = KonteksCabang::idPembatas();

        return $cabang ? PengaturanBengkel::batasHariAmbil($cabang) : PengaturanBengkel::batasHariAmbil(0);
    }

    public function render()
    {
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $cari = trim($this->cari);
        $batas = $this->batasHari();
        $final = [StatusOrder::Diambil->value, StatusOrder::Dikembalikan->value];

        $query = OrderServis::query()->bengkel();

        match ($this->tampil) {
            'proses' => $query->whereNotIn('status', $final),
            'belum_diambil' => $query->belumDiambilLewat($batas),
            'semua' => null,
            default => $query->where('status', $this->tampil),
        };

        $daftar = $query
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('no_order', $like, "%{$cari}%")
                ->orWhereHas('pelanggan', fn ($p) => $p->where('nama', $like, "%{$cari}%")->orWhere('hp', $like, "%{$cari}%"))))
            ->with(['pelanggan:id,nama,hp', 'unit.jenisPerangkat:id,nama', 'penerimaan:id,order_id,lokasi_simpan', 'teknisi.teknisi:id,nama'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.bengkel.bengkel-index', [
            'daftar' => $daftar,
            'batasHari' => $batas,
            'jumlahBelumDiambil' => OrderServis::query()->belumDiambilLewat($batas)->count(),
            'pilihanStatus' => StatusOrder::untukTipe(TipeOrder::Bengkel),
            'linkPengingat' => fn (OrderServis $o) => WhatsApp::link($o->pelanggan?->hp, implode("\n", [
                "Halo {$o->pelanggan?->nama},",
                "Servis {$o->unit?->jenisPerangkat?->nama} {$o->unit?->merk} (No. {$o->no_order}) sudah selesai sejak ".tgl($o->selesai_at).'.',
                'Silakan diambil di toko kami. Terima kasih.',
            ])),
        ]);
    }
}
