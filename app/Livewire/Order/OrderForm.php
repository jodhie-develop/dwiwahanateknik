<?php

namespace App\Livewire\Order;

use App\Enums\StatusOrder;
use App\Enums\TipeLayanan;
use App\Enums\TipeOrder;
use App\Livewire\Concerns\IsiKoordinat;
use App\Livewire\Concerns\PilihPelangganUnit;
use App\Models\Cabang;
use App\Models\Layanan;
use App\Models\OrderServis;
use App\Services\OrderService;
use App\Support\KonteksCabang;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Input order kunjungan baru (dan ubah data dasarnya selama masih Dijadwalkan).
 * Pelanggan & unit baru bisa dibuat langsung dari form ini.
 */
#[Title('Order Kunjungan')]
class OrderForm extends Component
{
    use IsiKoordinat, PilihPelangganUnit;

    public ?OrderServis $order = null;

    public ?int $cabang_id = null;

    public string $keluhan = '';

    public string $tgl_jadwal = '';

    public string $jam_janji = '';

    public string $catatan = '';

    /** @var list<array{layanan_id: int|string|null, qty: int|string}> */
    public array $layanan = [];

    public function mount(?OrderServis $order = null): void
    {
        if ($order?->exists) {
            $this->authorize('update', $order);
            abort_unless($order->tipe === TipeOrder::Kunjungan && $order->status === StatusOrder::Dijadwalkan, 403, 'Order ini sudah tidak bisa diubah.');

            $this->order = $order;
            $this->cabang_id = $order->cabang_id;
            $this->pelanggan_id = $order->pelanggan_id;
            $this->unit_pelanggan_id = $order->unit_pelanggan_id;
            $this->keluhan = $order->keluhan;
            $this->tgl_jadwal = $order->tgl_jadwal->toDateString();
            $this->jam_janji = $order->jam_janji ? substr($order->jam_janji, 0, 5) : '';
            $this->catatan = (string) $order->catatan;

            return;
        }

        $this->authorize('create', OrderServis::class);
        $this->cabang_id = KonteksCabang::idPembatas() ?: Cabang::query()->aktif()->value('id');
        $this->tgl_jadwal = today()->toDateString();
        $this->layanan = [['layanan_id' => null, 'qty' => 1]];

        if ($id = request()->integer('pelanggan')) {
            $this->pilihPelanggan($id);
        }
    }

    public function tambahBarisLayanan(): void
    {
        $this->layanan[] = ['layanan_id' => null, 'qty' => 1];
    }

    public function hapusBarisLayanan(int $i): void
    {
        unset($this->layanan[$i]);
        $this->layanan = array_values($this->layanan);
    }

    public function simpan(OrderService $service): void
    {
        $saya = auth()->user();

        if (! $saya->isOwner()) {
            $this->cabang_id = $saya->cabang_id;
        }

        if ($this->pelangganBaru && ! $this->prosesLinkMapsTertunda()) {
            return;
        }

        $this->pb_hp = preg_replace('/[^0-9+]/', '', $this->pb_hp);

        $data = $this->validate([
            'cabang_id' => ['required', 'integer', Rule::exists('cabang', 'id')->whereNull('deleted_at')],
            ...$this->aturanPelangganUnit(),
            'keluhan' => ['required', 'string', 'max:2000'],
            'tgl_jadwal' => array_filter(['required', 'date', $this->order ? null : 'after_or_equal:today']),
            'jam_janji' => ['nullable', 'date_format:H:i'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'layanan' => ['array'],
            'layanan.*.layanan_id' => ['nullable', 'integer', Rule::exists('layanan', 'id')->where('aktif', true)->whereNull('deleted_at')],
            'layanan.*.qty' => ['required_with:layanan.*.layanan_id', 'nullable', 'numeric', 'min:0.01', 'max:999'],
        ], [
            ...$this->pesanPelangganUnit(),
            'tgl_jadwal.after_or_equal' => 'Tanggal jadwal tidak boleh sebelum hari ini.',
        ], [
            ...$this->atributPelangganUnit(),
            'cabang_id' => 'cabang', 'tgl_jadwal' => 'tanggal jadwal', 'jam_janji' => 'jam janji',
            'layanan.*.layanan_id' => 'layanan', 'layanan.*.qty' => 'jumlah',
        ]);

        $order = DB::transaction(function () use ($data, $service, $saya) {
            [$pelangganId, $unitId] = $this->simpanPelangganUnit($data);

            $kolom = [
                'cabang_id' => $data['cabang_id'],
                'tipe' => TipeOrder::Kunjungan->value,
                'pelanggan_id' => $pelangganId,
                'unit_pelanggan_id' => $unitId,
                'keluhan' => $data['keluhan'],
                'tgl_jadwal' => $data['tgl_jadwal'],
                'jam_janji' => $data['jam_janji'] ?: null,
                'catatan' => $data['catatan'] ?: null,
            ];

            if ($this->order) {
                return $service->ubahData($this->order, $kolom, $saya);
            }

            $layanan = collect($data['layanan'])->filter(fn ($b) => filled($b['layanan_id']))
                ->map(fn ($b) => ['layanan_id' => (int) $b['layanan_id'], 'qty' => (float) $b['qty']])->values()->all();

            return $service->buat($kolom, $saya, $layanan);
        });

        session()->flash('sukses', $this->order ? 'Order diperbarui.' : "Order {$order->no_order} dibuat.");
        $this->redirectRoute('order.detail', $order, navigate: true);
    }

    public function render()
    {
        $dataPU = $this->dataPelangganUnit();
        $jenisUnit = $dataPU['jenisUnitTerpilih'];

        $pilihanLayanan = Layanan::query()->aktif()
            ->whereIn('tipe', [TipeLayanan::Kunjungan->value, TipeLayanan::Keduanya->value])
            ->when($jenisUnit, fn ($q) => $q->where('jenis_perangkat_id', $jenisUnit))
            ->with(['jenisPerangkat:id,nama', 'harga' => fn ($q) => $q->withoutGlobalScopes()->where('cabang_id', $this->cabang_id ?? 0)])
            ->orderBy('nama')
            ->get();

        return view('livewire.order.order-form', [
            ...$dataPU,
            'pilihanLayanan' => $pilihanLayanan,
            'pilihanCabang' => Cabang::query()->aktif()->orderBy('nama')->get(['id', 'nama']),
        ]);
    }
}
