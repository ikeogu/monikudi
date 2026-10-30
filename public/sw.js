/*
 * Grace Daily Contribution service worker.
 *
 * - Pages always come from the network: they carry live balances and a CSRF token,
 *   and caching them would leave customer data on shared phones.
 * - Hashed Vite assets are cached forever, so the app shell loads instantly.
 * - When the network is down, full page loads fall back to /offline.html.
 *
 * Bump VERSION when this file's caching rules change.
 */
const VERSION = 'v1';
const STATIC_CACHE = `grace-static-${VERSION}`;
const ASSET_CACHE = 'grace-assets';
const MAX_ASSETS = 150;
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/manifest.webmanifest', '/img/favicon.svg', '/img/pwa-192x192.png', '/img/pwa-512x512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('grace-static-') && key !== STATIC_CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    if (url.origin === 'https://fonts.bunny.net') {
        event.respondWith(staleWhileRevalidate(request));
        return;
    }
    if (url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(cacheFirst(request));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (url.pathname.startsWith('/img/')) {
        event.respondWith(staleWhileRevalidate(request));
    }

    // Everything else (Inertia visits, customer search, downloads) goes straight to the network.
});

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    const response = await fetch(request);
    if (response.ok) {
        const cache = await caches.open(ASSET_CACHE);
        await cache.put(request, response.clone());
        trim(cache);
    }
    return response;
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request);
    const network = fetch(request)
        .then((response) => {
            if (response.ok || response.type === 'opaque') cache.put(request, response.clone());
            return response;
        })
        .catch(() => cached);

    return cached || network;
}

// Old builds' assets pile up across deploys; keep only the most recent ones.
async function trim(cache) {
    const keys = await cache.keys();
    await Promise.all(keys.slice(0, Math.max(0, keys.length - MAX_ASSETS)).map((key) => cache.delete(key)));
}
