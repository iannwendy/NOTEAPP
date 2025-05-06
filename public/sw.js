const SW_VERSION = '1.0.5';
const CACHE_NAME = 'notes-app-cache-v5';
const STATIC_ASSETS = [
  '/',
  '/offline.html',
  '/css/app.css',
  '/css/custom.css',
  '/css/offline.css',
  '/js/app.js',
  '/js/manifest.js',
  '/js/vendor.js',
  '/favicon.ico',
  '/manifest.json',
  '/icons/icon-72x72.png',
  '/icons/icon-96x96.png',
  '/icons/icon-128x128.png',
  '/icons/icon-144x144.png',
  '/icons/icon-152x152.png',
  '/icons/icon-192x192.png',
  '/icons/icon-384x384.png',
  '/icons/icon-512x512.png'
];

// Cài đặt Service Worker
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Opened cache');
        return cache.addAll(STATIC_ASSETS);
      })
      .catch(error => {
        console.error('Failed to cache static assets:', error);
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
          return cacheName.startsWith('notes-app-cache') && cacheName !== CACHE_NAME;
        }).map((cacheName) => {
          console.log('Deleting old cache:', cacheName);
          return caches.delete(cacheName);
        })
      );
    }).then(() => {
      // Thông báo cho client biết service worker đã cập nhật
      self.clients.matchAll().then(clients => {
        clients.forEach(client => {
          client.postMessage({
            type: 'SW_UPDATED',
            version: SW_VERSION
          });
        });
      });
    })
  );
  self.clients.claim();
});

// Chiến lược 'Cache First, Network Fallback' cho static assets
const cacheFirstStrategy = (request) => {
  return caches.match(request)
    .then((cacheResponse) => {
      if (cacheResponse) {
        return cacheResponse;
      }
      return fetch(request)
        .then((networkResponse) => {
          // Chỉ cache các response hợp lệ
          if (networkResponse && networkResponse.status === 200) {
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, responseToCache);
            });
          }
          return networkResponse;
        })
        .catch(error => {
          console.error('Fetch failed:', error);
          // Nếu là request yêu cầu trang, trả về trang offline
          if (request.mode === 'navigate') {
            return caches.match('/offline.html');
          }
          
          // Xử lý CSS mặc định
          if (request.url.endsWith('.css')) {
            return new Response('/* Fallback CSS */', {
              headers: { 'Content-Type': 'text/css' }
            });
          }
          
          return new Response('Network error happened', {
            status: 408,
            headers: { 'Content-Type': 'text/plain' }
          });
        });
    });
};

// Chiến lược 'Network First, Cache Fallback' cho API requests
const networkFirstStrategy = (request) => {
  return fetch(request)
    .then((networkResponse) => {
      // Clone response trước khi sử dụng
      const responseClone = networkResponse.clone();
      
      // Chỉ cache các response hợp lệ
      if (networkResponse.status === 200) {
        caches.open(CACHE_NAME).then((cache) => {
          cache.put(request, responseClone);
        });
      }
      
      return networkResponse;
    })
    .catch((error) => {
      console.log('Fetch failed, trying cache...', error);
      return caches.match(request).then((cacheResponse) => {
        // Nếu có trong cache, trả về từ cache
        if (cacheResponse) {
          return cacheResponse;
        }
        
        // Nếu là request API notes và không có cache, trả về từ IndexedDB
        if (request.url.includes('/notes') || request.url.includes('/api/')) {
          return new Response(JSON.stringify({ 
            offline: true,
            message: 'Data retrieved from offline storage' 
          }), {
            headers: { 'Content-Type': 'application/json' }
          });
        }
        
        // Trả về trang offline.html cho các navigation request
        if (request.mode === 'navigate') {
          return caches.match('/offline.html');
        }
        
        // Xử lý các resource khác
        const url = new URL(request.url);
        if (url.pathname.match(/\.(js|css|png|jpg|jpeg|gif|svg|ico)$/)) {
          // Trả về CSS trống cho các request CSS để tránh lỗi
          if (url.pathname.endsWith('.css')) {
            return new Response('/* Empty CSS fallback */', {
              headers: { 'Content-Type': 'text/css' }
            });
          }
          
          return new Response('Not available offline', {
            status: 404,
            headers: { 'Content-Type': 'text/plain' }
          });
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

  // Bỏ qua các request đến các tài nguyên khác tên miền (để tránh CORS issues)
  if (url.origin !== self.location.origin) {
    return;
  }

  // Trường hợp đặc biệt cho file offline.css - luôn lấy từ cache hoặc network
  if (url.pathname === '/css/offline.css') {
    event.respondWith(cacheFirstStrategy(request));
    return;
  }

  // Xử lý các static assets (CSS, JS, images)
  if (url.pathname.match(/\.(js|css|png|jpg|jpeg|gif|svg|ico)$/)) {
    event.respondWith(cacheFirstStrategy(request));
    return;
  }

  // Xử lý các API request và HTML request riêng biệt
  if (request.url.includes('/api/') || 
      (request.headers.get('accept') && 
       request.headers.get('accept').includes('application/json'))) {
    event.respondWith(networkFirstStrategy(request));
  } else if (request.mode === 'navigate' || request.destination === 'document') {
    event.respondWith(networkFirstStrategy(request));
  } else {
    event.respondWith(cacheFirstStrategy(request));
  }
});

// Xử lý tin nhắn từ clients
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'CHECK_VERSION') {
    event.ports[0].postMessage({
      type: 'VERSION_INFO',
      version: SW_VERSION
    });
  }
  
  if (event.data && event.data.type === 'ONLINE_STATUS_CHANGE') {
    if (event.data.online) {
      console.log('Client is back online, starting sync...');
      self.registration.sync.register('sync-notes')
        .catch(err => console.error('Sync registration failed:', err));
    } else {
      console.log('Client went offline');
    }
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
  const data = event.data ? event.data.json() : { title: 'Notification' };
  
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