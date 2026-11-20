// Service worker de la app. Solo guarda en caché lo estático (íconos y la página sin conexión).
// Los datos de pacientes, citas y cobros SIEMPRE se piden al servidor: nunca se guardan en el teléfono.
const VERSION = 'crm-v1';
const ESTATICOS = ['offline.html', 'icons/icon-192.png', 'icons/icon-512.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(ESTATICOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((k) => Promise.all(k.filter((x) => x !== VERSION).map((x) => caches.delete(x))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;                       // POST (guardar, cobrar) va directo al servidor
  if (req.mode === 'navigate') {                          // páginas: red primero; sin internet, aviso
    e.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }
  const url = new URL(req.url);
  if (url.origin === location.origin && url.pathname.includes('/icons/')) {
    e.respondWith(caches.match(req).then((r) => r || fetch(req)));
  }
});
