// Basic offline shell for the PWA / APK wrapper.
const CACHE = 'nannyapp-v9';
const ASSETS = [
  './',
  './index.php',
  './assets/css/style.css',
  './assets/js/app.js',
  './manifest.webmanifest',
  './assets/img/icon.svg'
];

// Admin is web-only by design (see is_native_app_request() server-side) —
// never let admin pages land in the offline cache.
function isSensitivePath(url) {
  try {
    const path = new URL(url).pathname;
    return path.includes('/admin/') || /\/migrate_v\d+\.php$/.test(path);
  } catch (e) {
    return false;
  }
}

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).catch(() => {}));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
  );
  self.clients.claim();
});

// Network-first for dynamic PHP pages, cache fallback when offline.
self.addEventListener('fetch', (e) => {
  if (e.request.method !== 'GET') return;

  if (isSensitivePath(e.request.url)) {
    e.respondWith(fetch(e.request));
    return;
  }

  e.respondWith(
    fetch(e.request)
      .then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(e.request, copy)).catch(() => {});
        return res;
      })
      .catch(() => caches.match(e.request).then((r) => r || caches.match('./index.php')))
  );
});
