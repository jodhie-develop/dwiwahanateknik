<?php

namespace App\Livewire\Teknisi;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Halaman teknisi untuk memasang aplikasi (PWA) ke HP dan mengatur notifikasi job. */
#[Layout('components.layouts.teknisi')]
#[Title('Aplikasi & Notifikasi')]
class Perangkat extends Component
{
    public function render()
    {
        return view('livewire.teknisi.perangkat');
    }
}
