<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Profil')] class extends Component {
    public string $nama = '';
    public string $email = '';

    public function mount(): void
    {
        $this->nama = Auth::user()->nama;
        $this->email = (string) Auth::user()->email;
    }

    public function simpan(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill(['nama' => $data['nama'], 'email' => $data['email'] ?: null])->save();

        $this->dispatch('profile-updated', name: $user->nama);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="Profil" subheading="Ubah nama dan email Anda">
        <form wire:submit="simpan" class="my-6 w-full space-y-6">
            <flux:input label="Username" :value="auth()->user()->username" disabled description="Username hanya bisa diubah oleh admin." />

            <flux:input wire:model="nama" label="Nama" type="text" required autofocus autocomplete="name" />

            <flux:input wire:model="email" label="Email (opsional)" type="email" autocomplete="email" />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Simpan</flux:button>

                <x-action-message class="me-3" on="profile-updated">Tersimpan.</x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
