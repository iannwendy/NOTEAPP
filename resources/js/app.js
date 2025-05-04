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
            // Xử lý thông báo từ Service Worker
        });
    });
}

// Thêm indicator cho trạng thái offline/online
function addOfflineIndicators() {
    // Chỉ báo offline
    const offlineIndicator = document.createElement('div');
    offlineIndicator.className = 'offline-indicator';
    offlineIndicator.textContent = 'Ngoại tuyến';
    document.body.appendChild(offlineIndicator);
    
    // Chỉ báo đồng bộ hóa
    const syncIndicator = document.createElement('div');
    syncIndicator.className = 'sync-indicator';
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
                    updated_at: new Date().toISOString()
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
