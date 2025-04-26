// Service Worker for Notes App
const CACHE_NAME = 'notes-app-v1';
const DYNAMIC_CACHE = 'notes-app-dynamic-v1';

// Resources to cache on install
const STATIC_ASSETS = [
    '/',
    '/offline',
    '/css/app.css',
    '/js/app.js',
    '/js/refresh-cache.js',
    '/js/fix-list-view.js',
    '/css/custom.css',
    '/favicon.ico',
    'https://fonts.bunny.net/css?family=Nunito',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Install event - cache static assets
self.addEventListener('install', event => {
    console.log('[Service Worker] Installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[Service Worker] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('[Service Worker] Installation complete');
                return self.skipWaiting();
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('[Service Worker] Activating...');
    
    const currentCaches = [CACHE_NAME, DYNAMIC_CACHE];
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return cacheNames.filter(cacheName => !currentCaches.includes(cacheName));
            })
            .then(cachesToDelete => {
                return Promise.all(cachesToDelete.map(cacheToDelete => {
                    console.log('[Service Worker] Deleting old cache:', cacheToDelete);
                    return caches.delete(cacheToDelete);
                }));
            })
            .then(() => {
                console.log('[Service Worker] Activation complete');
                return self.clients.claim();
            })
    );
});

// Helper function to check if a request is for an API route
const isApiRequest = url => {
    return url.pathname.startsWith('/api/') || 
           url.pathname.includes('/notes/') && 
           (url.pathname.includes('/real-time-update') || 
            url.pathname.includes('/heartbeat'));
};

// Helper function to determine if we should try to fetch from network first
const shouldFetchFromNetworkFirst = request => {
    const url = new URL(request.url);
    
    // For API requests and POST/PUT/DELETE requests, try network first
    return isApiRequest(url) || 
           request.method !== 'GET' || 
           url.pathname.includes('/login') || 
           url.pathname.includes('/register');
};

// Helper function to handle note-specific requests
const handleNoteRequest = async (request) => {
    const url = new URL(request.url);
    
    try {
        // Try network first for note requests to get fresh content
        const networkResponse = await fetch(request);
        
        // If successful, cache the response and return it
        if (networkResponse.ok) {
            const responseToCache = networkResponse.clone();
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, responseToCache);
            return networkResponse;
        }
    } catch (error) {
        console.log('[Service Worker] Network request failed, trying cache', error);
    }
    
    // If network failed, try the cache
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }
    
    // If no cached response, check if we're offline and serve a general offline page
    if (url.pathname.startsWith('/notes/')) {
        return caches.match('/offline');
    }
    
    // Otherwise return an error response
    return new Response('No cached data available', { status: 404, headers: { 'Content-Type': 'text/plain' } });
};

// Fetch event - intercept network requests
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    
    // Skip cross-origin requests
    if (url.origin !== self.location.origin && !url.href.includes('fonts.bunny.net') && !url.href.includes('cdn.jsdelivr.net') && !url.href.includes('cdnjs.cloudflare.com')) {
        return;
    }

    // For API requests and mutations, use network-first approach
    if (shouldFetchFromNetworkFirst(event.request)) {
        event.respondWith(
            fetch(event.request)
                .catch(error => {
                    console.log('[Service Worker] Network request failed:', error);
                    
                    // For GET requests, try the cache
                    if (event.request.method === 'GET') {
                        return caches.match(event.request);
                    }
                    
                    // For other methods, we'll put the request in IndexedDB for later sync
                    // (this will be handled by the sync manager in the app code)
                    return new Response(JSON.stringify({ error: 'You are offline. This action will be synced when you come back online.' }), 
                                      { status: 503, headers: { 'Content-Type': 'application/json' } });
                })
        );
        return;
    }
    
    // For note specific pages, use a custom handler
    if (url.pathname.startsWith('/notes/')) {
        event.respondWith(handleNoteRequest(event.request));
        return;
    }
    
    // For other GET requests, use cache-first approach
    event.respondWith(
        caches.match(event.request)
            .then(cachedResponse => {
                if (cachedResponse) {
                    // Return cached response
                    return cachedResponse;
                }
                
                // If not in cache, fetch from network
                return fetch(event.request)
                    .then(networkResponse => {
                        // Cache the network response for future
                        const responseToCache = networkResponse.clone();
                        caches.open(DYNAMIC_CACHE)
                            .then(cache => {
                                cache.put(event.request, responseToCache);
                            });
                        
                        return networkResponse;
                    })
                    .catch(error => {
                        console.log('[Service Worker] Fetch failed:', error);
                        
                        // If offline and requesting an HTML page, show offline page
                        if (event.request.headers.get('accept').includes('text/html')) {
                            return caches.match('/offline');
                        }
                        
                        // Otherwise return an error
                        return new Response('You are offline and this resource is not cached.', 
                                           { status: 503, headers: { 'Content-Type': 'text/plain' } });
                    });
            })
    );
});

// Sync event - handle background sync when connection is restored
self.addEventListener('sync', event => {
    console.log('[Service Worker] Sync event received:', event.tag);
    
    if (event.tag === 'sync-notes') {
        event.waitUntil(
            // This will be implemented to sync pending changes from IndexedDB
            self.syncPendingChanges()
        );
    }
});

// Function to sync pending changes when back online
self.syncPendingChanges = async () => {
    console.log('[Service Worker] Syncing pending changes');
    
    // We'll post a message to the client to handle the sync from there
    // since IndexedDB is more easily accessible from the client
    const clients = await self.clients.matchAll();
    
    clients.forEach(client => {
        client.postMessage({
            type: 'SYNC_REQUIRED'
        });
    });
}; 