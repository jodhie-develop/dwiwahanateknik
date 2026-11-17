{{--
    Logika PWA teknisi (dipakai banner beranda & halaman Aplikasi & Notifikasi):
    - pasangAplikasi: tombol "Pasang ke perangkat" (Android/Chrome lewat beforeinstallprompt, iPhone lewat panduan).
    - notifikasiPush: aktifkan / matikan push notification dan sinkronkan langganan ke server.
--}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }

    // Chrome/Android memicu event ini sekali saat aplikasi bisa dipasang; simpan supaya bisa dipanggil dari tombol.
    window.promptPasang = null;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.promptPasang = e;
        window.dispatchEvent(new CustomEvent('pwa-bisa-dipasang'));
    });
    window.addEventListener('appinstalled', () => {
        window.promptPasang = null;
        window.dispatchEvent(new CustomEvent('pwa-terpasang'));
    });

    window.pwaKonfig = {
        urlPush: @js(route('teknisi.push.simpan')),
        kunciVapid: @js(config('webpush.vapid.public_key')),
    };

    // Fungsi global (bukan Alpine.data) supaya tetap tersedia saat halaman dibuka lewat wire:navigate
    // setelah Alpine sudah berjalan (mis. dari halaman login).
    (() => {
        const headerJson = () => ({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
        });

        window.pasangAplikasi = () => ({
            // terpasang | bisa | ios | manual
            status: 'manual',
            init() {
                this.periksa();
                window.addEventListener('pwa-bisa-dipasang', () => this.periksa());
                window.addEventListener('pwa-terpasang', () => { this.status = 'terpasang'; });
            },
            periksa() {
                const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
                const ios = /iphone|ipad|ipod/i.test(navigator.userAgent)
                    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                this.status = standalone ? 'terpasang' : (window.promptPasang ? 'bisa' : (ios ? 'ios' : 'manual'));
            },
            async pasang() {
                if (!window.promptPasang) { this.periksa(); return; }
                window.promptPasang.prompt();
                const { outcome } = await window.promptPasang.userChoice;
                window.promptPasang = null;
                this.status = outcome === 'accepted' ? 'terpasang' : 'manual';
            },
        });

        window.notifikasiPush = () => ({
            // memeriksa | tidak-didukung | ditolak | belum | aktif | proses
            status: 'memeriksa',
            pesan: '',
            async init() {
                if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                    this.status = 'tidak-didukung'; return;
                }
                if (Notification.permission === 'denied') { this.status = 'ditolak'; return; }
                try {
                    const reg = await navigator.serviceWorker.register('/sw.js');
                    const sub = await reg.pushManager.getSubscription();
                    this.status = sub && Notification.permission === 'granted' ? 'aktif' : 'belum';
                    if (sub && this.status === 'aktif') this.simpan(sub).catch(() => {}); // sinkronkan ulang ke server
                } catch (e) { this.status = 'belum'; }
            },
            kunci(base64) {
                const pad = '='.repeat((4 - base64.length % 4) % 4);
                const raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
                return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
            },
            async simpan(sub) {
                const json = sub.toJSON();
                json.contentEncoding = (PushManager.supportedContentEncodings || ['aes128gcm'])[0];
                const r = await fetch(window.pwaKonfig.urlPush, { method: 'POST', headers: headerJson(), body: JSON.stringify(json) });
                if (!r.ok) throw new Error('Gagal menyimpan pengaturan notifikasi (' + r.status + ')');
            },
            async aktifkan() {
                this.status = 'proses'; this.pesan = '';
                try {
                    const izin = await Notification.requestPermission();
                    if (izin !== 'granted') { this.status = izin === 'denied' ? 'ditolak' : 'belum'; return; }
                    const reg = await navigator.serviceWorker.ready;
                    const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.kunci(window.pwaKonfig.kunciVapid) });
                    await this.simpan(sub);
                    this.status = 'aktif';
                } catch (e) { this.status = 'belum'; this.pesan = e.message; }
            },
            async matikan() {
                this.status = 'proses'; this.pesan = '';
                try {
                    const reg = await navigator.serviceWorker.ready;
                    const sub = await reg.pushManager.getSubscription();
                    if (sub) {
                        await fetch(window.pwaKonfig.urlPush, { method: 'DELETE', headers: headerJson(), body: JSON.stringify({ endpoint: sub.endpoint }) });
                        await sub.unsubscribe();
                    }
                    this.status = 'belum';
                } catch (e) { this.status = 'aktif'; this.pesan = e.message; }
            },
        });
    })();
</script>
