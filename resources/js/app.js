import './bootstrap';
import { offlineDB } from './database';
import { syncManager } from './sync';

// Lắng nghe sự kiện lỗi toàn cục để xử lý lỗi kết nối
window.addEventListener('error', function(event) {
    console.error('Error caught:', event);
    if (event.message === 'NetworkError' || 
        event.message.includes('network') || 
        event.message.includes('Network Error')) {
        handleNetworkError();
    }
});

// Xử lý lỗi kết nối mạng
function handleNetworkError() {
    if (!navigator.onLine) {
        showOfflineNotification();
    }
}

// Hiển thị thông báo ngoại tuyến
function showOfflineNotification() {
    const notification = document.getElementById('offline-notification');
    if (!notification) {
        const newNotification = document.createElement('div');
        newNotification.id = 'offline-notification';
        newNotification.className = 'offline-notification visible';
        
        newNotification.innerHTML = `
            <div class="notification-content">
                <div class="notification-title">Mất kết nối!</div>
                <div class="notification-message">Ứng dụng sẽ chuyển sang chế độ offline. Các thay đổi của bạn sẽ được lưu cục bộ và đồng bộ hóa khi có kết nối internet trở lại.</div>
            </div>
            <button class="notification-close">&times;</button>
        `;
        
        document.body.appendChild(newNotification);
        
        const closeButton = newNotification.querySelector('.notification-close');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                newNotification.classList.remove('visible');
            });
        }
    } else {
        notification.classList.add('visible');
    }
}

// Đăng ký Service Worker nếu trình duyệt hỗ trợ
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('Service Worker đã được đăng ký thành công với phạm vi:', registration.scope);
                
                // Kiểm tra cập nhật
                checkForUpdates(registration);
                
                // Thiết lập kiểm tra cập nhật định kỳ (mỗi giờ)
                setInterval(() => {
                    registration.update();
                    checkForUpdates(registration);
                }, 60 * 60 * 1000);
                
                // Kiểm tra và đồng bộ hóa nếu cần
                if (navigator.onLine && registration.sync) {
                    registration.sync.register('sync-notes')
                    .catch(error => console.error('Lỗi đăng ký sync:', error));
                }
            })
            .catch(error => {
                console.error('Đăng ký Service Worker thất bại:', error);
            });
            
        // Lắng nghe thông báo từ Service Worker
        navigator.serviceWorker.addEventListener('message', (event) => {
            console.log('Nhận tin nhắn từ Service Worker:', event.data);
            // Xử lý tin nhắn SW_UPDATED - có phiên bản mới
            if (event.data && event.data.type === 'SW_UPDATED') {
                showUpdateNotification();
            }
        });
    });
}

// Kiểm tra phiên bản mới của service worker
function checkForUpdates(registration) {
    // Kiểm tra nếu có waiting worker (phiên bản đã cập nhật nhưng chưa kích hoạt)
    if (registration.waiting) {
        showUpdateNotification();
        return;
    }
    
    // Theo dõi khi có cập nhật
    registration.addEventListener('updatefound', () => {
        // Lấy service worker mới đang cài đặt 
        const newWorker = registration.installing;
        
        // Theo dõi trạng thái cài đặt
        newWorker.addEventListener('statechange', () => {
            // Nếu cài đặt hoàn tất, hiển thị thông báo
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                showUpdateNotification();
            }
        });
    });
}

