<?php
require_once __DIR__ . '/auth.php';
$pageTitle = 'Quản lý nhân viên - Panda Admin';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
    <div class="content">
        <header class="topbar">
            <div class="brand">Quản lý nhân viên</div>
            <div class="nav">
                <?php include __DIR__ . '/includes/notification_header.php'; ?>
                <button class="btn btn-primary" onclick="openAddUserModal()">
                    <i>➕</i>Thêm nhân viên
                </button>
                <form method="get" onsubmit="event.preventDefault(); filterUsers();">
                    <input id="searchInput" name="q" placeholder="Tìm nhân viên..." />
                </form>
            </div>
        </header>
        <main class="container">
            <div class="card">
                <div class="table-header">
                    <h3>Danh sách nhân viên</h3>
                    <div class="table-actions">
                        <select id="roleFilter" onchange="filterUsers()">
                            <option value="">Tất cả vai trò</option>
                            <option value="admin">Quản trị viên</option>
                            <option value="manager">Quản lý</option>
                            <option value="waiter">Phục vụ</option>
                            <option value="kitchen">Bếp</option>
                            <option value="cashier">Thu ngân</option>
                        </select>
                        <select id="statusFilter" onchange="filterUsers()">
                            <option value="">Tất cả trạng thái</option>
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Ngừng hoạt động</option>
                        </select>
                    </div>
                </div>
                <div class="table-list" id="usersList"></div>
            </div>
        </main>
    </div>

    <!-- Modal thêm/sửa nhân viên (Bootstrap) -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm nhân viên mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="userForm" class="needs-validation" novalidate>
                        <input type="hidden" id="userId" name="id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="userName" class="form-label">Họ tên *</label>
                                <input type="text" id="userName" name="full_name" class="form-control" required>
                                <div class="invalid-feedback">Vui lòng nhập họ tên.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="userUsername" class="form-label">Tên đăng nhập *</label>
                                <input type="text" id="userUsername" name="username" class="form-control" required>
                                <div class="invalid-feedback">Vui lòng nhập tên đăng nhập.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="userEmail" class="form-label">Email *</nlabel>
                                <input type="email" id="userEmail" name="email" class="form-control" required>
                                <div class="invalid-feedback">Email không hợp lệ.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="userPhone" class="form-label">Số điện thoại</label>
                                <input type="tel" id="userPhone" name="phone" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="userRole" class="form-label">Vai trò *</label>
                                <select id="userRole" name="role" class="form-select" required>
                                    <option value="">Chọn vai trò</option>
                                    <option value="admin">Quản trị viên</option>
                                    <option value="manager">Quản lý</option>
                                    <option value="waiter">Phục vụ</option>
                                    <option value="kitchen">Bếp</option>
                                    <option value="cashier">Thu ngân</option>
                                </select>
                                <div class="invalid-feedback">Vui lòng chọn vai trò.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="userPassword" class="form-label">Mật khẩu *</label>
                                <input type="password" id="userPassword" name="password" class="form-control" required>
                                <div class="invalid-feedback">Vui lòng nhập mật khẩu.</div>
                                <small class="form-text">Để trống nếu không muốn thay đổi mật khẩu (khi sửa)</small>
                            </div>
                            <div class="col-md-6">
                                <label for="userStatus" class="form-label">Trạng thái</label>
                                <select id="userStatus" name="status" class="form-select">
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Ngừng hoạt động</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button id="userSaveBtn" type="button" class="btn btn-primary" onclick="saveUser()">Lưu</button>
                </div>
            </div>
        </div>
    </div>

<script>
let allUsers = [];

