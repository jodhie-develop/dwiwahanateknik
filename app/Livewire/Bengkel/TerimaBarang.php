<?php

namespace App\Livewire\Bengkel;

use App\Enums\BagianTeknisi;
use App\Enums\KategoriFoto;
use App\Livewire\Concerns\IsiKoordinat;
use App\Livewire\Concerns\PilihPelangganUnit;
use App\Models\Cabang;
use App\Models\OrderServis;
use App\Models\Teknisi;
use App\Models\User;
use App\Notifications\JobBaruDitugaskan;
use App\Services\OrderService;
use App\Support\KirimNotifikasi;
use App\Support\KonteksCabang;
use App\Support\PengaturanBengkel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Terima barang servis bengkel + tanda terima. */
#[Title('Terima Barang Servis')]
class TerimaBarang extends Component
{
    use IsiKoordinat, PilihPelangganUnit, WithFileUploads;

    /** Pilihan kelengkapan yang sering dibawa pelanggan. */
    public const KELENGKAPAN_UMUM = ['Remote', 'Kabel power', 'Adaptor / charger', 'Dus / kardus', 'Kaki / stand', 'Selang'];

    public ?int $cabang_id = null;

    public string $keluhan = '';

    public string $kondisi_fisik = '';

    /** @var list<string> */
    public array $kelengkapan = [];

    public string $kelengkapan_lain = '';

    public string $catatan_kerusakan = '';

    public string $lokasi_simpan = '';

    public string $catatan = '';

    /** @var list<int|string> */
    public array $teknisi = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $foto = [];

    /** Satu foto per upload (lihat komponen x-unggah-foto), lalu dipindah ke $foto. */
    public ?TemporaryUploadedFile $fotoBaru = null;

    public function mount(): void
    {
        $this->authorize('create', OrderServis::class);
        $this->cabang_id = KonteksCabang::idPembatas() ?: Cabang::query()->aktif()->value('id');

        if ($id = request()->integer('pelanggan')) {
            $this->pilihPelanggan($id);
        }
    }

    public function updatedFotoBaru(): void
    {
        $this->validate(['fotoBaru' => ['required', 'image', 'max:8192']], attributes: ['fotoBaru' => 'foto']);

        if (count($this->foto) >= 10) {
            $this->addError('fotoBaru', 'Maksimal 10 foto.');
        } else {
            $this->foto[] = $this->fotoBaru;
        }

        $this->reset('fotoBaru');
    }

    public function hapusFoto(int $i): void
    {
        unset($this->foto[$i]);
        $this->foto = array_values($this->foto);
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
            ...$this->aturanPelangganUnit(alamatWajib: false),
            'keluhan' => ['required', 'string', 'max:2000'],
            'kondisi_fisik' => ['nullable', 'string', 'max:2000'],
            'kelengkapan' => ['array'],
            'kelengkapan.*' => ['string', 'max:50'],
            'kelengkapan_lain' => ['nullable', 'string', 'max:200'],
            'catatan_kerusakan' => ['nullable', 'string', 'max:2000'],
            'lokasi_simpan' => ['nullable', 'string', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'teknisi' => ['array'],
            'teknisi.*' => ['integer', Rule::exists('teknisi', 'id')->where('cabang_id', $this->cabang_id)->where('aktif', true)->whereNull('deleted_at')],
            'foto' => ['array', 'max:10'],
            'foto.*' => ['image', 'max:8192'],
        ], $this->pesanPelangganUnit(), [
            ...$this->atributPelangganUnit(),
            'cabang_id' => 'cabang', 'kondisi_fisik' => 'kondisi fisik', 'catatan_kerusakan' => 'catatan kerusakan',
            'lokasi_simpan' => 'lokasi simpan', 'teknisi.*' => 'teknisi', 'foto.*' => 'foto',
        ]);

        $kelengkapan = collect($data['kelengkapan'])
            ->merge(collect(explode(',', (string) $data['kelengkapan_lain']))->map(fn ($k) => trim($k)))
            ->filter()->unique()->values()->all();

        // Teknisi pertama yang dipilih menjadi ketua.
        $teknisi = collect($data['teknisi'])->values()
            ->mapWithKeys(fn ($id, $i) => [(int) $id => $i === 0 ? 'ketua' : 'anggota'])->all();

        $order = DB::transaction(function () use ($data, $service, $saya, $kelengkapan, $teknisi) {
            [$pelangganId, $unitId] = $this->simpanPelangganUnit($data);

            return $service->terimaBengkel([
                'cabang_id' => $data['cabang_id'],
                'pelanggan_id' => $pelangganId,
                'unit_pelanggan_id' => $unitId,
                'keluhan' => $data['keluhan'],
                'catatan' => $data['catatan'] ?: null,
            ], [
                'kondisi_fisik' => $data['kondisi_fisik'] ?: null,
                'kelengkapan' => $kelengkapan,
                'catatan_kerusakan' => $data['catatan_kerusakan'] ?: null,
                'lokasi_simpan' => $data['lokasi_simpan'] ?: null,
            ], $saya, $teknisi);
        });

        foreach ($this->foto as $file) {
            $service->tambahFoto($order, $file, KategoriFoto::Penerimaan, $saya);
        }

        $penerima = User::query()->with('pushSubscriptions')
            ->whereIn('id', Teknisi::query()->whereIn('id', array_keys($teknisi))->pluck('user_id'))->get();
        if ($penerima->isNotEmpty()) {
            KirimNotifikasi::aman($penerima, new JobBaruDitugaskan([$order->id]));
        }

        session()->flash('sukses', "Barang diterima dengan nomor {$order->no_order}. Cetak tanda terima & label di bawah.");
        $this->redirectRoute('bengkel.detail', $order, navigate: true);
    }

    public function render()
    {
        return view('livewire.bengkel.terima-barang', [
            ...$this->dataPelangganUnit(),
            'pilihanCabang' => Cabang::query()->aktif()->orderBy('nama')->get(['id', 'nama']),
            'pilihanTeknisi' => Teknisi::query()->aktif()
                ->where('cabang_id', $this->cabang_id ?? 0)
                ->whereIn('bagian', [BagianTeknisi::Bengkel->value, BagianTeknisi::Keduanya->value])
                ->orderBy('nama')->get(['id', 'nama', 'bagian']),
            'biayaCek' => $this->cabang_id ? PengaturanBengkel::biayaCek($this->cabang_id) : 0,
            'kelengkapanUmum' => self::KELENGKAPAN_UMUM,
        ]);
    }
}
