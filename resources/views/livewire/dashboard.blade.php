{{--
    Owner: tab Ringkasan (KPI, jadwal tim, order terbaru, alur bengkel) & Monitor TV.
    Admin cabang: langsung Monitor TV + ringkasan bengkel.
--}}
<div class="space-y-6">
    @if ($isOwner)
        <div role="tablist" aria-label="Tampilan dashboard" class="inline-flex gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800">
            @foreach (['ringkasan' => ['Ringkasan', 'squares-2x2'], 'monitor' => ['Monitor TV', 'tv']] as $kunci => [$label, $ikon])
                <button type="button" role="tab" wire:click="$set('tampilan', '{{ $kunci }}')" aria-selected="{{ $tampilanAktif === $kunci ? 'true' : 'false' }}"
                    @class([
                        'flex min-h-9 items-center gap-2 rounded-lg px-4 text-sm transition',
                        'bg-white font-semibold text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' => $tampilanAktif === $kunci,
                        'font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $tampilanAktif !== $kunci,
                    ])>
                    <flux:icon :icon="$ikon" variant="micro" /> {{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($tampilanAktif === 'monitor')
        @include('livewire.dashboard.monitor')
        @include('livewire.dashboard.bengkel')
    @else
        @include('livewire.dashboard.ringkasan')
    @endif
</div>
