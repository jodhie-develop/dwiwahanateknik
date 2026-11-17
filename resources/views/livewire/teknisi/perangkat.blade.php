<div class="space-y-5">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('teknisi.beranda')" wire:navigate>Beranda</flux:button>
        <flux:heading size="xl" class="mt-2">Aplikasi & Notifikasi</flux:heading>
        <flux:text>Pengaturan untuk HP ini.</flux:text>
    </div>

    {{-- Pasang aplikasi --}}
    <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800" x-data="pasangAplikasi()" wire:ignore>
        <div class="flex items-center gap-3">
            <img src="/icons/icon-192.png" alt="" class="size-10 rounded-lg">
            <div>
                <div class="font-semibold">Pasang aplikasi di HP</div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">Buka langsung dari layar utama, tampil layar penuh seperti aplikasi.</div>
            </div>
        </div>

        <div x-show="status === 'terpasang'" class="flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm font-medium text-green-700 dark:bg-green-950/40 dark:text-green-400">
            <flux:icon.check-circle variant="mini" /> Aplikasi sudah terpasang di perangkat ini.
        </div>
        <div x-show="status === 'bisa'">
            <flux:button variant="primary" icon="arrow-down-tray" class="h-12 w-full" x-on:click="pasang()">Pasang ke perangkat</flux:button>
        </div>
        <ol x-show="status === 'ios'" class="list-decimal space-y-1 ps-5 text-sm">
            <li>Buka halaman ini di <b>Safari</b>.</li>
            <li>Ketuk tombol <b>Bagikan</b> (kotak dengan panah ke atas).</li>
            <li>Pilih <b>Tambah ke Layar Utama</b> (Add to Home Screen), lalu <b>Tambah</b>.</li>
            <li>Buka aplikasi dari ikon di layar utama, lalu aktifkan notifikasi di bawah.</li>
        </ol>
        <ol x-show="status === 'manual'" class="list-decimal space-y-1 ps-5 text-sm">
            <li>Buka halaman ini di <b>Google Chrome</b>.</li>
            <li>Ketuk menu <b>⋮</b> di kanan atas.</li>
            <li>Pilih <b>Instal aplikasi</b> atau <b>Tambahkan ke Layar utama</b>.</li>
        </ol>
    </div>

    {{-- Notifikasi --}}
    <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800" x-data="notifikasiPush()" wire:ignore>
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="font-semibold">Notifikasi job baru</div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">HP berbunyi saat admin membagikan job ke tim Anda.</div>
            </div>
            <span x-show="status === 'aktif'"><flux:badge color="green">Aktif</flux:badge></span>
            <span x-show="status === 'belum'"><flux:badge color="zinc">Mati</flux:badge></span>
        </div>

        <div class="text-sm text-red-600" x-show="pesan" x-text="pesan"></div>

        <flux:text x-show="status === 'memeriksa'">Memeriksa…</flux:text>
        <div x-show="status === 'belum' || status === 'proses'">
            <flux:button variant="primary" icon="bell" class="h-12 w-full" x-on:click="aktifkan()" x-bind:disabled="status === 'proses'">Aktifkan notifikasi</flux:button>
        </div>
        <div x-show="status === 'aktif'">
            <flux:button icon="bell-slash" class="h-12 w-full" x-on:click="matikan()">Matikan notifikasi</flux:button>
        </div>
        <div x-show="status === 'ditolak'" class="space-y-1 text-sm">
            <p class="font-medium text-amber-600">Notifikasi diblokir di browser.</p>
            <p>Ketuk ikon gembok / <b>ⓘ</b> di samping alamat situs → <b>Izin</b> → <b>Notifikasi</b> → <b>Izinkan</b>, lalu muat ulang halaman ini.</p>
            <p>Jika memakai aplikasi terpasang: buka <b>Pengaturan HP → Aplikasi → DWT Teknisi → Notifikasi</b>, lalu aktifkan.</p>
        </div>
        <p x-show="status === 'tidak-didukung'" class="text-sm text-zinc-500">
            Browser ini belum mendukung notifikasi. Di iPhone (iOS 16.4 ke atas), pasang aplikasi ke Layar Utama terlebih dahulu lalu buka dari ikonnya.
        </p>
    </div>
</div>
