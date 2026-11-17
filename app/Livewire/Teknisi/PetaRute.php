<?php

namespace App\Livewire\Teknisi;

use App\Services\RuteTeknisi;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Peta rute job hari ini untuk teknisi (Leaflet + OpenStreetMap, rute jalan dari OSRM). */
#[Layout('components.layouts.teknisi')]
#[Title('Peta Rute')]
class PetaRute extends Component
{
    public function render(RuteTeknisi $rute)
    {
        $data = $rute->untuk(auth()->user());

        return view('livewire.teknisi.peta-rute', [
            ...$data,
            'linkGoogle' => RuteTeknisi::linkGoogleMaps($data['titik']),
        ]);
    }
}