// Hiển thị thông báo cập nhật và hỏi người dùng có muốn làm mới
function showUpdateNotification() {
    // Kiểm tra xem đã hiển thị thông báo chưa để tránh hiển thị nhiều lần
    if (document.getElementById('update-notification')) {
        return;
    }
    
    const newNotification = document.createElement('div');
    newNotification.id = 'update-notification';
    newNotification.className = 'update-notification visible';
    
    newNotification.innerHTML = `
        <div class="notification-content">
            <div class="notification-title">Cập nhật mới!</div>
            <div class="notification-message">Ứng dụng có phiên bản mới. Làm mới trang để cập nhật?</div>
            <div class="notification-actions">
                <button id="update-now" class="btn btn-primary">Cập nhật ngay</button>
                <button id="update-later" class="btn btn-secondary">Để sau</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(newNotification);
    
    // Xử lý nút cập nhật ngay
    document.getElementById('update-now').addEventListener('click', () => {
        // Gửi tin nhắn tới service worker để bỏ qua chờ đợi và kích hoạt
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ type: 'SKIP_WAITING' });
        }
        
        // Tải lại trang
        window.location.reload();
    });
    
    // Xử lý nút cập nhật sau
    document.getElementById('update-later').addEventListener('click', () => {
        newNotification.classList.remove('visible');
    });
}

// Thêm indicator cho trạng thái offline/online
function addOfflineIndicators() {
    // Kiểm tra xem indicator đã tồn tại chưa
    if (document.querySelector('.offline-indicator')) {
        return;
    }
    
    // Chỉ báo offline
    const offlineIndicator = document.createElement('div');
    offlineIndicator.className = 'offline-indicator';
    offlineIndicator.id = 'offline-indicator';
    offlineIndicator.textContent = 'Ngoại tuyến';
    document.body.appendChild(offlineIndicator);
    
    // Chỉ báo đồng bộ hóa
    const syncIndicator = document.createElement('div');
    syncIndicator.className = 'sync-indicator';
    syncIndicator.id = 'sync-indicator';
    syncIndicator.textContent = 'Đang đồng bộ...';
    document.body.appendChild(syncIndicator);
    
    // Cập nhật trạng thái ban đầu
    updateOfflineStatus();
    
    // Lắng nghe sự kiện đồng bộ từ syncManager
    syncManager.addSyncListener(data => {
        if (data.type === 'SYNC_STATUS_CHANGE') {
            syncIndicator.classList.toggle('visible', data.inProgress);
            if (data.pendingCount) {
                syncIndicator.textContent = `Đang đồng bộ (${data.pendingCount})...`;
            } else {
                syncIndicator.textContent = 'Đang đồng bộ...';
            }
        }
    });
}

// Cập nhật trạng thái offline/online trên giao diện
function updateOfflineStatus() {
    const isOffline = !navigator.onLine;
    document.body.classList.toggle('is-offline', isOffline);
    
    const offlineIndicator = document.querySelector('.offline-indicator');
    if (offlineIndicator) {
        offlineIndicator.classList.toggle('visible', isOffline);
    }
    
    if (isOffline) {
        showOfflineNotification();
    }
}

// Cache ghi chú cho sử dụng offline
function cacheNotesForOffline() {
    // Lấy user ID từ meta tag (nếu đã đăng nhập)
    const userIdMeta = document.querySelector('meta[name="user-id"]');
    if (userIdMeta) {
        const userId = userIdMeta.getAttribute('content');
        if (userId) {
            syncManager.loadAndCacheUserNotes(userId)
                .then(notes => {
                    console.log(`Đã cache ${notes.length} ghi chú cho sử dụng offline`);
                })
                .catch(err => {
                    console.error('Lỗi khi cache ghi chú:', err);
                });
        }
    }
}

// Tự động lưu ghi chú khi soạn thảo trong chế độ offline
function setupOfflineAutoSave() {
    // Xác định form chỉnh sửa ghi chú
    const noteForm = document.querySelector('form.note-form');
    if (!noteForm) return;
    
    // Lấy ID ghi chú (nếu đang chỉnh sửa)
    const noteIdInput = noteForm.querySelector('input[name="note_id"]');
    const noteId = noteIdInput ? noteIdInput.value : null;
    
    // Lấy user ID
    const userIdMeta = document.querySelector('meta[name="user-id"]');
    const userId = userIdMeta ? userIdMeta.getAttribute('content') : null;
    
    if (!userId) return;
    
    // Thiết lập sự kiện tự động lưu
    const titleInput = noteForm.querySelector('input[name="title"]');
    const contentInput = noteForm.querySelector('textarea[name="content"]');
    const colorInput = noteForm.querySelector('input[name="color"]');
    
    if (!titleInput || !contentInput) return;
    
    // Lưu hàm debounce để tránh lưu quá nhiều
    let saveTimeout = null;
    const autoSave = () => {
        if (saveTimeout) clearTimeout(saveTimeout);
        
        saveTimeout = setTimeout(() => {
            const title = titleInput.value.trim();
            const content = contentInput.value.trim();
            const color = colorInput ? colorInput.value : '#ffffff';
            
            if (title && content) {
                const note = {
                    id: noteId || `temp-${Date.now()}`, // Tạo ID tạm nếu là ghi chú mới
                    user_id: userId,
                    title: title,
                    content: content,
                    color: color,
                    updated_at: syncManager.offlineDB ? syncManager.offlineDB.getCurrentTimestamp() : new Date().toISOString()
                };
                
                syncManager.saveNote(note, !noteId)
                    .then(savedNote => {
                        console.log('Đã tự động lưu ghi chú trong chế độ offline', savedNote);
                        // Nếu là ghi chú mới và đã có ID từ server, cập nhật form
                        if (!noteId && savedNote.id && !String(savedNote.id).startsWith('temp-')) {
                            if (noteIdInput) {
                                noteIdInput.value = savedNote.id;
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Lỗi khi tự động lưu ghi chú:', err);
                    });
            }
        }, 2000); // Đợi 2 giây sau lần thay đổi cuối cùng
    };
    
    // Đăng ký sự kiện input
    titleInput.addEventListener('input', autoSave);
    contentInput.addEventListener('input', autoSave);
    if (colorInput) {
        colorInput.addEventListener('change', autoSave);
    }
    
    // Nếu đang chỉnh sửa ghi chú hiện có, tải và lưu vào cache
    if (noteId) {
        syncManager.loadAndCacheNote(noteId)
            .then(note => {
                console.log('Đã cache ghi chú hiện tại cho sử dụng offline', note);
            })
            .catch(err => {
                console.error('Lỗi khi cache ghi chú hiện tại:', err);
            });
    }
}

// Apply user preferences
document.addEventListener('DOMContentLoaded', function() {
    // Thêm chỉ báo offline/online
    addOfflineIndicators();
    
    // Đăng ký sự kiện network
    window.addEventListener('online', () => {
        updateOfflineStatus();
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'ONLINE_STATUS_CHANGE',
                online: true
            });
        }
        
        // Đồng bộ hóa dữ liệu khi online trở lại
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            navigator.serviceWorker.ready.then(registration => {
                registration.sync.register('sync-notes')
                .catch(error => console.error('Lỗi đăng ký sync:', error));
            });
        } else {
            // Nếu không hỗ trợ background sync, thực hiện đồng bộ ngay lập tức
            syncManager.syncAll();
        }
    });
    
    window.addEventListener('offline', () => {
        updateOfflineStatus();
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'ONLINE_STATUS_CHANGE',
                online: false
            });
        }
    });
    
    // Cache ghi chú cho sử dụng offline
    cacheNotesForOffline();
    
    // Thiết lập tự động lưu khi offline
    setupOfflineAutoSave();
    
    // Handle theme toggle in the preferences form
    const themeSelect = document.getElementById('theme');
    if (themeSelect) {
        themeSelect.addEventListener('change', function() {
            if (this.value === 'dark') {
                document.body.classList.add('dark-theme');
            } else {
                document.body.classList.remove('dark-theme');
            }
        });
    }

    // Handle font size change in the preferences form
    const fontSizeSelect = document.getElementById('font_size');
    if (fontSizeSelect) {
        fontSizeSelect.addEventListener('change', function() {
            document.body.classList.remove('small-font', 'medium-font', 'large-font');
            document.body.classList.add(this.value + '-font');
        });
    }
});

/**
 * Format a date string to the user's local timezone
 * 
 * @param {string} dateString - ISO 8601 date string
 * @param {string} format - Optional format (default: full datetime)
 * @return {string} Formatted date string
 */
window.formatLocalDate = function(dateString, format = 'full') {
    const date = new Date(dateString);
    
    if (format === 'full') {
        return date.toLocaleString();
    } else if (format === 'date') {
        return date.toLocaleDateString();
    } else if (format === 'time') {
        return date.toLocaleTimeString();
    } else if (format === 'relative') {
        // Simple relative time formatter
        const now = new Date();
        const diffMs = now - date;
        const diffSec = Math.floor(diffMs / 1000);
        const diffMin = Math.floor(diffSec / 60);
        const diffHour = Math.floor(diffMin / 60);
        const diffDay = Math.floor(diffHour / 24);
        
        if (diffSec < 60) return 'just now';
        if (diffMin < 60) return `${diffMin}m ago`;
        if (diffHour < 24) return `${diffHour}h ago`;
        if (diffDay < 7) return `${diffDay}d ago`;
        
        return date.toLocaleDateString();
    }
    
    return date.toLocaleString();
};
