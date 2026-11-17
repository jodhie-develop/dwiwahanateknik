<?php

use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Tampilan')] class extends Component {
    //
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout heading="Tampilan" subheading="Atur tema tampilan aplikasi">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">Terang</flux:radio>
            <flux:radio value="dark" icon="moon">Gelap</flux:radio>
            <flux:radio value="system" icon="computer-desktop">Ikuti sistem</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</div>
