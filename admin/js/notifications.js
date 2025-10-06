// Notification System JavaScript
let notificationCheckInterval;
let currentUnreadCount = 0;

// Initialize notification system
document.addEventListener('DOMContentLoaded', function() {
    loadNotificationStats();
    startNotificationCheck();
    
    // Update sidebar badge on page load
    updateSidebarNotificationBadge(currentUnreadCount);
});

// Load notification statistics
function loadNotificationStats() {
    fetch('get_token.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.token) {
                checkNotifications(data.token);
            }
        })
        .catch(error => {
            console.error('Error loading notification stats:', error);
        });
}

// Check for new notifications
function checkNotifications(token) {
    const timestamp = new Date().getTime();
    fetch(`../api/admin/notifications?limit=100&_t=${timestamp}`, {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const unreadCount = data.data.unread_count || 0;
            updateNotificationCount(unreadCount);
            
            // Update notification list if panel is open
            const panel = document.getElementById('notificationPanel');
            if (panel && !panel.classList.contains('hidden')) {
                loadNotificationList(token);
            }
        }
    })
    .catch(error => {
        console.error('Error checking notifications:', error);
    });
}

// Update notification count display
function updateNotificationCount(count) {
    const headerCount = document.getElementById('headerNotificationCount');
    const sidebarBadge = document.getElementById('sidebarNotificationBadge');
    
    if (count > 0) {
        if (headerCount) {
            headerCount.textContent = count;
            headerCount.classList.remove('hidden');
        }
        if (sidebarBadge) {
            sidebarBadge.textContent = count;
        }
    } else {
        if (headerCount) {
            headerCount.classList.add('hidden');
        }
        if (sidebarBadge) {
            sidebarBadge.remove();
        }
    }
    
    currentUnreadCount = count;
    updateSidebarNotificationBadge(count);
}

// Load notification list
function loadNotificationList(token) {
    const notificationList = document.getElementById('notificationList');
    if (!notificationList) return;
    
    fetch(`../api/admin/notifications?limit=10&_t=${new Date().getTime()}`, {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data.notifications) {
            displayNotificationList(data.data.notifications);
        }
    })
    .catch(error => {
        console.error('Error loading notification list:', error);
    });
}

// Display notification list
function displayNotificationList(notifications) {
    const notificationList = document.getElementById('notificationList');
    if (!notificationList) return;
    
    if (notifications.length === 0) {
        notificationList.innerHTML = '<div style="padding: 16px; text-align: center; color: #666;">Không có thông báo mới</div>';
        return;
    }
    
    const html = notifications.map(notification => `
        <div class="notification-item" style="padding: 12px 16px; border-bottom: 1px solid #eee; cursor: pointer;" onclick="handleNotificationClick('${notification.type}', ${notification.id})">
            <div style="font-weight: 500; margin-bottom: 4px;">${notification.title}</div>
            <div style="font-size: 12px; color: #666;">${notification.message}</div>
            <div style="font-size: 11px; color: #999; margin-top: 4px;">${formatTime(notification.created_at)}</div>
        </div>
    `).join('');
    
    notificationList.innerHTML = html;
}

// Handle notification click
function handleNotificationClick(type, id) {
    // Mark as read
    markNotificationAsRead(id);
    
    // Navigate based on type
    switch (type) {
        case 'new_order':
            window.location.href = 'pending_orders.php';
            break;
        case 'payment_completed':
            window.location.href = 'orders.php';
            break;
        case 'table_status_change':
            window.location.href = 'tables.php';
            break;
        default:
            window.location.href = 'notifications.php';
    }
}

// Mark notification as read
function markNotificationAsRead(id) {
    fetch('get_token.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.token) {
                return fetch(`../api/admin/notifications/${id}/read`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${data.token}`,
                        'Content-Type': 'application/json'
                    }
                });
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotificationStats();
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
}

// Mark all notifications as read
function markAllNotificationsAsRead() {
    fetch('get_token.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.token) {
                return fetch('../api/admin/notifications/mark-all-read', {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${data.token}`,
                        'Content-Type': 'application/json'
                    }
                }).then(res => res.json()).then(result => {
                    if (result.success) {
                        loadNotificationStats();
                        loadNotificationList(data.token);
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
}

// Toggle notification panel
function toggleNotificationPanel(evt) {
    if (evt && typeof evt.preventDefault === 'function') evt.preventDefault();
    const panel = document.getElementById('notificationPanel');
    if (!panel) return;
    panel.classList.toggle('hidden');
    if (!panel.classList.contains('hidden')) {
        fetch('get_token.php')
            .then(r => r.json())
            .then(d => { if (d.success && d.token) loadNotificationList(d.token); });
    }
}

// Close panel when clicking outside
document.addEventListener('click', function(e){
    const panel = document.getElementById('notificationPanel');
    const area = document.querySelector('.notification-area');
    if (!panel || !area) return;
    if (!panel.classList.contains('hidden')) {
        if (!area.contains(e.target)) {
            panel.classList.add('hidden');
        }
    }
});

// Start notification checking interval
function startNotificationCheck() {
    notificationCheckInterval = setInterval(() => {
        loadNotificationStats();
    }, 10000); // Check every 10 seconds
}

// Update sidebar notification badge
function updateSidebarNotificationBadge(count) {
    const notificationLink = document.querySelector('a[href*="notifications.php"]');
    if (notificationLink) {
        let badge = notificationLink.querySelector('.notification-badge');
        
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'notification-badge';
                badge.id = 'sidebarNotificationBadge';
                notificationLink.appendChild(badge);
            }
            badge.textContent = count;
        } else if (badge) {
            badge.remove();
        }
    }
}

// Format time
function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) { // Less than 1 minute
        return 'Vừa xong';
    } else if (diff < 3600000) { // Less than 1 hour
        return `${Math.floor(diff / 60000)} phút trước`;
    } else if (diff < 86400000) { // Less than 1 day
        return `${Math.floor(diff / 3600000)} giờ trước`;
    } else {
        return date.toLocaleDateString('vi-VN');
    }
}

// Check notifications when page becomes visible
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        loadNotificationStats();
    }
});

// Check notifications when window gains focus
window.addEventListener('focus', function() {
    loadNotificationStats();
});
