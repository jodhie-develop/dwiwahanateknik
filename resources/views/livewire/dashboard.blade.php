@php
    // Warna kepala kartu per tim (bergiliran). Teks nama tim putih di atas gradasi gelap agar kontras.
    $palet = [
        ['kepala' => 'from-indigo-600 to-violet-600', 'bar' => 'from-indigo-500 to-violet-500', 'cincin' => 'hover:ring-indigo-300 dark:hover:ring-indigo-700'],
        ['kepala' => 'from-sky-600 to-blue-700', 'bar' => 'from-sky-500 to-blue-600', 'cincin' => 'hover:ring-sky-300 dark:hover:ring-sky-700'],
        ['kepala' => 'from-emerald-600 to-teal-700', 'bar' => 'from-emerald-500 to-teal-500', 'cincin' => 'hover:ring-emerald-300 dark:hover:ring-emerald-700'],
        ['kepala' => 'from-orange-600 to-red-600', 'bar' => 'from-orange-500 to-red-500', 'cincin' => 'hover:ring-orange-300 dark:hover:ring-orange-700'],
        ['kepala' => 'from-rose-600 to-pink-700', 'bar' => 'from-rose-500 to-pink-500', 'cincin' => 'hover:ring-rose-300 dark:hover:ring-rose-700'],
        ['kepala' => 'from-fuchsia-600 to-purple-700', 'bar' => 'from-fuchsia-500 to-purple-500', 'cincin' => 'hover:ring-fuchsia-300 dark:hover:ring-fuchsia-700'],
    ];
@endphp

