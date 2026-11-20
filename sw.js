/**
 * Smart Stock service worker.
 *
 * Deliberately conservative: the network always wins, so a cashier can never
 * be served yesterday's prices or a stale cart out of a cache. The cache is a
 * fallback for when the Wi-Fi drops, and holds only the static shell files.
 *
 * This only runs on a secure origin (https, or localhost). Over plain http on
 * the shop Wi-Fi the browser refuses to register it, which is why the page
 * script checks isSecureContext before asking.
 */
const VERSION = 'smartstock-v1';
const SHELL = [
  'assets/css/style.css',
  'assets/css/product-form.css',
  'assets/js/app.js',
  'assets/vendor/bootstrap/bootstrap.min.css',
  'assets/vendor/bootstrap/bootstrap.bundle.min.js',
  'assets/vendor/icons/bootstrap-icons.min.css',
  'assets/img/logo.png',
  'assets/img/app-icon-192.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(VERSION)
      .then((cache) => cache.addAll(SHELL.map((p) => new URL(p, self.registration.scope).toString())))
      .catch(() => null)           // a missing file must not block installation
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Never touch anything that changes data, and never cache another origin.
  if (request.method !== 'GET') return;
  if (new URL(request.url).origin !== self.location.origin) return;

  // Pages always come from the server. If the network is gone, say so plainly
  // rather than showing a stale dashboard that looks live.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => new Response(
        '<!doctype html><meta charset="utf-8">'
        + '<meta name="viewport" content="width=device-width,initial-scale=1">'
        + '<title>Smart Stock is offline</title>'
        + '<style>body{font-family:system-ui,sans-serif;margin:0;padding:48px 24px;'
        + 'background:#FBF9F6;color:#1A1715;text-align:center}'
        + 'h1{font-size:20px;margin:24px 0 8px}p{color:#5C534D;line-height:1.5;max-width:28em;margin:0 auto}</style>'
        + '<h1>Smart Stock cannot be reached</h1>'
        + '<p>This phone is not on the shop Wi-Fi, or the shop computer is switched off. '
        + 'Stock figures are never shown from memory, so nothing is displayed until the '
        + 'connection is back.</p>',
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
      ))
    );
    return;
  }

  // Static files: network first, cache only as a safety net.
  event.respondWith(
    fetch(request)
      .then((response) => {
        if (response && response.ok) {
          const copy = response.clone();
          caches.open(VERSION).then((cache) => cache.put(request, copy)).catch(() => null);
        }
        return response;
      })
      .catch(() => caches.match(request))
  );
});
