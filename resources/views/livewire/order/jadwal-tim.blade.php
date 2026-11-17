<div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Jadwal Tim</flux:heading>
            <flux:subheading>Antrian tiap tim urut waktu input order. Order baru dibagikan lewat tombol "Bagi tim".</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($pilihanCabang->isNotEmpty())
                <div class="w-44">
                    <flux:select wire:model.live="cabangId" aria-label="Cabang">
                        @foreach ($pilihanCabang as $c)
                            <flux:select.option value="{{ $c->id }}">{{ $c->nama }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif
            <flux:button icon="chevron-left" variant="ghost" wire:click="geserHari(-1)" aria-label="Hari sebelumnya" />
            <div class="w-44"><flux:input type="date" wire:model.live="tanggal" aria-label="Tanggal" /></div>
            <flux:button icon="chevron-right" variant="ghost" wire:click="geserHari(1)" aria-label="Hari berikutnya" />
            <flux:button variant="primary" icon="plus" :href="route('order.baru')" wire:navigate>Order baru</flux:button>
        </div>
    </div>

    {{-- Belum dibagi --}}
    <flux:card class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="lg">Belum dibagi ({{ $belumDibagi->count() }})</flux:heading>
            <flux:button variant="primary" icon="arrows-right-left" wire:click="bagiTim" :disabled="$belumDibagi->isEmpty() || $daftarTim->isEmpty()">Bagi tim</flux:button>
        </div>
        @if ($daftarTim->isEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle" heading="Belum ada tim aktif di cabang ini. Buat tim di menu Tim." />
        @endif
        @forelse ($belumDibagi as $o)
            @include('livewire.order.partials.kartu-jadwal', ['o' => $o])
        @empty
            <div class="text-sm text-zinc-500">Semua order sudah dibagi.</div>
        @endforelse
    </flux:card>

    {{-- Kolom per tim --}}
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($daftarTim as $t)
            @php($antrian = $perTim->get($t->id, collect()))
            <flux:card class="space-y-3" wire:key="tim-{{ $t->id }}">
                <div>
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ $t->nama_tim }}</flux:heading>
                        <flux:badge size="sm">{{ $antrian->where('status', '!=', App\Enums\StatusOrder::Batal)->count() }} job</flux:badge>
                    </div>
                    <div class="text-xs text-zinc-500">{{ $t->anggotaAktif->pluck('teknisi.nama')->implode(', ') ?: 'Belum ada anggota' }}</div>
                </div>
                @forelse ($antrian as $i => $o)
                    @include('livewire.order.partials.kartu-jadwal', ['o' => $o, 'nomor' => $loop->iteration])
                @empty
                    <div class="text-sm text-zinc-500">Belum ada job.</div>
                @endforelse
            </flux:card>
        @endforeach
    </div>

    {{-- Pratinjau pembagian --}}
    <flux:modal name="pratinjau-bagi" class="w-full max-w-3xl">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Usulan pembagian {{ tgl($tanggal) }}</flux:heading>
                <flux:subheading>Periksa dan ubah tim jika perlu, lalu tekan Terapkan. Teknisi akan menerima notifikasi.</flux:subheading>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($usulan as $orderId => $timId)
                    @php($o = $orderUsulan->get($orderId))
                    @continue(! $o)
                    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center" wire:key="usulan-{{ $orderId }}">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $o->pelanggan?->nama }} <span class="font-mono text-xs text-zinc-500">{{ $o->no_order }}</span></div>
                            <div class="truncate text-xs text-zinc-500">{{ $o->pelanggan?->alamat }}</div>
                            <div class="text-xs text-zinc-500">
                                Input {{ tgl($o->created_at, true) }} · {{ $infoUsulan[$orderId]['alasan'] ?? '' }}
                                @if (($infoUsulan[$orderId]['jarak_km'] ?? null) !== null)
                                    <span class="font-medium">(± {{ $infoUsulan[$orderId]['jarak_km'] }} km)</span>
                                @endif
                            </div>
                        </div>
                        <div class="sm:w-48">
                            <flux:select wire:model="usulan.{{ $orderId }}" size="sm" aria-label="Tim untuk {{ $o->no_order }}">
                                <flux:select.option value="">Jangan dibagi dulu</flux:select.option>
                                @foreach ($daftarTim as $t)
                                    <flux:select.option value="{{ $t->id }}">{{ $t->nama_tim }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                @endforeach
            </div>
            <flux:error name="usulan" />
            <flux:error name="tim_id" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="terapkan" icon="check">Terapkan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
