<?php

namespace App\Livewire\Concerns;

use App\Support\KoordinatMaps;

/**
 * Isian koordinat dari link Google Maps.
 * Admin menempel link di $linkMaps; latitude/longitude diisi otomatis dari hasil bacaannya.
 */
trait IsiKoordinat
{
    public string $linkMaps = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public function updatedLinkMaps(): void
    {
        $this->resetValidation('linkMaps');

        if (trim($this->linkMaps) === '') {
            return;
        }

        $hasil = KoordinatMaps::baca($this->linkMaps);

        if (! $hasil) {
            $this->addError('linkMaps', 'Koordinat tidak ditemukan di link ini. Buka lokasi di Google Maps, pilih "Bagikan", lalu salin linknya.');

            return;
        }

        $this->latitude = $hasil['lat'];
        $this->longitude = $hasil['lng'];
        $this->linkMaps = '';
    }

    /**
     * Dipanggil di awal simpan(): jika masih ada link yang belum terbaca (mis. langsung klik Simpan
     * setelah menempel), proses dulu. Mengembalikan false jika link tidak valid.
     */
    protected function prosesLinkMapsTertunda(): bool
    {
        if (trim($this->linkMaps) === '') {
            return true;
        }

        $this->updatedLinkMaps();

        return ! $this->getErrorBag()->has('linkMaps');
    }

    public function hapusKoordinat(): void
    {
        $this->reset('linkMaps', 'latitude', 'longitude');
        $this->resetValidation('linkMaps');
    }

    protected function isiKoordinatDari(?object $model): void
    {
        $this->linkMaps = '';
        $this->latitude = $model?->latitude !== null ? (float) $model->latitude : null;
        $this->longitude = $model?->longitude !== null ? (float) $model->longitude : null;
    }

    /** Aturan validasi untuk digabung ke validate() komponen. */
    protected function aturanKoordinat(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }
}
