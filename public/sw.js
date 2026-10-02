/**
 * File: public/sw.js
 * Tujuan: Service Worker untuk kapabilitas Progressive Web App (PWA) Menuju Haramain (Installability, lifecycle caching, dan network passthrough)
 * Dipakai Oleh: Browser Client / Web App Manifest
 * Dependensi Utama: Service Worker API CacheStorage
 * Daftar Event Listener: install, activate, fetch
 * Side Effect: Pendaftaran offline shell cache dan background client claim
 */

const CACHE_NAME = 'menuju-haramain-v1';
const ASSETS_TO_CACHE = [
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon.svg'
];

// Install Event: Cache basic assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE);
        }).then(() => self.skipWaiting())
    );
});

// Activate Event: Cleanup older caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event: Network-first approach with cache fallback for static assets
self.addEventListener('fetch', (event) => {
    // Only handle GET requests and avoid intercepting non-http(s) schemas
    if (event.request.method !== 'GET' || !event.request.url.startsWith('http')) {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});