async function loadUsers(){
    try {
        // Check token first
        const token = AdminAPI.getToken();
        console.log('Current token:', token ? token.substring(0, 50) + '...' : 'No token');
        
        if (!token) {
            alert('Không có token xác thực. Vui lòng đăng nhập lại.');
            window.location.href = 'login.php';
            return;
        }
        
        // Try admin/staff endpoint first
        const res = await AdminAPI.request('/admin/staff');
        console.log('Users API response:', res);
        // API returns {users: [...], pagination: {...}}
        allUsers = (res && res.users) ? res.users : [];
        renderUsers(allUsers);
    } catch(e){ 
        console.error('Error loading users:', e);
        
        if (e.status === 401 || e.message.includes('401') || e.message.includes('Unauthorized')) {
            console.log('401 Unauthorized error:', e.response);
            
            // Try to refresh token first
            console.log('Attempting to refresh token...');
            const refreshed = await AdminAPI.refreshToken();
            if (refreshed) {
                console.log('Token refreshed, retrying...');
                // Retry the request
                try {
                    const res = await AdminAPI.request('/admin/staff');
                    allUsers = (res && res.users) ? res.users : [];
                    renderUsers(allUsers);
                    return;
                } catch(retryError) {
                    console.error('Retry failed:', retryError);
                }
            }
            
            alert('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
            window.location.href = 'login.php';
            return;
        }
        
        // Fallback: try to get users from auth/me or create dummy data
        allUsers = [];
        renderUsers([]);
        alert('Không thể tải danh sách nhân viên: ' + e.message);
    }
}

function renderUsers(users){
    const wrap = document.getElementById('usersList');
    const q = (document.getElementById('searchInput').value || '').trim().toLowerCase();
    const roleFilter = document.getElementById('roleFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    wrap.innerHTML = '';
    
    // Ensure users is an array
    const userList = Array.isArray(users) ? users : [];
    
    const filtered = userList.filter(user => {
        // Search filter
        if (q) {
            const hay = `${(user.full_name||'')} ${(user.username||'')} ${(user.email||'')}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        
        // Role filter
        if (roleFilter && user.role !== roleFilter) return false;
        
        // Status filter
        if (statusFilter && user.status !== statusFilter) return false;
        
        return true;
    });
    
    if (filtered.length === 0) {
        wrap.innerHTML = '<div class="empty-state">Không tìm thấy nhân viên nào</div>';
        return;
    }
    
    filtered.forEach(user => {
        const row = document.createElement('div');
        row.className = 'user-row';
        const status = (user.status||'').toLowerCase();
        const statusText = {
            'active': 'Hoạt động',
            'inactive': 'Ngừng hoạt động'
        }[status] || status;
        
        const roleText = {
            'admin': 'Quản trị viên',
            'manager': 'Quản lý',
            'waiter': 'Phục vụ',
            'kitchen': 'Bếp',
            'cashier': 'Thu ngân'
        }[user.role] || user.role;
        
        const createdDate = new Date(user.created_at).toLocaleDateString('vi-VN');
        
        row.innerHTML = `
            <div class="user-info">
                <div class="user-avatar">
                    <div class="avatar-circle">${(user.full_name || 'U').charAt(0).toUpperCase()}</div>
                </div>
                <div class="user-details">
                    <div class="user-name">${user.full_name || 'N/A'}</div>
                    <div class="user-meta">
                        <span class="username">@${user.username || 'N/A'}</span>
                        <span class="role">${roleText}</span>
                    </div>
                    <div class="user-contact">
                        <span class="email">${user.email || 'N/A'}</span>
                        ${user.phone ? `<span class="phone">${user.phone}</span>` : ''}
                    </div>
                </div>
            </div>
            <div class="user-status">
                <span class="status-badge status-${status}">${statusText}</span>
                <div class="user-date">Tham gia: ${createdDate}</div>
            </div>
            <div class="user-actions">
                <button class="btn btn-sm btn-primary" onclick="editUser(${user.id})">Sửa</button>
                <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})">Xóa</button>
            </div>
        `;
        wrap.appendChild(row);
    });
}

function filterUsers(){ 
    renderUsers(allUsers); 
}

let USER_BOOTSTRAP_MODAL = null;
document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('userModal');
    if (window.bootstrap && el) {
        USER_BOOTSTRAP_MODAL = bootstrap.Modal.getOrCreateInstance(el, { backdrop: false, keyboard: true, focus: true });
    }
});

function openAddUserModal() {
    document.getElementById('modalTitle').textContent = 'Thêm nhân viên mới';
    const form = document.getElementById('userForm');
    form.reset();
    form.classList.remove('was-validated');
    document.getElementById('userId').value = '';
    document.getElementById('userPassword').required = true;
    USER_BOOTSTRAP_MODAL && USER_BOOTSTRAP_MODAL.show();
}

function editUser(userId) {
    const user = allUsers.find(u => u.id == userId);
    if (!user) return;
    
    document.getElementById('modalTitle').textContent = 'Sửa thông tin nhân viên';
    document.getElementById('userId').value = user.id;
    document.getElementById('userName').value = user.full_name || '';
    document.getElementById('userUsername').value = user.username || '';
    document.getElementById('userEmail').value = user.email || '';
    document.getElementById('userPhone').value = user.phone || '';
    document.getElementById('userRole').value = user.role || '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = false; // Not required for edit
    document.getElementById('userStatus').value = user.status || 'active';
    const form = document.getElementById('userForm');
    form.classList.remove('was-validated');
    USER_BOOTSTRAP_MODAL && USER_BOOTSTRAP_MODAL.show();
}

function closeUserModal() {
    if (USER_BOOTSTRAP_MODAL) USER_BOOTSTRAP_MODAL.hide();
    document.getElementById('userPassword').required = true; // Reset required for new users
}

function validateUserForm(){
    const form = document.getElementById('userForm');
    form.classList.add('was-validated');
    const name = document.getElementById('userName');
    const username = document.getElementById('userUsername');
    const email = document.getElementById('userEmail');
    const role = document.getElementById('userRole');
    const pass = document.getElementById('userPassword');
    let ok = true;
    if (!name.value.trim()) ok = false;
    if (!username.value.trim()) ok = false;
    if (!email.checkValidity()) ok = false;
    if (!role.value) ok = false;
    if (pass.required && !pass.value) ok = false;
    return ok;
}

async function saveUser() {
    const form = document.getElementById('userForm');
    if (!validateUserForm()) return;
    const btn = document.getElementById('userSaveBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang lưu...';
    try {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        if (data.id && !data.password) { delete data.password; }
        if (data.id) {
            await AdminAPI.request(`/admin/staff/${data.id}`, { method: 'PUT', body: JSON.stringify(data) });
        } else {
            await AdminAPI.request('/admin/staff', { method: 'POST', body: JSON.stringify(data) });
        }
        closeUserModal();
        loadUsers();
    } catch(e) {
        console.error('Error saving user:', e);
        alert('Có lỗi xảy ra: ' + (e.message || 'Không thể lưu thông tin nhân viên'));
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

async function deleteUser(userId) {
    if (!confirm('Bạn có chắc chắn muốn xóa nhân viên này?')) return;
    
    try {
        await AdminAPI.request(`/admin/staff/${userId}`, 'DELETE');
        alert('Xóa nhân viên thành công!');
        loadUsers();
    } catch(e) {
        console.error('Error deleting user:', e);
        alert('Có lỗi xảy ra: ' + (e.message || 'Không thể xóa nhân viên'));
    }
}

// Remove legacy outside click handler; Bootstrap controls backdrop

document.addEventListener('DOMContentLoaded', ()=>{ 
    loadUsers(); 
});
</script>

<style>
.user-row {
    display: flex;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
    gap: 15px;
}

.user-info {
    display: flex;
    align-items: center;
    flex: 1;
    gap: 15px;
}

.user-avatar {
    width: 50px;
    height: 50px;
}

.avatar-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 18px;
}

.user-details {
    flex: 1;
}

.user-name {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 4px;
}

.user-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 4px;
    font-size: 14px;
}

.username {
    color: #666;
}

.role {
    background: #e9ecef;
    color: #495057;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
}

.user-contact {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: #888;
}

.user-status {
    text-align: center;
    min-width: 120px;
}

.user-date {
    font-size: 11px;
    color: #888;
    margin-top: 4px;
}

.user-actions {
    display: flex;
    gap: 8px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #666;
    font-style: italic;
}

.form-text {
    font-size: 12px;
    color: #666;
    margin-top: 4px;
    display: block;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
