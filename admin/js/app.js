document.addEventListener('DOMContentLoaded', () => {
    console.log('[AdminApp] DOM ready');
    // Sync PHP session token to JS (if set by server-side)
    if (window.PHP_ADMIN_TOKEN) {
        try { sessionStorage.setItem('admin_token', window.PHP_ADMIN_TOKEN); } catch(e) {}
        window.ADMIN_TOKEN = window.PHP_ADMIN_TOKEN;
        console.log('[AdminApp] Token loaded from PHP session');
    }

    // Sidebar pending approvals badge (both desktop and mobile)
    const badgeDesktop = document.getElementById('pendingApprovalBadge');
    const badgeMobile = document.getElementById('pendingApprovalBadgeMobile');
    
    async function refreshPendingBadge(){
        if (!window.AdminAPI) return;
        try {
            const res = await AdminAPI.request('/admin/kitchen/orders?status=pending_approval');
            const count = (res && Array.isArray(res.kitchen_orders)) ? res.kitchen_orders.length : 0;
            
            // Update both desktop and mobile badges
            [badgeDesktop, badgeMobile].forEach(badge => {
                if (badge) {
                    if (count > 0) { 
                        badge.textContent = count; 
                        badge.style.display = 'inline-block'; 
                    } else { 
                        badge.style.display = 'none'; 
                    }
                }
            });
        } catch(e){ console.warn('refreshPendingBadge error', e); }
    }
    
    // Global function to allow other pages to refresh badge
    window.updatePendingApprovalsBadge = refreshPendingBadge;
    
    refreshPendingBadge();
    setInterval(refreshPendingBadge, 30000);
});

// Global handler to surface unhandled Promise rejections (helps diagnose onboarding.js error)
window.addEventListener('unhandledrejection', function (event) {
    console.error('[AdminApp] Unhandled promise rejection:', event.reason);
});


