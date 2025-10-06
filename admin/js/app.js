document.addEventListener('DOMContentLoaded', () => {
    console.log('[AdminApp] DOM ready');
    // Sync PHP session token to JS (if set by server-side)
    if (window.PHP_ADMIN_TOKEN) {
        try { sessionStorage.setItem('admin_token', window.PHP_ADMIN_TOKEN); } catch(e) {}
        window.ADMIN_TOKEN = window.PHP_ADMIN_TOKEN;
        console.log('[AdminApp] Token loaded from PHP session');
    }

    // Sidebar pending approvals badge
    const badge = document.getElementById('pendingApprovalBadge');
    async function refreshPendingBadge(){
        if (!badge || !window.AdminAPI) return;
        try {
            const res = await AdminAPI.request('/admin/kitchen/orders?status=pending_approval');
            const count = (res && Array.isArray(res.kitchen_orders)) ? res.kitchen_orders.length : 0;
            if (count > 0) { badge.textContent = count; badge.style.display = 'inline-block'; }
            else { badge.style.display = 'none'; }
        } catch(e){ console.warn('refreshPendingBadge error', e); }
    }
    refreshPendingBadge();
    setInterval(refreshPendingBadge, 30000);
});

// Global handler to surface unhandled Promise rejections (helps diagnose onboarding.js error)
window.addEventListener('unhandledrejection', function (event) {
    console.error('[AdminApp] Unhandled promise rejection:', event.reason);
});


