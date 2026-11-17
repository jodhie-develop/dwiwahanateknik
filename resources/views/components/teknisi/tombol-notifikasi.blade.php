{{-- Tombol aktifkan push notification di HP teknisi. Hanya tampil jika belum aktif. --}}
<div x-data="{
        status: 'memeriksa', // memeriksa | tidak-didukung | ditolak | belum | aktif | proses
        pesan: '',
        async init() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                this.status = 'tidak-didukung'; return;
            }
            if (Notification.permission === 'denied') { this.status = 'ditolak'; return; }
            const reg = await navigator.serviceWorker.register('/sw.js');
            const sub = await reg.pushManager.getSubscription();
            this.status = sub && Notification.permission === 'granted' ? 'aktif' : 'belum';
            if (sub) this.kirim(sub); // sinkronkan ulang ke server
        },
        kunci(base64) {
            const pad = '='.repeat((4 - base64.length % 4) % 4);
            const raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
            return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
        },
        async kirim(sub) {
            const json = sub.toJSON();
            json.contentEncoding = (PushManager.supportedContentEncodings || ['aes128gcm'])[0];
            await fetch('{{ route('teknisi.push.simpan') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(json),
            }).then((r) => { if (!r.ok) throw new Error('Gagal menyimpan langganan (' + r.status + ')'); });
        },
        async aktifkan() {
            this.status = 'proses'; this.pesan = '';
            try {
                const izin = await Notification.requestPermission();
                if (izin !== 'granted') { this.status = izin === 'denied' ? 'ditolak' : 'belum'; return; }
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.kunci('{{ config('webpush.vapid.public_key') }}') });
                await this.kirim(sub);
                this.status = 'aktif';
            } catch (e) {
                this.status = 'belum'; this.pesan = e.message;
            }
        }
    }" x-cloak>
    <template x-if="status === 'belum' || status === 'proses'">
        <div class="flex items-center justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 p-3 dark:border-sky-800 dark:bg-sky-950/40">
            <div class="text-sm">
                <div class="font-medium">Aktifkan notifikasi job baru</div>
                <div class="text-zinc-600 dark:text-zinc-400">Supaya HP berbunyi saat ada job untuk tim Anda.</div>
                <div class="text-red-600" x-show="pesan" x-text="pesan"></div>
            </div>
            <flux:button variant="primary" size="sm" x-on:click="aktifkan()" x-bind:disabled="status === 'proses'">Aktifkan</flux:button>
        </div>
    </template>
    <template x-if="status === 'ditolak'">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/40">
            Notifikasi diblokir. Buka pengaturan situs di browser lalu izinkan notifikasi.
        </div>
    </template>
    <template x-if="status === 'tidak-didukung'">
        <div class="rounded-xl border border-zinc-200 p-3 text-sm text-zinc-500 dark:border-zinc-700">
            Browser ini belum mendukung notifikasi. Di iPhone, tambahkan aplikasi ke Home Screen terlebih dahulu.
        </div>
    </template>
</div>
