window.AdminAPI = (function(){
    const API_BASE = '../api';

    function getToken() {
        try { return sessionStorage.getItem('admin_token') || (window.ADMIN_TOKEN || null); } catch(e) { return window.ADMIN_TOKEN || null; }
    }

    function setToken(token) {
        try { sessionStorage.setItem('admin_token', token); } catch(e) {}
        window.ADMIN_TOKEN = token;
    }

    async function request(path, options={}) {
        const headers = Object.assign({ 'Accept': 'application/json', 'Content-Type': 'application/json' }, options.headers || {});
        const token = getToken();
        console.log('API Request:', path, 'Token:', token ? 'Present' : 'Missing');
        if (token) headers['Authorization'] = `Bearer ${token}`;
        const resp = await fetch(`${API_BASE}${path}`, Object.assign({}, options, { headers }));
        const data = await resp.json().catch(() => ({}));
        console.log('API Response:', path, resp.status, data);
        
        if (!resp.ok) {
            const message = (data && data.message) ? data.message : `HTTP ${resp.status} ${resp.statusText}`;
            const error = new Error(message);
            error.status = resp.status;
            error.response = data;
            throw error;
        }
        
        if (data.success === false) {
            const message = (data && data.message) ? data.message : 'API returned success=false';
            const error = new Error(message);
            error.status = 400;
            error.response = data;
            throw error;
        }
        
        // Prefer data.data if exists; otherwise return data itself
        return (typeof data.data !== 'undefined') ? data.data : data;
    }

    async function login(username, password) {
        const data = await request('/auth/login', { method: 'POST', body: JSON.stringify({ username, password }) });
        if (data && data.token) setToken(data.token);
        return data;
    }

    async function refreshToken() {
        try {
            const data = await request('/auth/refresh', { method: 'POST' });
            if (data && data.token) {
                setToken(data.token);
                return true;
            }
        } catch(e) {
            console.error('Token refresh failed:', e);
        }
        return false;
    }

    return { request, login, getToken, setToken, refreshToken };
})();


