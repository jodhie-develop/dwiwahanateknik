<div class="space-y-4">
    @if ($terakhir)
        <div class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Estimasi berlaku</div>
                    <div class="text-xl font-semibold">{{ rupiah($terakhir->nominal) }}</div>
                </div>
                <flux:badge :color="$terakhir->status->warna()">{{ $terakhir->status->label() }}</flux:badge>
            </div>
            <p class="whitespace-pre-line text-sm">{{ $terakhir->catatan }}</p>
            <div class="text-xs text-zinc-500">Diajukan {{ $terakhir->pembuat?->nama }} · {{ tgl($terakhir->created_at, true) }}</div>
            @if ($terakhir->diputuskan_at)
                <div class="text-xs text-zinc-500">
                    {{ $terakhir->status->label() }} via {{ $terakhir->media?->label() ?? '-' }} · {{ tgl($terakhir->diputuskan_at, true) }}
                    · dicatat {{ $terakhir->pencatat?->nama }}
                    @if ($terakhir->catatan_keputusan) · "{{ $terakhir->catatan_keputusan }}" @endif
                </div>
            @endif
            @if ($bisaPutuskan && $linkWa)
                <flux:button size="sm" icon="chat-bubble-left-right" :href="$linkWa" target="_blank">Kirim estimasi via WA</flux:button>
            @endif
        </div>
    @else
        <div class="text-sm text-zinc-500">Belum ada estimasi.</div>
    @endif

    @if ($bisaPutuskan)
        <div class="space-y-3 rounded-lg bg-amber-50 p-3 dark:bg-amber-950/30">
            <div class="font-medium">Jawaban pelanggan</div>
            <flux:radio.group wire:model="media" variant="segmented" size="sm" label="Dikonfirmasi lewat">
                @foreach ($pilihanMedia as $nilai => $label)
                    <flux:radio value="{{ $nilai }}" label="{{ $label }}" />
                @endforeach
            </flux:radio.group>
            <flux:input wire:model="catatanKeputusan" label="Catatan (wajib jika ditolak)" placeholder="Mis. pelanggan setuju asal selesai Sabtu" />
            <div class="grid grid-cols-2 gap-2">
                <flux:button variant="primary" icon="check" wire:click="putuskan(true)" wire:confirm="Catat: pelanggan SETUJU dan mulai kerjakan?">Setuju</flux:button>
                <flux:button variant="danger" icon="x-mark" wire:click="putuskan(false)" wire:confirm="Catat: pelanggan MENOLAK? Order akan dibatalkan.">Tolak</flux:button>
            </div>
        </div>
    @endif

    @if ($bisaAjukan)
        <form wire:submit="ajukan" class="space-y-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-700/40">
            <div class="font-medium">{{ $terakhir && $bisaPutuskan ? 'Revisi estimasi' : 'Ajukan estimasi' }}</div>
            <flux:textarea wire:model="catatan" label="Hasil diagnosa / rincian perbaikan" rows="3" placeholder="Kapasitor mainboard bengkak, perlu ganti 2 pcs" />
            <flux:input wire:model="nominal" type="number" min="0" label="Estimasi biaya">
                <x-slot name="iconLeading"><span class="text-sm text-zinc-500">Rp</span></x-slot>
            </flux:input>
            <flux:button type="submit" variant="primary" class="w-full">Simpan estimasi</flux:button>
        </form>
    @endif

    <flux:error name="estimasi" />
    <flux:error name="status" />

    @if ($riwayat->count() > 1)
        <details class="text-sm">
            <summary class="cursor-pointer text-zinc-500">Riwayat estimasi ({{ $riwayat->count() }})</summary>
            <ul class="mt-2 space-y-1">
                @foreach ($riwayat as $e)
                    <li wire:key="est-{{ $e->id }}">{{ tgl($e->created_at, true) }} · {{ rupiah($e->nominal) }} · {{ $e->status->label() }}</li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
