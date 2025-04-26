// Notes App Service Worker
const CACHE_NAME = 'notes-app-v1';
const STATIC_ASSETS = [
  '/',
  '/css/custom.css',
  '/js/fix-list-view.js',
  '/js/refresh-cache.js',
  '/js/offline.js',
  '/js/db.js',
  '/manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://fonts.bunny.net/css?family=Nunito'
];

// Install event - Cache static assets
self.addEventListener('install', event => {
  console.log('[Service Worker] Installing Service Worker...', event);
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[Service Worker] Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => {
        return self.skipWaiting();
      })
  );
});

// Activate event - Clean up old caches
self.addEventListener('activate', event => {
  console.log('[Service Worker] Activating Service Worker...', event);
  event.waitUntil(
    caches.keys()
      .then(keyList => {
        return Promise.all(keyList.map(key => {
          if (key !== CACHE_NAME) {
            console.log('[Service Worker] Removing old cache', key);
            return caches.delete(key);
          }
        }));
      })
      .then(() => {
        return self.clients.claim();
      })
  );
  return self.clients.claim();
});

// Fetch event - Serve cached content when offline
self.addEventListener('fetch', event => {
  // Skip cross-origin requests
  if (!event.request.url.startsWith(self.location.origin) && 
      !event.request.url.startsWith('https://fonts.bunny.net') && 
      !event.request.url.startsWith('https://cdn.jsdelivr.net') && 
      !event.request.url.startsWith('https://cdnjs.cloudflare.com')) {
    return;
  }

  // Only cache GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // Handle API requests - important for offline data
  if (event.request.url.includes('/api/notes') || event.request.url.includes('/notes')) {
    handleApiRequest(event);
    return;
  }

  // For normal page navigations and static assets
  event.respondWith(
    caches.match(event.request)
      .then(cachedResponse => {
        if (cachedResponse) {
          return cachedResponse;
        }

        return fetch(event.request)
          .then(response => {
            // Don't cache non-successful responses
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // Clone the response
            const responseToCache = response.clone();

            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });

            return response;
          })
          .catch(err => {
            // Return the offline page if the resource isn't available
            if (event.request.headers.get('accept').includes('text/html')) {
              return caches.match('/offline.html');
            }
            console.log('[Service Worker] Fetch failed; returning offline fallback.', err);
          });
      })
  );
});

// Handle API requests with network-first strategy and fallback to IndexedDB
function handleApiRequest(event) {
  event.respondWith(
    fetch(event.request)
      .then(response => {
        return response;
      })
      .catch(err => {
        console.log('[Service Worker] API fetch failed; returning from IndexedDB.', err);
        
        // Parse the URL to determine what data is being requested
        const url = new URL(event.request.url);
        
        // Handle requests for notes data
        if (url.pathname.includes('/notes') && !url.pathname.includes('/create')) {
          // Access IndexedDB and return cached data
          return new Response(JSON.stringify({
            message: 'This data is served from the local database.',
            offline: true,
            useIndexedDB: true
          }), {
            headers: { 'Content-Type': 'application/json' }
          });
        }
        
        // Generic offline response for other API requests
        return new Response(JSON.stringify({
          message: 'You are currently offline.',
          offline: true
        }), {
          headers: { 'Content-Type': 'application/json' }
        });
      })
  );
}

// Sync event - Background sync when online
self.addEventListener('sync', event => {
  console.log('[Service Worker] Background Syncing', event);
  if (event.tag === 'sync-notes') {
    event.waitUntil(
      syncNotes()
    );
  }
});

// Function to sync notes with the server
function syncNotes() {
  // This is a placeholder that will be implemented in the db.js file
  console.log('[Service Worker] Syncing notes with server');
  // The actual sync implementation will be triggered from the main application
  return Promise.resolve();
}

// Push notification event
self.addEventListener('push', event => {
  console.log('[Service Worker] Push Notification received', event);

  let data = { title: 'New Notification', content: 'Something new happened!' };
  
  if (event.data) {
    data = JSON.parse(event.data.text());
  }

  const options = {
    body: data.content,
    icon: '/icons/icon-96x96.png',
    badge: '/icons/icon-72x72.png',
    data: {
      url: data.openUrl || '/'
    }
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
}); 