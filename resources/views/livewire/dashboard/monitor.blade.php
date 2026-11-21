@php
    // Warna kartu per tim: semua cukup gelap supaya teks putih kontras >= 4.5:1.
    $palet = [
        ['a' => '#4338CA', 'b' => '#312E81', 'tinta' => '#312E81'], // indigo
        ['a' => '#0F766E', 'b' => '#134E4A', 'tinta' => '#115E59'], // teal
        ['a' => '#BE123C', 'b' => '#881337', 'tinta' => '#9F1239'], // rose
        ['a' => '#B45309', 'b' => '#78350F', 'tinta' => '#92400E'], // amber
        ['a' => '#7C3AED', 'b' => '#4C1D95', 'tinta' => '#5B21B6'], // ungu
        ['a' => '#1D4ED8', 'b' => '#1E3A8A', 'tinta' => '#1E40AF'], // biru
        ['a' => '#047857', 'b' => '#064E3B', 'tinta' => '#065F46'], // hijau
        ['a' => '#A21CAF', 'b' => '#701A75', 'tinta' => '#86198F'], // fuchsia
    ];

    // Pill status job: latar putih + teks gelap, terbaca di warna kartu apa pun.
    $gayaStatus = fn (App\Enums\StatusOrder $s) => match ($s) {
        App\Enums\StatusOrder::Selesai => ['#166534', '#16A34A'],
        App\Enums\StatusOrder::Dikerjakan => ['#155E75', '#0891B2'],
        App\Enums\StatusOrder::Berangkat => ['#1E40AF', '#2563EB'],
        default => ['#334155', '#64748B'],
    };

    $keliling = 2 * M_PI * 44;
    $tglMonitor = now()->parse($tanggalTampil);
@endphp

