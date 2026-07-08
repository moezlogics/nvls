const CACHE_NAME = 'novel-reader-v3';
const PRECACHE = [
    './vendor/pdfjs/pdf.min.js',
    './vendor/pdfjs/pdf.worker.min.js',
];

self.addEventListener('install', e => {
    e.waitUntil(caches.open(CACHE_NAME).then(c => c.addAll(PRECACHE)));
    self.skipWaiting();
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    const url = e.request.url;

    if (e.request.method !== 'GET' || url.includes('/newlogin/') || url.includes('resolve=1')) {
        return;
    }

    if (url.includes('/vendor/pdfjs/')) {
        e.respondWith(
            caches.match(e.request).then(r => r || fetch(e.request).then(resp => {
                const clone = resp.clone();
                caches.open(CACHE_NAME).then(c => c.put(e.request, clone));
                return resp;
            }))
        );
        return;
    }

    e.respondWith(
        fetch(e.request).then(resp => {
            if (resp.ok && (url.match(/\.(css|js|png|jpg|jpeg|gif|webp|svg|woff2?)$/i))) {
                const clone = resp.clone();
                caches.open(CACHE_NAME).then(c => c.put(e.request, clone));
            }
            return resp;
        }).catch(() => caches.match(e.request))
    );
});
