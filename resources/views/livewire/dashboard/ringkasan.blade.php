@use('App\Enums\StatusOrder')
@use('App\Enums\TipeOrder')
@php
    // Pill status (latar lembut + teks gelap).
    $pill = fn (StatusOrder $s) => match ($s) {
        StatusOrder::Selesai, StatusOrder::Diambil => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        StatusOrder::Dikerjakan, StatusOrder::Berangkat, StatusOrder::Diagnosa => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
        StatusOrder::MenungguSparepart, StatusOrder::MenungguKonfirmasi => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
        StatusOrder::Batal, StatusOrder::Dikembalikan => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        default => 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200',
    };
    $kartu = 'rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900';
    $tglRingkas = now()->parse($tanggalTampil);
@endphp

<div class="space-y-5">
    {{-- Kepala --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1" class="!text-2xl !font-bold">Dashboard</flux:heading>
            <flux:subheading>{{ $tglRingkas->translatedFormat('l, j F Y') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button icon="chevron-left" variant="ghost" wire:click="geserHari(-1)" aria-label="Hari sebelumnya" />
            <div class="w-44"><flux:input type="date" wire:model.live="tanggal" aria-label="Tanggal" /></div>
            <flux:button icon="chevron-right" variant="ghost" wire:click="geserHari(1)" aria-label="Hari berikutnya" />
            <flux:button icon="plus" variant="primary" :href="route('order.baru')" wire:navigate>Order baru</flux:button>
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

    {{-- KPI --}}
    <section aria-label="Ringkasan hari ini" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="{{ $kartu }} flex flex-col gap-1.5 p-[18px]">
            <span class="font-medium text-zinc-600 dark:text-zinc-400">Order hari ini</span>
            <span class="text-3xl font-bold tabular-nums">{{ $kpi['kunjungan'] + $kpi['bengkelMasuk'] }}</span>
            <span class="text-[13px] text-zinc-600 dark:text-zinc-400">{{ $kpi['kunjungan'] }} kunjungan · {{ $kpi['bengkelMasuk'] }} masuk bengkel</span>
        </div>
        <div class="{{ $kartu }} flex flex-col gap-1.5 p-[18px]">
            <span class="font-medium text-zinc-600 dark:text-zinc-400">Tim di lapangan</span>
            <span class="text-3xl font-bold tabular-nums">{{ $kpi['timJalan'] }} <span class="text-base font-medium text-zinc-600 dark:text-zinc-400">dari {{ $box->count() }} tim</span></span>
            <span class="text-[13px] text-zinc-600 dark:text-zinc-400">{{ $kpi['timTuntas'] }} tuntas · {{ $kpi['timBelumMulai'] }} belum mulai</span>
        </div>
        <a href="{{ route('bengkel.index') }}" wire:navigate class="{{ $kartu }} flex flex-col gap-1.5 p-[18px] transition hover:border-cyan-600">
            <span class="font-medium text-zinc-600 dark:text-zinc-400">Unit servis di bengkel</span>
            <span class="text-3xl font-bold tabular-nums">{{ $kpi['unitBengkel'] }}</span>
            <span @class(['text-[13px]', 'font-semibold text-orange-800 dark:text-orange-300' => $kpi['sparepart'] > 0, 'text-zinc-600 dark:text-zinc-400' => $kpi['sparepart'] === 0])>{{ $kpi['sparepart'] }} menunggu sparepart</span>
        </a>
        <div class="{{ $kartu }} flex flex-col gap-1.5 border-dashed p-[18px]">
            <span class="font-medium text-zinc-600 dark:text-zinc-400">Pendapatan {{ $tglRingkas->translatedFormat('F') }}</span>
            <span class="text-3xl font-bold text-zinc-300 dark:text-zinc-600">Rp –</span>
            <span class="inline-flex items-center gap-1.5 text-[13px] text-zinc-600 dark:text-zinc-400"><flux:badge size="sm" color="amber">Segera hadir</flux:badge> modul keuangan (Fase 3)</span>
        </div>
    </section>

    {{-- Ringkasan kerja + keuangan --}}
    <div class="flex flex-wrap gap-5">
        <section aria-labelledby="h-kerja" class="{{ $kartu }} flex min-w-0 flex-[2_1_520px] flex-col gap-4 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="h-kerja" class="text-[17px] font-bold">Ringkasan Kerja hari ini</h2>
                    <span class="text-[13px] text-zinc-600 dark:text-zinc-400">Job kunjungan {{ tgl($tanggalTampil) }}, tidak termasuk yang batal</span>
                </div>
                <a href="{{ route('order.jadwal', ['tanggal' => $tanggalTampil]) }}" wire:navigate class="py-2.5 font-semibold text-cyan-700 hover:text-cyan-800 dark:text-cyan-400">Buka jadwal tim</a>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-[10px] bg-zinc-50 p-4 dark:bg-zinc-800/60">
                    <div class="text-[13px] font-medium text-zinc-600 dark:text-zinc-400">Jumlah pekerjaan</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums">{{ $kerja['total'] }}</div>
                </div>
                <div class="rounded-[10px] bg-green-50 p-4 dark:bg-green-950/40">
                    <div class="text-[13px] font-medium text-green-800 dark:text-green-300">Sudah terselesaikan</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums text-green-700 dark:text-green-400">{{ $kerja['selesai'] }}</div>
                </div>
                <div class="rounded-[10px] bg-orange-50 p-4 dark:bg-orange-950/40">
                    <div class="text-[13px] font-medium text-orange-800 dark:text-orange-300">Belum terselesaikan</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums text-orange-700 dark:text-orange-400">{{ $kerja['belum'] }}</div>
                </div>
                <div class="rounded-[10px] border border-dashed border-zinc-300 p-4 dark:border-zinc-600">
                    <div class="text-[13px] font-medium text-zinc-600 dark:text-zinc-400">Pembayaran diterima</div>
                    <div class="mt-1 text-3xl font-bold text-zinc-300 dark:text-zinc-600">Rp –</div>
                    <flux:badge size="sm" color="amber" class="mt-1">Segera hadir</flux:badge>
                </div>
            </div>

            @if ($kerja['total'] > 0)
                <div class="space-y-1.5">
                    <div class="flex justify-between text-[13px] text-zinc-600 dark:text-zinc-400">
                        <span>Progres</span><span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $kerja['persen'] }}%</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="progressbar" aria-label="Progres kerja hari ini" aria-valuenow="{{ $kerja['persen'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full bg-green-500" style="width: {{ $kerja['persen'] }}%"></div>
                    </div>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full min-w-[420px] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-zinc-600 dark:text-zinc-400">
                            <th class="border-b border-zinc-200 px-3 py-2 font-semibold dark:border-zinc-700">Tim</th>
                            <th class="border-b border-zinc-200 px-3 py-2 text-right font-semibold dark:border-zinc-700">Pekerjaan</th>
                            <th class="border-b border-zinc-200 px-3 py-2 text-right font-semibold dark:border-zinc-700">Selesai</th>
                            <th class="border-b border-zinc-200 px-3 py-2 text-right font-semibold dark:border-zinc-700">Belum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($box as $b)
                            @php($t = $b['tim'])
                            <tr wire:key="kerja-{{ $t->id }}">
                                <td class="border-b border-zinc-100 px-3 py-2 dark:border-zinc-800">
                                    <span class="font-semibold">{{ str($t->nama_tim)->lower()->startsWith('tim') ? $t->nama_tim : 'Tim '.$t->nama_tim }}</span>
                                    <span class="text-[13px] text-zinc-500">· {{ $t->anggotaAktif->map(fn ($a) => $a->teknisi?->nama)->filter()->implode(' & ') ?: 'Belum ada anggota' }}</span>
                                </td>
                                <td class="border-b border-zinc-100 px-3 py-2 text-right tabular-nums dark:border-zinc-800">{{ $b['total'] }}</td>
                                <td class="border-b border-zinc-100 px-3 py-2 text-right tabular-nums text-green-700 dark:border-zinc-800 dark:text-green-400">{{ $b['selesai'] }}</td>
                                <td class="border-b border-zinc-100 px-3 py-2 text-right tabular-nums text-orange-700 dark:border-zinc-800 dark:text-orange-400">{{ $b['total'] - $b['selesai'] }}</td>
                            </tr>
                        @endforeach
                        @if ($belumDibagi > 0)
                            <tr>
                                <td class="px-3 py-2 italic text-zinc-500">Belum dibagi ke tim</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $belumDibagi }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">0</td>
                                <td class="px-3 py-2 text-right tabular-nums text-orange-700 dark:text-orange-400">{{ $belumDibagi }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="h-keu" class="{{ $kartu }} flex min-w-0 flex-[1_1_320px] flex-col gap-4 p-5">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 id="h-keu" class="text-[17px] font-bold">Keuangan {{ $tglRingkas->translatedFormat('F') }}</h2>
                    <span class="text-[13px] text-zinc-600 dark:text-zinc-400">Pemasukan & pengeluaran per minggu</span>
                </div>
                <flux:badge size="sm" color="amber">Segera hadir</flux:badge>
            </div>
            <div class="relative">
                {{-- Kerangka grafik (abu-abu) sebagai gambaran; data asli tersedia setelah modul keuangan. --}}
                <div aria-hidden="true" class="flex h-[190px] items-end justify-between gap-2 border-b border-zinc-300 px-1.5 opacity-40 dark:border-zinc-600">
                    @foreach ([[110, 57], [128, 72], [124, 60], [110, 64]] as [$masuk, $keluar])
                        <div class="flex flex-1 justify-center gap-1">
                            <div class="w-[18px] rounded-t bg-zinc-300 dark:bg-zinc-600" style="height: {{ $masuk }}px"></div>
                            <div class="w-[18px] rounded-t bg-zinc-200 dark:bg-zinc-700" style="height: {{ $keluar }}px"></div>
                        </div>
                    @endforeach
                </div>
                <div class="absolute inset-0 flex items-center justify-center p-4">
                    <p class="max-w-[260px] rounded-lg bg-white/90 px-4 py-3 text-center text-sm text-zinc-700 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900/90 dark:text-zinc-300 dark:ring-zinc-700">
                        Grafik pemasukan, pengeluaran, piutang dan laba tampil di sini setelah modul <strong>Keuangan</strong> (Fase 3) selesai.
                    </p>
                </div>
            </div>
            <div class="flex flex-col gap-2.5 border-t border-zinc-200 pt-3.5 text-zinc-400 dark:border-zinc-700 dark:text-zinc-500">
                @foreach (['Pemasukan', 'Sparepart & freon', 'Gaji & komisi teknisi', 'Operasional (bensin, dll.)'] as $baris)
                    <div class="flex justify-between"><span>{{ $baris }}</span><span>Rp –</span></div>
                @endforeach
                <div class="flex justify-between border-t border-zinc-200 pt-2.5 font-bold dark:border-zinc-700"><span>Laba kotor</span><span>Rp –</span></div>
            </div>
        </section>
    </div>

    {{-- Order terbaru --}}
    <section aria-labelledby="h-order" class="{{ $kartu }} flex flex-col gap-3.5 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="h-order" class="text-[17px] font-bold">Order terbaru</h2>
            <div role="group" aria-label="Filter tipe order" class="flex gap-1 rounded-[10px] bg-zinc-100 p-1 dark:bg-zinc-800">
                @foreach (['semua' => 'Semua', 'kunjungan' => 'Kunjungan', 'bengkel' => 'Bengkel'] as $kunci => $label)
                    <button type="button" wire:click="$set('filterOrder', '{{ $kunci }}')" aria-pressed="{{ $filterOrder === $kunci ? 'true' : 'false' }}"
                        @class([
                            'min-h-9 rounded-[7px] px-3.5 text-[13px]',
                            'bg-white font-semibold text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' => $filterOrder === $kunci,
                            'font-medium text-zinc-600 dark:text-zinc-400' => $filterOrder !== $kunci,
                        ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] border-collapse text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-zinc-600 dark:text-zinc-400">
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-semibold dark:border-zinc-700">No. order</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-semibold dark:border-zinc-700">Pelanggan</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-semibold dark:border-zinc-700">Layanan / keluhan</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-semibold dark:border-zinc-700">Tipe</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-semibold dark:border-zinc-700">Tim / teknisi</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-semibold dark:border-zinc-700">Status</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 text-right font-semibold dark:border-zinc-700">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orderTerbaru as $o)
                        @php($layanan = $o->item->where('jenis', \App\Enums\JenisItem::Layanan)->pluck('nama')->filter()->unique()->implode(', '))
                        <tr wire:key="ord-{{ $o->id }}">
                            <td class="border-b border-zinc-100 p-3 font-semibold tabular-nums dark:border-zinc-800"><a href="{{ $o->urlDetail() }}" wire:navigate class="text-cyan-700 hover:text-cyan-800 dark:text-cyan-400">{{ $o->no_order }}</a></td>
                            <td class="border-b border-zinc-100 p-3 dark:border-zinc-800">{{ $o->pelanggan?->nama }}</td>
                            <td class="max-w-72 truncate border-b border-zinc-100 p-3 dark:border-zinc-800">{{ $layanan ?: ($o->keluhan ?: '—') }}</td>
                            <td class="border-b border-zinc-100 p-3 text-zinc-700 dark:border-zinc-800 dark:text-zinc-300">{{ $o->tipe->label() }}</td>
                            <td class="border-b border-zinc-100 p-3 text-zinc-700 dark:border-zinc-800 dark:text-zinc-300">
                                {{ $o->tipe === TipeOrder::Kunjungan ? ($o->tim?->nama_tim ?? 'Belum dibagi') : ($o->teknisi->map(fn ($ot) => $ot->teknisi?->nama)->filter()->implode(', ') ?: '—') }}
                            </td>
                            <td class="border-b border-zinc-100 p-3 dark:border-zinc-800"><span class="{{ $pill($o->status) }} whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold">{{ $o->status->label() }}</span></td>
                            <td class="border-b border-zinc-100 p-3 text-right tabular-nums dark:border-zinc-800">{{ rupiah($o->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-6 text-center text-zinc-500">Belum ada order.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Alur servis bengkel --}}
    <section aria-labelledby="h-bengkel" class="{{ $kartu }} flex flex-col gap-3.5 p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 id="h-bengkel" class="text-[17px] font-bold">Alur servis bengkel</h2>
                <span class="text-[13px] text-zinc-600 dark:text-zinc-400">TV, kulkas, mesin cuci & elektronik lain yang dibawa pelanggan</span>
            </div>
            @if ($belumDiambilLewat > 0)
                <flux:button size="sm" icon="exclamation-triangle" class="text-amber-600" :href="route('bengkel.index', ['tampil' => 'belum_diambil'])" wire:navigate>
                    {{ $belumDiambilLewat }} belum diambil > {{ $batasHariAmbil }} hari
                </flux:button>
            @endif
        </div>
        <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(170px,1fr))]">
            @foreach ($alurBengkel as $s)
                @php($waspada = $s['status'] === StatusOrder::MenungguSparepart && $s['jumlah'] > 0)
                <div wire:key="alur-{{ $s['status']->value }}" class="flex flex-col gap-2.5 rounded-[10px] border border-zinc-200 bg-zinc-50 p-3.5 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <a href="{{ route('bengkel.index', ['tampil' => $s['status']->value]) }}" wire:navigate class="flex items-center justify-between gap-2 hover:underline">
                        <span class="font-semibold">{{ $s['label'] }}</span>
                        <span @class([
                            'inline-flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-[13px] font-bold',
                            'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300' => $waspada,
                            'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200' => ! $waspada,
                        ])>{{ $s['jumlah'] }}</span>
                    </a>
                    @foreach ($s['unit'] as $u)
                        <a href="{{ $u->urlDetail() }}" wire:navigate class="flex flex-col gap-0.5 rounded-lg border border-zinc-200 bg-white p-2.5 hover:border-cyan-600 dark:border-zinc-700 dark:bg-zinc-900">
                            <span class="text-[13px] font-semibold">{{ trim(($u->unit?->jenisPerangkat?->nama ?? 'Barang').' '.($u->unit?->merk ?? '')) }} · {{ $u->pelanggan?->nama }}</span>
                            <span class="line-clamp-2 text-xs text-zinc-600 dark:text-zinc-400">{{ $u->keluhan ?: $u->no_order }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
</div>