{{--
    Monitor TV. Tiap 20 detik: data di-refresh (tanpa reload halaman, supaya mode layar penuh tidak keluar) dan,
    jika tidak semua tim muat dalam satu baris, kartu berganti ke kelompok tim berikutnya.
    Di layar sempit (< 640px) tidak bergiliran: kartu ditampilkan semua dan cukup di-scroll.
--}}
<div class="space-y-4"
    x-data="{
        layarPenuh: false,
        bisaLayarPenuh: document.fullscreenEnabled ?? false,
        jeda: 20,
        sisa: 20,
        halaman: 0,
        perHalaman: Infinity,
        jumlah: 0,
        jam: '',
        detik: '',
        timer: null,
        tundaUkur: null,
        saatResize: null,
        get jumlahHalaman() {
            return Number.isFinite(this.perHalaman) ? Math.max(1, Math.ceil(this.jumlah / this.perHalaman)) : 1;
        },
        tampil(i) {
            return Math.floor(i / this.perHalaman) === this.halaman;
        },
        toggleLayarPenuh() {
            document.fullscreenElement ? document.exitFullscreen() : this.$refs.layar.requestFullscreen();
        },
        init() {
            this.detak();
            this.$nextTick(() => this.hitung());
            this.timer = setInterval(() => this.detak(true), 1000);
            this.saatResize = () => {
                clearTimeout(this.tundaUkur);
                this.tundaUkur = setTimeout(() => this.ukurUlang(), 300);
            };
            window.addEventListener('resize', this.saatResize);
        },
        destroy() {
            clearInterval(this.timer);
            window.removeEventListener('resize', this.saatResize);
        },
        detak(hitungMundur = false) {
            const n = new Date();
            const dua = (x) => String(x).padStart(2, '0');
            this.jam = dua(n.getHours()) + ':' + dua(n.getMinutes());
            this.detik = dua(n.getSeconds());
            if (hitungMundur && --this.sisa <= 0) this.putar();
        },
        putar() {
            this.sisa = this.jeda;
            if (this.jumlahHalaman > 1) {
                this.halaman = (this.halaman + 1) % this.jumlahHalaman;
            }
            this.$wire.$refresh().then(() => this.$nextTick(() => this.hitung()));
        },
        keHalaman(h) {
            this.halaman = h;
            this.sisa = this.jeda;
        },
        ukurUlang() {
            this.perHalaman = Infinity;
            this.halaman = 0;
            this.$nextTick(() => this.hitung());
        },
        hitung() {
            const grid = this.$refs.grid;
            if (! grid) return;
            this.jumlah = grid.querySelectorAll('[data-kartu-tim]').length;
            const kolom = getComputedStyle(grid).gridTemplateColumns.split(' ').length;

            // Satu baris kartu per halaman; di HP (1 kolom) semua ditampilkan.
            this.perHalaman = (window.innerWidth < 640 || kolom < 2 || this.jumlah <= kolom) ? Infinity : kolom;
            if (this.halaman >= this.jumlahHalaman) this.halaman = 0;
        },
    }"
    x-on:fullscreenchange.document="layarPenuh = document.fullscreenElement === $refs.layar; $nextTick(() => ukurUlang())">

    {{-- Bilah kontrol (di luar layar monitor) --}}
    <div class="flex flex-wrap items-center justify-end gap-2">
        <flux:button icon="chevron-left" variant="ghost" wire:click="geserHari(-1)" aria-label="Hari sebelumnya" />
        <div class="w-44"><flux:input type="date" wire:model.live="tanggal" aria-label="Tanggal" /></div>
        <flux:button icon="chevron-right" variant="ghost" wire:click="geserHari(1)" aria-label="Hari berikutnya" />
        <template x-if="bisaLayarPenuh">
            <flux:button icon="arrows-pointing-out" variant="primary" x-on:click="toggleLayarPenuh()">Full Screen</flux:button>
        </template>
    </div>

    @if ($belumDibagi > 0)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ $belumDibagi }} order belum dibagi ke tim</flux:callout.heading>
            <x-slot name="actions">
                <flux:button size="sm" :href="route('order.jadwal', ['tanggal' => $tanggalTampil])" wire:navigate>Bagi di Jadwal Tim</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    {{-- Layar monitor: ukuran teks mengikuti lebar layar (cqw) agar sama proporsinya di panel admin & TV 1920×1080 --}}
    <section x-ref="layar" aria-label="Monitor progres job"
        class="@container flex flex-col gap-[clamp(16px,1.25cqw,24px)] overflow-hidden rounded-3xl bg-[#07131B] bg-[radial-gradient(1200px_600px_at_85%_-10%,#0F3442_0%,#07131B_60%)] p-[clamp(16px,2.2cqw,44px)] font-sans text-slate-100 fullscreen:h-screen fullscreen:rounded-none">

        {{-- Kepala: merek + tanggal + jam --}}
        <header class="flex flex-wrap items-center justify-between gap-x-10 gap-y-4">
            <div class="flex min-w-0 items-center gap-[clamp(12px,1.15cqw,22px)]">
                <div class="shrink-0 rounded-2xl bg-white p-1.5 shadow-lg">
                    <img src="/images/logo-dwiwahana.webp" width="800" height="533" alt="Logo Dwiwahana Teknik" class="h-[clamp(44px,4cqw,76px)] w-auto">
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <h1 class="m-0 font-display text-[clamp(24px,2.3cqw,44px)] font-bold leading-tight tracking-tight text-white">Dwiwahana Teknik AC</h1>
                    <div class="flex flex-wrap items-center gap-3 text-[clamp(14px,1.15cqw,22px)] text-[#A9BCC6]">
                        <span>Job Progress Monitoring{{ $namaCabang ? ' · '.$namaCabang : '' }}</span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-green-400/15 px-3 py-0.5 text-[clamp(12px,0.95cqw,18px)] font-bold tracking-[0.08em] text-green-300">
                            <span class="size-2.5 rounded-full bg-green-400 motion-safe:animate-denyut"></span>LIVE
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-[clamp(16px,1.9cqw,36px)]">
                <div class="flex flex-col items-end gap-1">
                    <span class="text-[clamp(18px,1.55cqw,30px)] font-semibold text-slate-100">{{ $tglMonitor->translatedFormat('l') }}</span>
                    <span class="text-[clamp(15px,1.35cqw,26px)] text-[#A9BCC6]">{{ $tglMonitor->translatedFormat('j F Y') }}</span>
                    @unless ($tglMonitor->isToday())
                        <span class="rounded-full bg-amber-400/20 px-2.5 py-0.5 text-xs font-semibold text-amber-200">Bukan hari ini</span>
                    @endunless
                </div>
                <div class="h-[clamp(48px,5cqw,96px)] w-0.5 bg-slate-400/30"></div>
                <div class="flex items-baseline font-display text-[clamp(44px,5.8cqw,112px)] font-semibold leading-none tracking-tight tabular-nums" aria-label="Jam sekarang">
                    <span x-text="jam">{{ now()->format('H:i') }}</span>
                    <span x-text="detik" class="ml-2 text-[0.46em] text-cyan-300">{{ now()->format('s') }}</span>
                </div>
            </div>
        </header>

        {{-- Ringkasan semua tim --}}
        <div class="flex flex-wrap items-center gap-x-[clamp(16px,1.45cqw,28px)] gap-y-4 rounded-[20px] border border-white/10 bg-white/5 px-[clamp(16px,1.55cqw,30px)] py-[clamp(14px,1.15cqw,22px)]">
            @foreach ([
                ['Total tim', $ringkasJob['tim'], 'text-white'],
                ['Total job', $ringkasJob['total'], 'text-white'],
                ['Selesai', $ringkasJob['selesai'], 'text-green-300'],
                ['Dikerjakan', $ringkasJob['dikerjakan'], 'text-cyan-300'],
                ['Berangkat', $ringkasJob['berangkat'], 'text-blue-300'],
                ['Terjadwal', $ringkasJob['terjadwal'], 'text-slate-300'],
            ] as [$label, $nilai, $warna])
                <div class="flex min-w-[clamp(72px,6.8cqw,130px)] flex-col gap-1">
                    <span class="text-[clamp(12px,0.95cqw,18px)] font-medium text-[#A9BCC6]">{{ $label }}</span>
                    <span class="{{ $warna }} font-display text-[clamp(26px,2.5cqw,48px)] font-bold leading-none tabular-nums">{{ $nilai }}</span>
                </div>
            @endforeach
            <div class="flex min-w-[240px] flex-1 flex-col gap-3 border-l-2 border-slate-400/25 pl-[clamp(16px,1.45cqw,28px)]">
                <div class="flex items-baseline justify-between">
                    <span class="text-[clamp(15px,1.15cqw,22px)] font-semibold">Progress hari ini</span>
                    <span class="font-display text-[clamp(24px,2.1cqw,40px)] font-bold">{{ $ringkasJob['persen'] }}%</span>
                </div>
                <div class="flex h-[clamp(12px,1.05cqw,20px)] overflow-hidden rounded-full bg-white/10" role="progressbar" aria-label="Progres semua tim" aria-valuenow="{{ $ringkasJob['persen'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="bg-green-400 transition-all duration-700" style="width: {{ $ringkasJob['lebarSelesai'] }}%"></div>
                    <div class="bg-[repeating-linear-gradient(45deg,#22D3EE_0_12px,#0EA5C4_12px_24px)] transition-all duration-700" style="width: {{ $ringkasJob['lebarJalan'] }}%"></div>
                </div>
            </div>
        </div>

        {{-- Kartu tim --}}
        <div x-ref="grid" class="grid gap-[clamp(14px,1.15cqw,22px)] sm:grid-cols-2 xl:grid-cols-4 di-layar-penuh:min-h-0 di-layar-penuh:flex-1">
            @forelse ($box as $b)
                @php
                    $t = $b['tim'];
                    $w = $palet[$loop->index % count($palet)];
                    $rasio = $b['total'] > 0 ? $b['selesai'] / $b['total'] : 0;
                    $aktif = $b['aktif'];
                @endphp
                <article wire:key="tim-{{ $t->id }}" data-kartu-tim x-show="tampil({{ $loop->index }})"
                    :class="perHalaman !== Infinity && 'motion-safe:animate-geser-masuk'"
                    class="flex min-h-0 flex-col gap-[clamp(12px,0.95cqw,18px)] overflow-hidden rounded-[22px] p-[clamp(16px,1.25cqw,24px)] text-white shadow-[0_18px_40px_rgba(0,0,0,0.35),inset_0_1px_0_rgba(255,255,255,0.18)]"
                    style="background: linear-gradient(160deg, {{ $w['a'] }} 0%, {{ $w['b'] }} 100%)">

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 flex-col gap-3">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                <h2 class="m-0 truncate font-display text-[clamp(26px,2.2cqw,42px)] font-bold leading-none text-white">{{ str($t->nama_tim)->lower()->startsWith('tim') ? $t->nama_tim : 'Tim '.$t->nama_tim }}</h2>
                                <span class="whitespace-nowrap rounded-full bg-black/30 px-3 py-1 text-[clamp(11px,0.85cqw,16px)] font-bold tracking-wide">
                                    @if ($b['total'] > 0 && $b['selesai'] === $b['total'])
                                        Tuntas
                                    @elseif ($b['berjalan'] > 0)
                                        Bekerja
                                    @elseif ($b['total'] === 0)
                                        Tidak ada job
                                    @else
                                        Belum mulai
                                    @endif
                                </span>
                            </div>
                            @if ($tampilCabang)
                                <span class="-mt-1.5 text-sm text-white/75">{{ $t->cabang?->nama }}</span>
                            @endif
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                @forelse ($t->anggotaAktif as $a)
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex size-[clamp(28px,1.9cqw,36px)] items-center justify-center rounded-full bg-white text-[clamp(13px,0.85cqw,16px)] font-extrabold" style="color: {{ $w['tinta'] }}">{{ mb_strtoupper(mb_substr($a->teknisi?->nama ?? '?', 0, 1)) }}</span>
                                        <span class="text-[clamp(14px,1cqw,19px)] font-medium">{{ $a->teknisi?->nama }}</span>
                                        @if ($a->peran === App\Enums\PeranTim::Ketua)
                                            <span class="rounded-full bg-amber-300 px-1.5 text-[11px] font-bold text-amber-950">ketua</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-sm text-white/75">Belum ada anggota</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="relative size-[clamp(76px,5.4cqw,104px)] shrink-0">
                            <svg role="img" aria-label="{{ $b['selesai'] }} dari {{ $b['total'] }} job selesai ({{ $b['persen'] }}%)" viewBox="0 0 104 104" class="size-full">
                                <circle cx="52" cy="52" r="44" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width="10"></circle>
                                <circle cx="52" cy="52" r="44" fill="none" stroke="#FFFFFF" stroke-width="10" stroke-linecap="round"
                                    stroke-dasharray="{{ number_format($rasio * $keliling, 1, '.', '') }} {{ number_format($keliling, 1, '.', '') }}" transform="rotate(-90 52 52)"
                                    class="transition-all duration-700"></circle>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="font-display text-[clamp(20px,1.45cqw,28px)] font-bold leading-none">{{ $b['selesai'] }}/{{ $b['total'] }}</span>
                                <span class="mt-0.5 text-[clamp(11px,0.7cqw,13px)] font-medium">selesai</span>
                            </div>
                        </div>
                    </div>

                    @if ($aktif)
                        <div class="flex flex-col gap-2 rounded-2xl bg-white p-[clamp(14px,0.95cqw,18px)] text-slate-900 shadow-[0_10px_30px_rgba(0,0,0,0.25)]">
                            <span class="inline-flex items-center gap-2 text-[clamp(12px,0.8cqw,15px)] font-extrabold tracking-[0.1em]" style="color: {{ $w['tinta'] }}">
                                <span class="size-2.5 rounded-full" style="background: {{ $gayaStatus($aktif['status'])[1] }}"></span>
                                {{ $aktif['status'] === App\Enums\StatusOrder::Dikerjakan ? 'SEDANG DIKERJAKAN' : 'DALAM PERJALANAN' }}
                            </span>
                            <span class="text-[clamp(18px,1.35cqw,26px)] font-bold leading-tight">{{ $aktif['kerja'] }}</span>
                            <span class="text-[clamp(14px,1cqw,19px)] text-slate-700">{{ $aktif['tempat'] }}</span>
                            @if ($aktif['mulai'] || $aktif['jam'])
                                <span class="text-[clamp(13px,0.9cqw,17px)] text-slate-600">{{ $aktif['mulai'] ? 'Mulai '.$aktif['mulai'] : 'Janji '.$aktif['jam'] }}</span>
                            @endif
                        </div>
                    @endif

                    <div class="flex min-h-0 flex-col">
                        <span class="mb-1 text-[clamp(12px,0.8cqw,15px)] font-bold tracking-[0.1em]">JADWAL HARI INI</span>
                        @forelse ($b['jobs'] as $j)
                            @continue($aktif && $j['order']->is($aktif['order']))
                            @php([$fg, $titik] = $gayaStatus($j['status']))
                            <div @class(['flex gap-3.5 border-t border-white/20 py-[clamp(10px,0.75cqw,14px)]', 'opacity-80' => $j['status'] === App\Enums\StatusOrder::Selesai])>
                                <span class="w-[clamp(52px,3.75cqw,72px)] flex-none font-display text-[clamp(16px,1.15cqw,22px)] font-semibold tabular-nums">{{ $j['jam'] ?? '—' }}</span>
                                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                                    <span class="text-[clamp(15px,1.05cqw,20px)] font-semibold leading-tight">{{ $j['kerja'] }}</span>
                                    <span class="truncate text-[clamp(12px,0.8cqw,15px)] text-white/75">{{ $j['tempat'] }}</span>
                                    <span class="inline-flex items-center gap-2 self-start whitespace-nowrap rounded-full bg-white px-3 py-1 text-[clamp(12px,0.85cqw,16px)] font-bold" style="color: {{ $fg }}">
                                        <span class="size-2 rounded-full" style="background: {{ $titik }}"></span>{{ $j['status']->label() }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="border-t border-white/20 py-3 text-white/75">Belum ada job.</div>
                        @endforelse
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/25 p-6 text-center text-[#A9BCC6] sm:col-span-2 xl:col-span-4">
                    Belum ada tim aktif. Buat tim di menu <a href="{{ route('master.tim') }}" class="underline" wire:navigate>Tim</a>.
                </div>
            @endforelse
        </div>

        {{-- Kaki: info + penanda halaman --}}
        <footer class="flex flex-wrap items-center justify-between gap-3 text-[clamp(13px,0.95cqw,18px)] text-[#A9BCC6]">
            <span>Diperbarui otomatis tiap 20 detik</span>
            <div x-show="jumlahHalaman > 1" x-cloak class="flex items-center gap-5">
                <span class="font-semibold text-slate-200">Tim <span x-text="halaman * perHalaman + 1"></span>–<span x-text="Math.min((halaman + 1) * perHalaman, jumlah)"></span> dari <span x-text="jumlah"></span></span>
                <div class="flex items-center gap-2">
                    <template x-for="h in jumlahHalaman" :key="h">
                        <button type="button" x-on:click="keHalaman(h - 1)" :aria-label="'Halaman ' + h"
                            :class="halaman === h - 1 ? 'w-10 bg-white' : 'w-3 bg-white/30 hover:bg-white/50'"
                            class="h-3 rounded-full transition-all duration-300"></button>
                    </template>
                </div>
                <span class="tabular-nums">Berganti dalam <span x-text="sisa"></span> dtk</span>
            </div>
            <template x-if="layarPenuh">
                <button type="button" x-on:click="toggleLayarPenuh()" class="rounded-lg px-3 py-1.5 text-slate-300 ring-1 ring-white/20 hover:bg-white/10">Keluar layar penuh</button>
            </template>
        </footer>
    </section>
</div>
