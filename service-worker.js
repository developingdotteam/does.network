const CACHE_NAME = 'does-network-cache-v3.2.9'; // Increment this to force updates

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll([
        '/',
        '/sofiaPro.otf',
        '/sofiaProBold.otf',
        '/index.html',
      ]);
    })
  );
  self.skipWaiting(); // Activate immediately
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((cache) => cache !== CACHE_NAME)
          .map((cache) => caches.delete(cache))
      );
    })
  );
  self.clients.claim(); // Take control of open pages immediately
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    fetch(event.request) // Try network first
      .then((response) => {
        return caches.open(CACHE_NAME).then((cache) => {
          cache.put(event.request, response.clone()); // Update cache with fresh version
          return response;
        });
      })
      .catch(() => caches.match(event.request)) // Use cache only when offline
  );
});
