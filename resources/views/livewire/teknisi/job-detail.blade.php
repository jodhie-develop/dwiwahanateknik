@php($o = $order)
<div class="space-y-4">
    <div class="flex items-center gap-2">
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('teknisi.beranda')" wire:navigate>{{ $bengkel ? "Daftar job" : "Job hari ini" }}</flux:button>
        <flux:spacer />
        <flux:badge :color="$o->status->warna()">{{ $o->status->label() }}</flux:badge>
    </div>

    {{-- Info pelanggan --}}
    <div class="space-y-2 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="text-xl font-semibold">{{ $o->pelanggan?->nama }}</div>
        <div class="font-mono text-xs text-zinc-500">{{ $o->no_order }} · {{ $o->tim?->nama_tim }}</div>
        @unless ($bengkel)
            <div class="text-sm">{{ $o->unit?->alamatEfektif() }}</div>
        @endunless
        <div class="text-sm">
            <span class="font-medium">{{ $o->unit?->jenisPerangkat?->nama }} {{ trim($o->unit?->merk.' '.$o->unit?->model) }}</span>
            {{ $o->unit?->ukuran ? '· '.$o->unit->ukuran : '' }} {{ $o->unit?->lokasi ? '· '.$o->unit->lokasi : '' }}
        </div>
        <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-700/40">
            <div class="text-xs uppercase tracking-wide text-zinc-500">Keluhan</div>
            <div class="whitespace-pre-line">{{ $o->keluhan }}</div>
        </div>
        @if ($bengkel && $o->penerimaan)
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div><div class="text-xs text-zinc-500">Kelengkapan</div>{{ $o->penerimaan->kelengkapanTeks() }}</div>
                <div><div class="text-xs text-zinc-500">Lokasi simpan</div>{{ $o->penerimaan->lokasi_simpan ?: "-" }}</div>
                @if ($o->penerimaan->kondisi_fisik)
                    <div class="col-span-2"><div class="text-xs text-zinc-500">Kondisi fisik</div>{{ $o->penerimaan->kondisi_fisik }}</div>
                @endif
            </div>
        @endif
        @if ($o->jam_janji)
            <div class="text-sm font-medium text-amber-600">Janji jam {{ substr($o->jam_janji, 0, 5) }}</div>
        @endif
        <div class="text-xs text-zinc-500">Teknisi: {{ $o->teknisi->pluck('teknisi.nama')->implode(', ') }}</div>

        <div class="grid grid-cols-2 gap-2 pt-1">
            @if ($o->pelanggan?->hp)
                <flux:button icon="phone" :href="'tel:'.$o->pelanggan->hp" class="w-full">Telepon</flux:button>
            @endif
            @if ($koordinat)
                <flux:button icon="map" :href="'https://www.google.com/maps/dir/?api=1&destination='.$koordinat['lat'].','.$koordinat['lng']" target="_blank" class="w-full">Navigasi</flux:button>
            @endif
        </div>
    </div>

    {{-- Status --}}
    @if ($tujuanStatus->isNotEmpty())
        <div class="space-y-2">
            @foreach ($tujuanStatus as $s)
                @php($label = match ($s) { App\Enums\StatusOrder::Berangkat => 'Berangkat sekarang', App\Enums\StatusOrder::Dikerjakan => $bengkel ? 'Lanjut kerjakan' : 'Mulai kerjakan', App\Enums\StatusOrder::Diagnosa => 'Mulai diagnosa', App\Enums\StatusOrder::MenungguSparepart => 'Tunggu sparepart', App\Enums\StatusOrder::Selesai => 'Selesai', default => $s->label() })
                <flux:button variant="primary" size="base" class="h-14 w-full text-base"
                    wire:click="ubahStatus('{{ $s->value }}')" wire:confirm="{{ $label }}?">{{ $label }}</flux:button>
            @endforeach
            <flux:error name="status" />
        </div>
    @endif

    @if ($bengkel)
        <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="font-medium">Diagnosa & estimasi biaya</div>
            <livewire:order.estimasi-order :order-id="$o->id" :key="'est-'.$o->id" />
        </div>
    @endif

    @unless ($o->terkunci() || $bengkel)
        {{-- Check-in GPS --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800"
             x-data="{ sedang: false, galat: '' }">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="font-medium">Check-in lokasi</div>
                    <div class="text-sm text-zinc-500">
                        {{ $o->checkin_at ? 'Sudah check-in '.$o->checkin_at->format('H:i') : 'Belum check-in' }}
                    </div>
                </div>
                <flux:button icon="map-pin" x-bind:disabled="sedang"
                    x-on:click="
                        galat = '';
                        if (!navigator.geolocation) { galat = 'HP tidak mendukung GPS.'; return; }
                        sedang = true;
                        navigator.geolocation.getCurrentPosition(
                            (p) => { $wire.checkin(p.coords.latitude, p.coords.longitude).finally(() => sedang = false) },
                            (e) => { sedang = false; galat = 'Gagal ambil lokasi: ' + e.message + '. Pastikan GPS & izin lokasi aktif.' },
                            { enableHighAccuracy: true, timeout: 15000 }
                        )">
                    <span x-show="!sedang">Check-in</span><span x-show="sedang">Mencari GPS…</span>
                </flux:button>
            </div>
            <div x-show="galat" x-text="galat" class="mt-2 text-sm text-red-600"></div>
            <flux:error name="checkin" />
        </div>
    @endunless

    {{-- Foto --}}
    <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="font-medium">Foto</div>
        <livewire:order.foto-order :order-id="$o->id" :key="'foto-'.$o->id" />
    </div>

    {{-- Item --}}
    <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="font-medium">Layanan & barang dipakai</div>
        <livewire:order.item-order :order-id="$o->id" :mobile="true" :key="'item-'.$o->id" />
    </div>

    {{-- Tanda tangan (kunjungan) --}}
    @unless ($bengkel)
    <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex items-center justify-between">
            <div class="font-medium">Tanda tangan pelanggan</div>
            @unless ($o->terkunci())
                <flux:modal.trigger name="tanda-tangan">
                    <flux:button size="sm" icon="pencil">{{ $o->tanda_tangan ? 'Ulangi' : 'Minta tanda tangan' }}</flux:button>
                </flux:modal.trigger>
            @endunless
        </div>
        @if ($o->tanda_tangan)
            <img src="{{ berkas_url($o->tanda_tangan) }}" alt="Tanda tangan" class="h-28 rounded border border-zinc-200 bg-white p-1">
        @endif
        <flux:error name="tanda_tangan" />
    </div>

    @endunless

    <flux:modal name="tanda-tangan" class="w-full max-w-lg">
        <div class="space-y-3"
             x-data="{
                kosong: true, menggambar: false, ctx: null,
                init() {
                    const c = this.$refs.kanvas;
                    const rasio = window.devicePixelRatio || 1;
                    const atur = () => {
                        const r = c.getBoundingClientRect();
                        if (!r.width) return;
                        c.width = r.width * rasio; c.height = r.height * rasio;
                        this.ctx = c.getContext('2d');
                        this.ctx.scale(rasio, rasio);
                        this.ctx.lineWidth = 2.5; this.ctx.lineCap = 'round'; this.ctx.lineJoin = 'round'; this.ctx.strokeStyle = '#111';
                        this.kosong = true;
                    };
                    new ResizeObserver(atur).observe(c);
                },
                titik(e) { const r = this.$refs.kanvas.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; },
                mulai(e) { this.menggambar = true; const p = this.titik(e); this.ctx.beginPath(); this.ctx.moveTo(p.x, p.y); },
                gerak(e) { if (!this.menggambar) return; const p = this.titik(e); this.ctx.lineTo(p.x, p.y); this.ctx.stroke(); this.kosong = false; },
                selesai() { this.menggambar = false; },
                hapus() { const c = this.$refs.kanvas; this.ctx.clearRect(0, 0, c.width, c.height); this.kosong = true; },
                simpan() {
                    // Latar putih supaya tanda tangan terbaca di nota.
                    const c = this.$refs.kanvas, salin = document.createElement('canvas');
                    salin.width = c.width; salin.height = c.height;
                    const s = salin.getContext('2d'); s.fillStyle = '#fff'; s.fillRect(0, 0, salin.width, salin.height); s.drawImage(c, 0, 0);
                    $wire.simpanTandaTangan(salin.toDataURL('image/png'));
                }
             }">
            <flux:heading size="lg">Tanda tangan pelanggan</flux:heading>
            <canvas x-ref="kanvas" class="h-56 w-full touch-none rounded-lg border border-zinc-300 bg-white"
                x-on:pointerdown.prevent="mulai($event)" x-on:pointermove.prevent="gerak($event)"
                x-on:pointerup="selesai()" x-on:pointerleave="selesai()"></canvas>
            <div class="flex justify-between gap-2">
                <flux:button variant="ghost" x-on:click="hapus()">Hapus</flux:button>
                <flux:button variant="primary" x-bind:disabled="kosong" x-on:click="simpan()">Simpan tanda tangan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