{{--
    Tiap 20 detik: data di-refresh (tanpa reload halaman, supaya mode layar penuh tidak keluar) dan,
    jika tidak semua kartu tim muat di layar, kartu berganti ke kelompok tim berikutnya.
    Di layar sempit (< 640px) tidak bergiliran: kartu ditampilkan semua dan cukup di-scroll.
--}}
<div
    x-data="{
        layarPenuh: false,
        bisaLayarPenuh: document.fullscreenEnabled ?? false,
        halaman: 0,
        perHalaman: Infinity,
        jumlah: 0,
        timer: null,
        tundaUkur: null,
        get jumlahHalaman() {
            return Number.isFinite(this.perHalaman) ? Math.max(1, Math.ceil(this.jumlah / this.perHalaman)) : 1;
        },
        tampil(i) {
            return Math.floor(i / this.perHalaman) === this.halaman;
        },
        toggleLayarPenuh() {
            document.fullscreenElement ? document.exitFullscreen() : this.$root.requestFullscreen();
        },
        init() {
            this.$nextTick(() => this.hitung());
            this.timer = setInterval(() => this.putar(), 20000);
            window.addEventListener('resize', () => {
                clearTimeout(this.tundaUkur);
                this.tundaUkur = setTimeout(() => this.ukurUlang(), 300);
            });
        },
        destroy() {
            clearInterval(this.timer);
        },
        putar() {
            if (this.jumlahHalaman > 1) {
                this.halaman = (this.halaman + 1) % this.jumlahHalaman;
            }
            this.$wire.$refresh().then(() => this.$nextTick(() => this.hitung()));
        },
        keHalaman(h) {
            this.halaman = h;
            clearInterval(this.timer);
            this.timer = setInterval(() => this.putar(), 20000);
        },
        ukurUlang() {
            this.perHalaman = Infinity;
            this.halaman = 0;
            this.$nextTick(() => this.hitung());
        },
        hitung() {
            const grid = this.$refs.grid;
            if (! grid) return;
            const kartu = [...grid.querySelectorAll('[data-kartu-tim]')];
            this.jumlah = kartu.length;

            if (window.innerWidth < 640) {
                this.perHalaman = Infinity;
                this.halaman = 0;
                return;
            }

            const tinggi = Math.max(0, ...kartu.map((k) => k.offsetHeight));
            if (! tinggi) return;

            const gaya = getComputedStyle(grid);
            const kolom = gaya.gridTemplateColumns.split(' ').length;
            const jarak = parseFloat(gaya.rowGap) || 0;
            const gulir = document.fullscreenElement ? this.$root.scrollTop : window.scrollY;
            const atas = grid.getBoundingClientRect().top + gulir;
            const baris = Math.max(1, Math.floor((window.innerHeight - atas - 24 + jarak) / (tinggi + jarak)));

            const muat = baris * kolom;
            this.perHalaman = muat >= this.jumlah ? Infinity : muat;
            if (this.halaman >= this.jumlahHalaman) this.halaman = 0;
        },
    }"
    x-on:fullscreenchange.document="layarPenuh = document.fullscreenElement === $root; $nextTick(() => ukurUlang())"
    class="space-y-6 fullscreen:overflow-y-auto fullscreen:bg-zinc-100 fullscreen:p-6 lg:fullscreen:p-10 dark:fullscreen:bg-zinc-950">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-center gap-4">
            <div class="shrink-0 rounded-xl bg-white p-1 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700">
                <img src="/images/logo-dwiwahana.webp" width="800" height="533" alt="Logo Dwiwahana Teknik" class="h-14 w-auto">
            </div>
            <div>
                <flux:heading size="xl" level="1" class="font-display !text-2xl !font-bold tracking-tight">Dwiwahana Teknik AC</flux:heading>
                <flux:subheading class="font-display !text-base">
                    <span class="bg-gradient-to-r from-indigo-600 via-fuchsia-600 to-orange-500 bg-clip-text font-semibold text-transparent dark:from-indigo-400 dark:via-fuchsia-400 dark:to-orange-300">Job Progress Monitoring</span>
                    · {{ now()->parse($tanggalTampil)->translatedFormat('l') }}, {{ tgl($tanggalTampil) }}
                </flux:subheading>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <flux:button icon="chevron-left" variant="ghost" wire:click="geserHari(-1)" aria-label="Hari sebelumnya" />
            <div class="w-44"><flux:input type="date" wire:model.live="tanggal" aria-label="Tanggal" /></div>
            <flux:button icon="chevron-right" variant="ghost" wire:click="geserHari(1)" aria-label="Hari berikutnya" />
            <template x-if="bisaLayarPenuh">
                <div>
                    <flux:button x-show="! layarPenuh" icon="arrows-pointing-out" variant="primary" x-on:click="toggleLayarPenuh()">Full Screen</flux:button>
                    <flux:button x-show="layarPenuh" x-cloak icon="arrows-pointing-in" x-on:click="toggleLayarPenuh()">Keluar</flux:button>
                </div>
            </template>
        </div>
    </div>

    @if ($belumDibagi > 0)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ $belumDibagi }} order belum dibagi ke tim</flux:callout.heading>
            <x-slot name="actions">
                <flux:button size="sm" :href="route('order.jadwal', ['tanggal' => $tanggalTampil])" wire:navigate>Bagi di Jadwal Tim</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    {{-- Tempatnya selalu disediakan (invisible, bukan hidden) agar ukuran grid tidak bergeser saat indikator muncul. --}}
    <div :class="jumlahHalaman > 1 ? '' : 'invisible'" class="-mt-3 flex h-5 items-center justify-end gap-3 text-xs text-zinc-500">
        <span>Tim <span x-text="halaman * perHalaman + 1"></span>–<span x-text="Math.min((halaman + 1) * perHalaman, jumlah)"></span> dari <span x-text="jumlah"></span> · ganti otomatis tiap 20 detik</span>
        <div class="flex gap-1.5">
            <template x-for="h in jumlahHalaman" :key="h">
                <button type="button" x-on:click="keHalaman(h - 1)" :aria-label="'Halaman ' + h"
                    :class="halaman === h - 1 ? 'w-6 bg-zinc-700 dark:bg-zinc-200' : 'w-2.5 bg-zinc-300 hover:bg-zinc-400 dark:bg-zinc-600'"
                    class="h-2.5 rounded-full transition-all duration-300"></button>
            </template>
        </div>
    </div>

    <div x-ref="grid" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($box as $b)
            @php($t = $b['tim'])
            @php($w = $palet[$loop->index % count($palet)])
            <div wire:key="box-{{ $t->id }}" data-kartu-tim style="animation-delay: {{ $loop->index * 70 }}ms"
                x-show="tampil({{ $loop->index }})"
                x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                @class([
                    'group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200 transition duration-300 hover:-translate-y-1 hover:shadow-xl motion-safe:animate-muncul dark:bg-zinc-900 dark:ring-zinc-800',
                    $w['cincin'],
                ])>

                {{-- Kepala kartu: nama tim besar, putih di atas gradasi --}}
                <div @class(['relative flex items-start justify-between gap-3 overflow-hidden bg-gradient-to-br px-5 py-4 text-white', $w['kepala']])>
                    <div class="pointer-events-none absolute -right-6 -top-10 size-32 rounded-full bg-white/10 transition-transform duration-500 group-hover:scale-125"></div>
                    <div class="relative min-w-0">
                        @unless (str($t->nama_tim)->lower()->startsWith('tim'))
                            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/80">Tim</div>
                        @endunless
                        <div class="truncate font-display text-4xl font-extrabold leading-none tracking-tight drop-shadow-sm">{{ $t->nama_tim }}</div>
                        @if ($tampilCabang)
                            <div class="mt-1 text-xs text-white/80">{{ $t->cabang?->nama }}</div>
                        @endif
                    </div>

                    <div class="relative shrink-0 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold ring-1 ring-white/30 backdrop-blur-sm">
                        @if ($b['total'] > 0 && $b['selesai'] === $b['total'])
                            <span class="flex items-center gap-1.5"><flux:icon.check-circle variant="micro" /> Tuntas</span>
                        @elseif ($b['berjalan'] > 0)
                            <span class="flex items-center gap-2">
                                <span class="relative flex size-2">
                                    <span class="absolute inline-flex size-full rounded-full bg-white opacity-75 motion-safe:animate-ping"></span>
                                    <span class="relative inline-flex size-2 rounded-full bg-white"></span>
                                </span>
                                Sedang bekerja
                            </span>
                        @elseif ($b['total'] === 0)
                            Tidak ada job
                        @else
                            Belum mulai
                        @endif
                    </div>
                </div>

                {{-- Isi kartu --}}
                <div class="flex flex-1 flex-col gap-4 p-5">
                    <div class="space-y-1.5">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Anggota</div>
                        @forelse ($t->anggotaAktif as $a)
                            <div class="flex items-center gap-2 text-sm">
                                <flux:avatar size="xs" :name="$a->teknisi?->nama" />
                                <span class="font-medium">{{ $a->teknisi?->nama }}</span>
                                @if ($a->peran === App\Enums\PeranTim::Ketua)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">ketua</span>
                                @endif
                            </div>
                        @empty
                            <div class="text-sm text-zinc-500">Belum ada anggota</div>
                        @endforelse
                    </div>

                    <div class="mt-auto space-y-3">
                        <div class="grid grid-cols-2 gap-3 text-center">
                            <div class="rounded-xl bg-zinc-50 py-3 dark:bg-zinc-800/70">
                                <div class="font-display text-3xl font-bold tabular-nums">{{ $b['total'] }}</div>
                                <div class="text-xs text-zinc-500">Job hari ini</div>
                            </div>
                            <div class="rounded-xl bg-green-50 py-3 dark:bg-green-950/40">
                                <div class="font-display text-3xl font-bold tabular-nums text-green-700 dark:text-green-400">{{ $b['selesai'] }}</div>
                                <div class="text-xs text-zinc-500">Selesai</div>
                            </div>
                        </div>

                        <div class="h-2.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="progressbar" aria-label="Progres {{ $t->nama_tim }}" aria-valuenow="{{ $b['persen'] }}" aria-valuemin="0" aria-valuemax="100">
                            <div @class(['relative h-full overflow-hidden rounded-full bg-gradient-to-r transition-all duration-700', $w['bar']]) style="width: {{ $b['persen'] }}%">
                                @if ($b['persen'] > 0 && $b['persen'] < 100)
                                    <div class="absolute inset-y-0 w-1/3 bg-gradient-to-r from-transparent via-white/60 to-transparent motion-safe:animate-kilau"></div>
                                @endif
                            </div>
                        </div>
                        <div class="flex justify-between text-xs text-zinc-500">
                            <span><span class="font-display font-semibold text-zinc-700 dark:text-zinc-200">{{ $b['persen'] }}% selesai</span>{{ $b['berjalan'] ? ' · '.$b['berjalan'].' sedang dikerjakan' : '' }}</span>
                            @if ($b['batal'])
                                <span>{{ $b['batal'] }} batal</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-500 sm:col-span-2 xl:col-span-3 dark:border-zinc-600">
                Belum ada tim aktif. Buat tim di menu <a href="{{ route('master.tim') }}" class="underline" wire:navigate>Tim</a>.
            </div>
        @endforelse
    </div>

    {{-- Ringkasan bengkel --}}
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg" class="font-display !font-semibold">Barang di bengkel</flux:heading>
            @if ($belumDiambilLewat > 0)
                <flux:button size="sm" icon="exclamation-triangle" class="text-amber-600" :href="route('bengkel.index', ['tampil' => 'belum_diambil'])" wire:navigate>
                    {{ $belumDiambilLewat }} belum diambil > {{ $batasHariAmbil }} hari
                </flux:button>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
            @foreach ($ringkasBengkel as $r)
                <a href="{{ route('bengkel.index', ['tampil' => $r['status']->value]) }}" wire:navigate wire:key="bkl-{{ $r['status']->value }}"
                   @class([
                       'rounded-xl border p-3 transition-colors',
                       'border-sky-200 bg-sky-50 hover:bg-sky-100 dark:border-sky-900 dark:bg-sky-950/30 dark:hover:bg-sky-950/50' => $r['status'] === App\Enums\StatusOrder::Diterima,
                       'border-blue-200 bg-blue-50 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/30 dark:hover:bg-blue-950/50' => $r['status'] === App\Enums\StatusOrder::Diagnosa,
                       'border-amber-200 bg-amber-50 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/30 dark:hover:bg-amber-950/50' => $r['status'] === App\Enums\StatusOrder::MenungguKonfirmasi,
                       'border-indigo-200 bg-indigo-50 hover:bg-indigo-100 dark:border-indigo-900 dark:bg-indigo-950/30 dark:hover:bg-indigo-950/50' => $r['status'] === App\Enums\StatusOrder::Dikerjakan,
                       'border-orange-200 bg-orange-50 hover:bg-orange-100 dark:border-orange-900 dark:bg-orange-950/30 dark:hover:bg-orange-950/50' => $r['status'] === App\Enums\StatusOrder::MenungguSparepart,
                       'border-green-200 bg-green-50 hover:bg-green-100 dark:border-green-900 dark:bg-green-950/30 dark:hover:bg-green-950/50' => $r['status'] === App\Enums\StatusOrder::Selesai,
                       'border-red-200 bg-red-50 hover:bg-red-100 dark:border-red-900 dark:bg-red-950/30 dark:hover:bg-red-950/50' => $r['status'] === App\Enums\StatusOrder::Batal,
                   ])>
                    <div class="text-2xl font-semibold">{{ $r['jumlah'] }}</div>
                    <div class="text-xs text-zinc-500">{{ $r['label'] }}</div>
                </a>
            @endforeach
        </div>
    </div>
</div>
