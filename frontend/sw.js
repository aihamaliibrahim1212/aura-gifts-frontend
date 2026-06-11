// Service Worker - caches CSS/JS only
self.addEventListener('install', function(event) {
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  event.waitUntil(clients.claim());
});

self.addEventListener('fetch', function(event) {
  // Only cache CSS and JS files
  if (!event.request.url.includes('.css') && !event.request.url.includes('.js')) {
    return;
  }

  event.respondWith(
    caches.open('aura-v1').then(function(cache) {
      return cache.match(event.request).then(function(response) {
        return response || fetch(event.request).then(function(response) {
          cache.put(event.request, response.clone());
          return response;
        });
      });
    })
  );
});
