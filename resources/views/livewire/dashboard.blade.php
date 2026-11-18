<div class="space-y-6" wire:poll.30s>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Dashboard</flux:heading>
            <flux:subheading>Monitoring job kunjungan tiap tim · {{ now()->parse($tanggalTampil)->translatedFormat('l') }}, {{ tgl($tanggalTampil) }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button icon="chevron-left" variant="ghost" wire:click="geserHari(-1)" aria-label="Hari sebelumnya" />
            <div class="w-44"><flux:input type="date" wire:model.live="tanggal" aria-label="Tanggal" /></div>
            <flux:button icon="chevron-right" variant="ghost" wire:click="geserHari(1)" aria-label="Hari berikutnya" />
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

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($box as $b)
            @php($t = $b['tim'])
            <div wire:key="box-{{ $t->id }}" @class([
                'flex flex-col gap-4 rounded-xl border p-5',
                'border-sky-200 bg-sky-50 dark:border-sky-900 dark:bg-sky-950/30' => $loop->even,
                'border-pink-200 bg-pink-50 dark:border-pink-900 dark:bg-pink-950/30' => $loop->odd,
            ])>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-lg font-semibold">{{ $t->nama_tim }}</div>
                        @if ($tampilCabang)
                            <div class="text-xs text-zinc-500">{{ $t->cabang?->nama }}</div>
                        @endif
                    </div>
                    @if ($b['total'] > 0 && $b['selesai'] === $b['total'])
                        <flux:badge color="green" size="sm" icon="check">Tuntas</flux:badge>
                    @elseif ($b['berjalan'] > 0)
                        <flux:badge color="blue" size="sm">Sedang bekerja</flux:badge>
                    @elseif ($b['total'] === 0)
                        <flux:badge color="zinc" size="sm">Tidak ada job</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">Belum mulai</flux:badge>
                    @endif
                </div>

                <div class="space-y-1">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Anggota</div>
                    @forelse ($t->anggotaAktif as $a)
                        <div class="flex items-center gap-2 text-sm">
                            <flux:avatar size="xs" :name="$a->teknisi?->nama" />
                            <span>{{ $a->teknisi?->nama }}</span>
                            @if ($a->peran === App\Enums\PeranTim::Ketua)
                                <span class="text-xs text-amber-600">ketua</span>
                            @endif
                        </div>
                    @empty
                        <div class="text-sm text-zinc-500">Belum ada anggota</div>
                    @endforelse
                </div>

                <div class="mt-auto space-y-2">
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div class="rounded-lg bg-zinc-50 py-2 dark:bg-zinc-800">
                            <div class="text-2xl font-semibold">{{ $b['total'] }}</div>
                            <div class="text-xs text-zinc-500">Job hari ini</div>
                        </div>
                        <div class="rounded-lg bg-green-50 py-2 dark:bg-green-950/40">
                            <div class="text-2xl font-semibold text-green-700 dark:text-green-400">{{ $b['selesai'] }}</div>
                            <div class="text-xs text-zinc-500">Selesai</div>
                        </div>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="progressbar" aria-valuenow="{{ $b['persen'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full bg-green-500 transition-all" style="width: {{ $b['persen'] }}%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-zinc-500">
                        <span>{{ $b['persen'] }}% selesai{{ $b['berjalan'] ? ' · '.$b['berjalan'].' sedang dikerjakan' : '' }}</span>
                        @if ($b['batal'])
                            <span>{{ $b['batal'] }} batal</span>
                        @endif
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
            <flux:heading size="lg">Barang di bengkel</flux:heading>
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
