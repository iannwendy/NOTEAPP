const CACHE_NAME = 'notes-app-cache-v1';
const STATIC_ASSETS = [
  '/',
  '/css/app.css',
  '/js/app.js',
  '/js/manifest.js',
  '/js/vendor.js',
  '/manifest.json',
  '/favicon.ico',
  '/offline.html'
];

// Cài đặt Service Worker
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Opened cache');
        return cache.addAll(STATIC_ASSETS);
      })
  );
  self.skipWaiting();
});

// Kích hoạt (và dọn dẹp cache cũ)
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.filter((cacheName) => {
          return cacheName !== CACHE_NAME;
        }).map((cacheName) => {
          return caches.delete(cacheName);
        })
      );
    })
  );
  self.clients.claim();
});

// Chiến lược 'Cache First, Network Fallback' cho static assets
const cacheFirstStrategy = (request) => {
  return caches.match(request)
    .then((cacheResponse) => {
      return cacheResponse || fetch(request).then((networkResponse) => {
        return caches.open(CACHE_NAME).then((cache) => {
          cache.put(request, networkResponse.clone());
          return networkResponse;
        });
      });
    });
};

// Chiến lược 'Network First, Cache Fallback' cho API requests
const networkFirstStrategy = (request) => {
  return fetch(request)
    .then((networkResponse) => {
      const responseClone = networkResponse.clone();
      caches.open(CACHE_NAME).then((cache) => {
        cache.put(request, responseClone);
      });
      return networkResponse;
    })
    .catch(() => {
      return caches.match(request).then((cacheResponse) => {
        if (cacheResponse) {
          return cacheResponse;
        }
        // Nếu là request API notes và không có cache, trả về từ IndexedDB
        if (request.url.includes('/notes')) {
          return new Response(JSON.stringify({ 
            offline: true,
            message: 'Data retrieved from offline storage' 
          }), {
            headers: { 'Content-Type': 'application/json' }
          });
        }
        
        // Trả về trang offline.html nếu không có cache và không lấy được từ network
        if (request.mode === 'navigate') {
          return caches.match('/offline.html');
        }
        
        return new Response('Offline and no cache available', {
          status: 503,
          statusText: 'Service Unavailable'
        });
      });
    });
};

// Xử lý sự kiện fetch
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);
  
  // Bỏ qua các request không phải HTTP/HTTPS
  if (url.protocol !== 'http:' && url.protocol !== 'https:') {
    return;
  }

  // Xử lý các API request và HTML request riêng biệt
  if (request.url.includes('/api/') || (request.headers.get('accept') && request.headers.get('accept').includes('application/json'))) {
    event.respondWith(networkFirstStrategy(request));
  } else if (request.mode === 'navigate' || request.url.endsWith('.html')) {
    event.respondWith(networkFirstStrategy(request));
  } else {
    event.respondWith(cacheFirstStrategy(request));
  }
});

// Xử lý đồng bộ hóa khi online trở lại
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-notes') {
    event.waitUntil(syncNotes());
  }
});

// Hàm đồng bộ hóa ghi chú từ IndexedDB với server
function syncNotes() {
  return self.clients.matchAll().then((clients) => {
    clients.forEach(client => {
      client.postMessage({
        type: 'SYNC_STARTED'
      });
    });
    
    // Đồng bộ hóa sẽ được thực hiện từ ứng dụng chính
    return new Promise((resolve, reject) => {
      setTimeout(() => {
        self.clients.matchAll().then((clients) => {
          clients.forEach(client => {
            client.postMessage({
              type: 'SYNC_COMPLETED'
            });
          });
          resolve();
        });
      }, 1000);
    });
  });
}

// Xử lý push notifications
self.addEventListener('push', (event) => {
  const data = event.data.json();
  
  const options = {
    body: data.body || 'Có cập nhật mới cho ghi chú của bạn',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/icon-72x72.png',
    data: {
      url: data.url || '/'
    }
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title || 'Notes App', options)
  );
});

// Xử lý khi người dùng click vào notification
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  
  event.waitUntil(
    clients.openWindow(event.notification.data.url)
  );
}); 