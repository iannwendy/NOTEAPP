const SW_VERSION = '1.0.5';
const CACHE_NAME = 'notes-app-cache-v2';
const STATIC_ASSETS = [
  '/',
  '/css/app.css',
  '/css/custom.css',
  '/css/offline.css',
  '/js/app.js',
  '/js/refresh-cache.js',
  '/js/fix-list-view.js',
  '/build/assets/app-BRCbVPIg.css',
  '/build/assets/database-xs-7IxTZ.js',
  '/build/assets/sync-DVLp6R6S.js', 
  '/build/assets/app-DuGOdoSz.js',
  '/icons/icon-72x72.png',
  '/icons/icon-96x96.png',
  '/icons/icon-128x128.png',
  '/icons/icon-144x144.png',
  '/icons/icon-152x152.png',
  '/icons/icon-192x192.png',
  '/icons/icon-384x384.png',
  '/icons/icon-512x512.png',
  '/manifest.json',
  '/favicon.ico',
  '/offline.html',
  'https://fonts.bunny.net/css?family=Nunito',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Cài đặt Service Worker
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installing Service Worker...', event);
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Caching app shell and static assets');
        return cache.addAll(STATIC_ASSETS).catch(error => {
          console.error('[Service Worker] Cache addAll error:', error);
          // Tiếp tục ngay cả khi có lỗi với một số tài nguyên
          return Promise.resolve();
        });
      })
  );
  self.skipWaiting();
});

// Kích hoạt (và dọn dẹp cache cũ)
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activating Service Worker...', event);
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.filter((cacheName) => {
          return cacheName !== CACHE_NAME && cacheName.startsWith('notes-app-cache');
        }).map((cacheName) => {
          console.log('[Service Worker] Deleting old cache:', cacheName);
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
      if (cacheResponse) {
        console.log('[Service Worker] Found in cache:', request.url);
        return cacheResponse;
      }
      
      console.log('[Service Worker] Not found in cache, fetching:', request.url);
      return fetch(request)
        .then((networkResponse) => {
          if (!networkResponse || networkResponse.status !== 200) {
            console.log('[Service Worker] Got bad response:', networkResponse ? networkResponse.status : 'null');
            return networkResponse;
          }
          
          return caches.open(CACHE_NAME)
            .then((cache) => {
              console.log('[Service Worker] Caching new resource:', request.url);
              cache.put(request, networkResponse.clone());
              return networkResponse;
            });
        })
        .catch((error) => {
          console.error('[Service Worker] Fetch failed:', error);
          
          // Nếu là request cho hình ảnh, thử trả về placeholder
          if (request.url.match(/\.(jpg|jpeg|png|gif|svg)$/i)) {
            return caches.match('/icons/icon-72x72.png');
          }
          
          // Nếu là request cho stylesheet, trả về stylesheet trống
          if (request.url.match(/\.css$/i)) {
            return new Response('/* Failed to load stylesheet - offline mode */', {
              headers: { 'Content-Type': 'text/css' }
            });
          }
          
          return new Response('Offline and resource not cached', {
            status: 503,
            statusText: 'Service Unavailable'
          });
        });
    });
};

// Chiến lược 'Network First, Cache Fallback' cho API requests
const networkFirstStrategy = (request) => {
  console.log('[Service Worker] Network-first strategy for:', request.url);
  
  return fetch(request)
    .then((networkResponse) => {
      // Lưu ý: Clone response trước khi sử dụng vì nó chỉ có thể được đọc một lần
      const responseClone = networkResponse.clone();
      
      caches.open(CACHE_NAME).then((cache) => {
        // Chỉ cache các response thành công
        if (networkResponse.ok) {
          console.log('[Service Worker] Caching network response for:', request.url);
          cache.put(request, responseClone);
        }
      });
      
      return networkResponse;
    })
    .catch((error) => {
      console.log('[Service Worker] Network request failed, trying cache:', request.url, error);
      
      return caches.match(request).then((cacheResponse) => {
        // Nếu có trong cache
        if (cacheResponse) {
          console.log('[Service Worker] Found in cache while offline:', request.url);
          return cacheResponse;
        }
        
        // Nếu là request API notes và không có cache, trả về từ IndexedDB
        if (request.url.includes('/notes')) {
          console.log('[Service Worker] API request while offline, returning offline response');
          return new Response(JSON.stringify({ 
            offline: true,
            message: 'Data retrieved from offline storage' 
          }), {
            headers: { 'Content-Type': 'application/json' }
          });
        }
        
        // Trả về trang offline.html nếu request HTML
        if (request.mode === 'navigate') {
          console.log('[Service Worker] Returning offline page for navigation request');
          return caches.match('/offline.html');
        }
        
        // Trả về response lỗi generic cho các request khác
        console.log('[Service Worker] No cache available for:', request.url);
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
  
  try {
    const url = new URL(request.url);
    
    // Bỏ qua các request không phải HTTP/HTTPS
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
      return;
    }
    
    // Bỏ qua các request đến các dịch vụ bên ngoài mà không định cache
    if (!url.host.includes(self.location.host) && 
        !url.host.includes('fonts.bunny.net') && 
        !url.host.includes('cdn.jsdelivr.net') && 
        !url.host.includes('cdnjs.cloudflare.com')) {
      console.log('[Service Worker] Ignoring external request:', request.url);
      return;
    }
    
    console.log('[Service Worker] Fetch event for:', request.url);
    
    // Xử lý các API request và HTML request riêng biệt
    if (request.url.includes('/api/') || 
        (request.headers.get('accept') && request.headers.get('accept').includes('application/json'))) {
      // Xử lý request API
      event.respondWith(networkFirstStrategy(request));
    } else if (request.mode === 'navigate' || request.url.endsWith('.html')) {
      // Xử lý navigation request
      event.respondWith(networkFirstStrategy(request));
    } else {
      // Xử lý các tài nguyên tĩnh
      event.respondWith(cacheFirstStrategy(request));
    }
  } catch (error) {
    console.error('[Service Worker] Error in fetch handler:', error);
  }
});

// Xử lý đồng bộ hóa khi online trở lại
self.addEventListener('sync', (event) => {
  console.log('[Service Worker] Background Sync event:', event.tag);
  
  if (event.tag === 'sync-notes') {
    event.waitUntil(syncNotes());
  }
});

// Hàm đồng bộ hóa ghi chú từ IndexedDB với server
function syncNotes() {
  console.log('[Service Worker] Syncing notes...');
  
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
  console.log('[Service Worker] Push received:', event);
  
  let data = { title: 'Notes App', body: 'Có cập nhật mới cho ghi chú của bạn' };
  
  try {
    if (event.data) {
      data = event.data.json();
    }
  } catch (e) {
    console.error('[Service Worker] Error parsing push data:', e);
  }
  
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
  console.log('[Service Worker] Notification click:', event);
  
  event.notification.close();
  
  event.waitUntil(
    clients.openWindow(event.notification.data.url)
  );
}); 