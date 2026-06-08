/**
 * Aura Gifts Service Worker
 * Caches HTML, CSS, JS and fonts for instant page loads with no flicker.
 * HTML: stale-while-revalidate (serve cached instantly, update in background)
 * Assets: cache-first (CSS/JS/fonts never change without version bump)
 */

const CACHE_NAME = 'aura-v5';

self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(k) { return k !== CACHE_NAME; })
                    .map(function(k) { return caches.delete(k); })
            );
        }).then(function() { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function(event) {
    var req = event.request;
    var url = req.url;

    if (req.method !== 'GET') return;

    // Skip API calls — always fresh
    if (url.includes('/api/')) return;

    // Skip admin panel
    if (url.includes('/admin')) return;

    var isHtml = req.headers.get('accept') && req.headers.get('accept').includes('text/html');
    var isCss  = url.includes('.css');
    var isJs   = url.includes('.js');
    var isCdn  = url.includes('cdnjs.cloudflare.com') || url.includes('fonts.googleapis.com') || url.includes('fonts.gstatic.com');
    // Don't cache Cloudinary images — let them load normally
    var isImg  = false;

    if (isHtml) {
        // Cache-first for HTML: serve from cache instantly, only fetch if not cached
        event.respondWith(
            caches.open(CACHE_NAME).then(function(cache) {
                return cache.match(req).then(function(cached) {
                    if (cached) {
                        // Serve from cache, update in background silently
                        fetch(req).then(function(response) {
                            if (response && response.status === 200) {
                                cache.put(req, response.clone());
                            }
                        }).catch(function() {});
                        return cached;
                    }
                    // Not cached — fetch and cache
                    return fetch(req).then(function(response) {
                        if (response && response.status === 200) {
                            cache.put(req, response.clone());
                        }
                        return response;
                    }).catch(function() { return new Response('', {status: 503}); });
                });
            })
        );
        return;
    }

    if (isCss || isJs || isCdn) {
        // Cache-first for assets
        event.respondWith(
            caches.open(CACHE_NAME).then(function(cache) {
                return cache.match(req).then(function(cached) {
                    if (cached) return cached;
                    return fetch(req).then(function(response) {
                        if (response && response.status === 200) {
                            cache.put(req, response.clone());
                        }
                        return response;
                    }).catch(function() { return new Response('', {status: 503}); });
                });
            })
        );
    }
});
