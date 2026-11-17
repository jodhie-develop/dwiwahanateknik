<?php

namespace App\Livewire\Teknisi;

use App\Enums\StatusOrder;
use App\Models\OrderServis;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Job hari ini milik teknisi yang login, urut antrian tim (waktu input). */
#[Layout('components.layouts.teknisi')]
#[Title('Job Hari Ini')]
class Beranda extends Component
{
    public function render()
    {
        $user = auth()->user()->loadMissing('teknisi:id,user_id,nama');

        // JobTeknisiScope memastikan hanya job milik teknisi ini yang terambil.
        $job = OrderServis::query()
            ->kunjungan()
            ->padaTanggal(today()->toDateString())
            ->with(['pelanggan:id,nama,alamat,hp,latitude,longitude', 'unit.jenisPerangkat:id,nama', 'unit.pelanggan:id,alamat,latitude,longitude', 'tim:id,nama_tim'])
            ->urutAntrian()
            ->get();

        // Barang bengkel yang sedang ditangani teknisi ini (belum selesai), tanpa batas tanggal.
        $bengkel = OrderServis::query()
            ->bengkel()
            ->whereNotIn('status', [StatusOrder::Selesai->value, StatusOrder::Diambil->value, StatusOrder::Batal->value, StatusOrder::Dikembalikan->value])
            ->with(['pelanggan:id,nama', 'unit.jenisPerangkat:id,nama', 'penerimaan:id,order_id,lokasi_simpan'])
            ->urutAntrian()
            ->get();

        return view('livewire.teknisi.beranda', [
            'bengkel' => $bengkel,
            'teknisi' => $user->teknisi,
            'aktif' => $job->reject(fn ($o) => $o->status->terkunci()),
            'selesai' => $job->filter(fn ($o) => $o->status->terkunci()),
            'bergerak' => $job->contains(fn ($o) => in_array($o->status, [StatusOrder::Berangkat, StatusOrder::Dikerjakan], true)),
        ]);
    }
}
