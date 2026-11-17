// Service worker PWA teknisi: menerima push notification dan membuka halaman job saat notifikasi diklik.
// Sengaja tidak meng-cache halaman: data job harus selalu terbaru dari server.

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'Notifikasi', body: event.data ? event.data.text() : '' };
    }

    const judul = data.title || 'Job baru';
    event.waitUntil(
        self.registration.showNotification(judul, {
            body: data.body || '',
            icon: data.icon || '/icons/icon-192.png',
            badge: data.badge || '/icons/badge-72.png',
            tag: data.tag,
            renotify: !!data.renotify,
            vibrate: [200, 100, 200],
            data: data.data || {},
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const tujuan = new URL((event.notification.data && event.notification.data.url) || '/teknisi', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((jendela) => {
            for (const w of jendela) {
                if (w.url.startsWith(self.location.origin) && 'focus' in w) {
                    w.navigate(tujuan);
                    return w.focus();
                }
            }
            return self.clients.openWindow(tujuan);
        })
    );
});
