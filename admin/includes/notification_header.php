<div class="notification-area">
    <div class="notification-icon" onclick="toggleNotificationPanel()">
        🔔
        <span class="notification-count" id="headerNotificationCount" style="display: none;">0</span>
    </div>
    
    <div class="notification-panel" id="notificationPanel" style="display: none;">
        <div class="notification-header">
            <h4>Notifications</h4>
            <button onclick="markAllNotificationsAsRead()">Mark All Read</button>
        </div>
        <div class="notification-list" id="notificationList">
            <!-- Notifications will be loaded here -->
        </div>
        <div class="notification-footer">
            <a href="notifications.php">View All</a>
        </div>
    </div>
</div>

<script>
// Notification System Functions
let notificationCheckInterval;
let lastUnreadCount = 0;

// Toggle notification panel
function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        loadNotifications();
    } else {
        panel.style.display = 'none';
    }
}

// Load notifications
async function loadNotifications() {
    try {
        // Temporarily disabled due to API issues
        // const res = await AdminAPI.request('/admin/notifications?limit=10');
        // if (res && res.notifications) {
        //     displayNotifications(res.notifications);
        // }
        
        // Show empty state for now
        const list = document.getElementById('notificationList');
        if (list) {
            list.innerHTML = '<div class="empty-notifications">No notifications available</div>';
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

// Display notifications
function displayNotifications(notifications) {
    const list = document.getElementById('notificationList');
    if (!list) return;
    
    if (notifications.length === 0) {
        list.innerHTML = '<div class="empty-notifications">No notifications</div>';
        return;
    }
    
    list.innerHTML = notifications.map(notification => `
        <div class="notification-item ${notification.is_read ? 'read' : 'unread'}" onclick="markNotificationAsRead(${notification.id})">
            <div class="notification-title">${notification.title}</div>
            <div class="notification-message">${notification.message}</div>
            <div class="notification-time">${getTimeAgo(notification.created_at)}</div>
        </div>
    `).join('');
}

// Mark notification as read
async function markNotificationAsRead(id) {
    try {
        // Temporarily disabled due to API issues
        // await AdminAPI.request(`/admin/notifications/${id}/read`, { method: 'PUT' });
        console.log('Mark notification as read:', id);
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Mark all notifications as read
async function markAllNotificationsAsRead() {
    try {
        // Temporarily disabled due to API issues
        // await AdminAPI.request('/admin/notifications/mark-all-read', { method: 'PUT' });
        console.log('Mark all notifications as read');
    } catch (error) {
        console.error('Error marking all notifications as read:', error);
    }
}

// Check notification count
async function checkNotificationCount() {
    try {
        // Temporarily disabled due to API issues
        // const res = await AdminAPI.request('/admin/notifications?limit=100');
        // if (res && res.notifications) {
        //     const unreadCount = res.notifications.filter(n => !n.is_read).length;
        //     updateNotificationCount(unreadCount);
        // }
        
        // Show dummy count for now
        updateNotificationCount(0);
    } catch (error) {
        console.error('Error checking notification count:', error);
        updateNotificationCount(0);
    }
}

// Update notification count display
function updateNotificationCount(count) {
    const countElement = document.getElementById('headerNotificationCount');
    if (count > 0) {
        countElement.textContent = count;
        countElement.style.display = 'block';
    } else {
        countElement.style.display = 'none';
    }
}

// Get time ago
function getTimeAgo(datetime) {
    const time = new Date(datetime);
    const now = new Date();
    const diff = now - time;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)} minutes ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)} hours ago`;
    const days = Math.floor(diff / 86400000);
    return `${days} days ago`;
}

// Start notification checking
function startNotificationCheck() {
    notificationCheckInterval = setInterval(checkNotificationCount, 10000);
}

// Initialize notification system
function initNotificationSystem() {
    checkNotificationCount();
    startNotificationCheck();
}

// Close notification panel when clicking outside
document.addEventListener('click', function(event) {
    const panel = document.getElementById('notificationPanel');
    const icon = document.querySelector('.notification-icon');
    
    if (panel && !panel.contains(event.target) && !icon.contains(event.target)) {
        panel.style.display = 'none';
    }
});

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initNotificationSystem();
});
</script>