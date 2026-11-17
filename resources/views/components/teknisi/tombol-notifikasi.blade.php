{{-- Banner di beranda teknisi: ajakan pasang aplikasi & aktifkan notifikasi. Hanya tampil jika belum. --}}
<div class="space-y-3" wire:ignore>
    <div x-data="pasangAplikasi()" x-cloak x-show="status === 'bisa' || status === 'ios'"
        class="flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-950/40">
        <div class="text-sm">
            <div class="font-medium">Pasang aplikasi di HP</div>
            <div class="text-zinc-600 dark:text-zinc-400">Buka lebih cepat dari layar utama.</div>
        </div>
        <div x-show="status === 'bisa'">
            <flux:button variant="primary" size="sm" icon="arrow-down-tray" x-on:click="pasang()">Pasang</flux:button>
        </div>
        <div x-show="status === 'ios'">
            <flux:button size="sm" :href="route('teknisi.perangkat')" wire:navigate>Caranya</flux:button>
        </div>
    </div>

    <div x-data="notifikasiPush()" x-cloak>
        <div x-show="status === 'belum' || status === 'proses'"
            class="flex items-center justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 p-3 dark:border-sky-800 dark:bg-sky-950/40">
            <div class="text-sm">
                <div class="font-medium">Aktifkan notifikasi job baru</div>
                <div class="text-zinc-600 dark:text-zinc-400">Supaya HP berbunyi saat ada job untuk tim Anda.</div>
                <div class="text-red-600" x-show="pesan" x-text="pesan"></div>
            </div>
            <flux:button variant="primary" size="sm" x-on:click="aktifkan()" x-bind:disabled="status === 'proses'">Aktifkan</flux:button>
        </div>
        <a x-show="status === 'ditolak' || status === 'tidak-didukung'" href="{{ route('teknisi.perangkat') }}" wire:navigate
            class="block rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/40">
            Notifikasi belum bisa aktif di HP ini. <span class="font-medium underline">Lihat caranya</span>
        </a>
    </div>
</div>
