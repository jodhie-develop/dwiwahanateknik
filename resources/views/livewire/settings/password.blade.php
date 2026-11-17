<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Ubah Password')] class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ], attributes: [
                'current_password' => 'password saat ini',
                'password' => 'password baru',
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        // Model meng-hash otomatis lewat cast 'hashed'.
        Auth::user()->update(['password' => $validated['password']]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="Ubah password" subheading="Gunakan password yang panjang dan sulit ditebak">
        <form wire:submit="updatePassword" class="mt-6 space-y-6">
            <flux:input wire:model="current_password" label="Password saat ini" type="password" required autocomplete="current-password" viewable />
            <flux:input wire:model="password" label="Password baru" type="password" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" label="Ulangi password baru" type="password" required autocomplete="new-password" viewable />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Simpan</flux:button>

                <x-action-message class="me-3" on="password-updated">Tersimpan.</x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
