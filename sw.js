const CACHE_NAME  = 'adslife-assets-v3';
const ASSET_REGEX = /\/assets\//;

// Install — nothing to pre-cache
self.addEventListener('install', () => self.skipWaiting());

// Activate — wipe every old cache
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  // Never intercept non-GET or cross-origin
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Hashed static assets — Cache First (safe: filename changes on rebuild)
  if (ASSET_REGEX.test(url.pathname)) {
    event.respondWith(
      caches.match(request).then(
        (cached) => cached || fetch(request).then((res) => {
          if (res.ok) {
            const clone = res.clone();
            caches.open(CACHE_NAME).then((c) => c.put(request, clone));
          }
          return res;
        })
      )
    );
    return;
  }

  // Everything else (HTML, API, PHP) — Network Only, no caching
});

// Push notification handler
self.addEventListener('push', (event) => {
  const data = event.data?.json() ?? {};
  const title = data.notification?.title ?? data.title ?? 'AdsLife';
  const body  = data.notification?.body  ?? data.body  ?? '';
  const offerIdStr = data.data?.offer_id ?? data.offer_id ?? '';

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon:  '/favicon.svg',
      badge: '/favicon.svg',
      data:  { offer_id: offerIdStr },
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const offerId = event.notification.data?.offer_id;
  const url = offerId ? `/offer/${offerId}` : '/feed';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.focus();
          client.navigate(url);
          return;
        }
      }
      clients.openWindow(url);
    })
  );
});

self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-interactions') event.waitUntil(Promise.resolve());
});
