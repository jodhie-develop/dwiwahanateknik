@assets
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        // Komponen Alpine peta rute (didefinisikan global agar siap sebelum x-data dievaluasi).
        window.petaRute = (titik) => ({
            titik,
            memuat: true,
            galat: '',
            total: null,
            kaki: [],
    
            init() {
                const warna = { kantor: '#18181b', aktif: '#2563eb', order: '#0ea5e9', selesai: '#16a34a' };
                const peta = L.map(this.$refs.peta);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',
                }).addTo(peta);
    
                const batas = [];
                this.titik.forEach((t) => {
                    const teks = t.jenis === 'kantor' ? 'K' : t.nomor;
                    const ikon = L.divIcon({
                        className: '',
                        html: `<div style="background:${warna[t.jenis] || '#0ea5e9'};color:#fff;width:28px;height:28px;border-radius:9999px;display:flex;align-items:center;justify-content:center;font:600 13px sans-serif;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)">${teks}</div>`,
                        iconSize: [28, 28],
                        iconAnchor: [14, 14],
                    });
                    L.marker([t.lat, t.lng], { icon: ikon }).addTo(peta)
                        .bindPopup(`<strong>${t.label}</strong>${t.alamat ? '<br>' + t.alamat : ''}`);
                    batas.push([t.lat, t.lng]);
                });
    
                if (batas.length === 1) {
                    peta.setView(batas[0], 16);
                    this.memuat = false;
                    return;
                }
                peta.fitBounds(batas, { padding: [30, 30] });
    
                // Rute mengikuti jalan dari OSRM (server publik gratis). Gagal → garis lurus.
                const koordinat = this.titik.map((t) => `${t.lng},${t.lat}`).join(';');
                const kontrol = new AbortController();
                setTimeout(() => kontrol.abort(), 12000);
    
                fetch(`https://router.project-osrm.org/route/v1/driving/${koordinat}?overview=full&geometries=geojson`, { signal: kontrol.signal })
                    .then((r) => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
                    .then((data) => {
                        if (data.code !== 'Ok' || !data.routes?.length) throw new Error(data.code || 'tanpa rute');
                        const rute = data.routes[0];
                        const garis = L.geoJSON(rute.geometry, { style: { color: '#2563eb', weight: 5, opacity: .8 } }).addTo(peta);
                        peta.fitBounds(garis.getBounds(), { padding: [30, 30] });
                        this.kaki = rute.legs.map((l) => ({ km: (l.distance / 1000).toFixed(1), menit: Math.max(1, Math.round(l.duration / 60)) }));
                        this.total = { km: (rute.distance / 1000).toFixed(1), menit: Math.round(rute.duration / 60) };
                    })
                    .catch(() => {
                        L.polyline(batas, { color: '#2563eb', weight: 4, dashArray: '8 8' }).addTo(peta);
                        this.galat = 'Rute jalan tidak bisa dihitung saat ini; ditampilkan garis lurus. Gunakan tombol Google Maps untuk navigasi.';
                    })
                    .finally(() => this.memuat = false);
            },
        });
    </script>
@endassets

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('teknisi.beranda')" wire:navigate>Job hari ini</flux:button>
        <flux:spacer />
        <flux:badge>{{ $mode === 'lanjut' ? 'Ke job berikutnya' : ($mode === 'awal' ? 'Rute dari kantor' : 'Tidak ada job') }}</flux:badge>
    </div>

    @if ($pesan)
        <flux:callout variant="warning" icon="information-circle" :heading="$pesan" />
    @endif

    @if (count($titik) > 0)
        <div wire:ignore
             x-data="petaRute(@js($titik))"
             class="space-y-3">
            <div x-ref="peta" class="h-[60vh] w-full overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700"></div>

            <div class="rounded-xl border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                <template x-if="memuat"><div class="text-zinc-500">Menghitung rute jalan…</div></template>
                <template x-if="!memuat && total">
                    <div class="font-medium">Total ± <span x-text="total.km"></span> km · ± <span x-text="total.menit"></span> menit berkendara</div>
                </template>
                <template x-if="!memuat && galat">
                    <div class="text-amber-600" x-text="galat"></div>
                </template>
                <ol class="mt-2 space-y-1">
                    <template x-for="(t, i) in titik" :key="i">
                        <li class="flex justify-between gap-2">
                            <span x-text="t.label"></span>
                            <span class="whitespace-nowrap text-zinc-500" x-show="i > 0 && kaki[i - 1]" x-text="kaki[i - 1] ? kaki[i - 1].km + ' km · ' + kaki[i - 1].menit + ' mnt' : ''"></span>
                        </li>
                    </template>
                </ol>
            </div>
        </div>

        @if ($linkGoogle)
            <flux:button variant="primary" icon="map" :href="$linkGoogle" target="_blank" class="h-12 w-full">Navigasi di Google Maps</flux:button>
        @endif
    @endif

    @if ($tanpaLokasi)
        <flux:callout variant="warning" icon="map-pin">
            <flux:callout.heading>Lokasi belum diisi</flux:callout.heading>
            <flux:callout.text>{{ implode(', ', $tanpaLokasi) }} — tidak tampil di peta. Minta admin mengisi link lokasi pelanggan.</flux:callout.text>
        </flux:callout>
    @endif
</div>
