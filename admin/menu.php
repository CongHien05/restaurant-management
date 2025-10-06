<?php
require_once __DIR__ . '/auth.php';
$pageTitle = 'Quản lý menu - Panda Admin';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
    <div class="content">
        <header class="topbar">
            <div class="brand">Quản lý menu</div>
            <div class="nav">
                <?php include __DIR__ . '/includes/notification_header.php'; ?>
                <button class="btn btn-primary" onclick="openAddMenuModal()">
                    <i>➕</i>Thêm món
                </button>
                <form method="get" onsubmit="event.preventDefault(); filterMenu();">
                    <input id="searchInput" name="q" placeholder="Tìm món ăn..." />
                </form>
            </div>
        </header>
        <main class="container">
            <div class="card">
                <div class="table-header">
                    <h3>Danh sách món ăn</h3>
                    <div class="table-actions">
                        <select id="categoryFilter" onchange="filterMenu()">
                            <option value="">Tất cả danh mục</option>
                        </select>
                        <select id="statusFilter" onchange="filterMenu()">
                            <option value="">Tất cả trạng thái</option>
                            <option value="active">Đang bán</option>
                            <option value="inactive">Ngừng bán</option>
                        </select>
                    </div>
                </div>
                <div class="table-list" id="menuList"></div>
                <div id="menuPager" class="d-flex justify-content-center py-2"></div>
            </div>
        </main>
    </div>

    <!-- Modal thêm/sửa món ăn (Bootstrap) -->
    <div class="modal fade" id="menuModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm món ăn mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="menuForm" class="needs-validation" novalidate>
                        <input type="hidden" id="menuId" name="id">
                        <div class="mb-3">
                            <label for="menuName" class="form-label">Tên món *</label>
                            <input type="text" id="menuName" name="name" class="form-control" required>
                            <div class="invalid-feedback">Vui lòng nhập tên món.</div>
                        </div>
                        <div class="mb-3">
                            <label for="menuDescription" class="form-label">Mô tả</label>
                            <textarea id="menuDescription" name="description" rows="3" class="form-control"></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="menuCategory" class="form-label">Danh mục *</label>
                                <select id="menuCategory" name="category_id" class="form-select" required>
                                    <option value="">Chọn danh mục</option>
                                </select>
                                <div class="invalid-feedback">Vui lòng chọn danh mục.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="menuPrice" class="form-label">Giá *</label>
                                <input type="number" id="menuPrice" name="price" min="0" step="1000" class="form-control" required>
                                <div class="invalid-feedback">Vui lòng nhập giá hợp lệ.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="menuStatus" class="form-label">Trạng thái</label>
                                <select id="menuStatus" name="status" class="form-select">
                                    <option value="active">Đang bán</option>
                                    <option value="inactive">Ngừng bán</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="menuPreparationTime" class="form-label">Thời gian chế biến (phút)</label>
                                <input type="number" id="menuPreparationTime" name="preparation_time" min="1" max="120" class="form-control">
                            </div>
                            <div class="col-12">
                                <label for="menuImage" class="form-label">Hình ảnh URL</label>
                                <input type="url" id="menuImage" name="image" placeholder="https://example.com/image.jpg" class="form-control">
                                <div class="invalid-feedback">URL hình ảnh không hợp lệ.</div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button id="menuSaveBtn" type="button" class="btn btn-primary" onclick="saveMenu()">Lưu</button>
                </div>
            </div>
        </div>
    </div>

<script>
function renderPager(elemId, current, total, onPage){
    const el = document.getElementById(elemId);
    if (!el) return;
    if (total <= 1) { el.innerHTML = ''; return; }
    const prevDisabled = current <= 1 ? 'disabled' : '';
    const nextDisabled = current >= total ? 'disabled' : '';
    // Using Function serialization for inline handler
    el.innerHTML = `
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" ${prevDisabled} onclick="(${onPage})( ${Math.max(1, current-1)} )">‹</button>
            <span class="small text-muted">Trang ${current}/${total}</span>
            <button class="btn btn-sm btn-outline-secondary" ${nextDisabled} onclick="(${onPage})( ${Math.min(total, current+1)} )">›</button>
        </div>`;
}
let allMenuItems = [];
let MENU_PAGE = 1, MENU_PAGE_SIZE = 12;
let allCategories = [];

async function loadCategories() {
    try {
        const res = await AdminAPI.request('/categories');
        allCategories = res || [];
        const categoryFilter = document.getElementById('categoryFilter');
        const menuCategory = document.getElementById('menuCategory');
        
        // Update category filter dropdown
        categoryFilter.innerHTML = '<option value="">Tất cả danh mục</option>';
        menuCategory.innerHTML = '<option value="">Chọn danh mục</option>';
        
        allCategories.forEach(category => {
            const option1 = document.createElement('option');
            option1.value = category.id;
            option1.textContent = category.name;
            categoryFilter.appendChild(option1);
            
            const option2 = document.createElement('option');
            option2.value = category.id;
            option2.textContent = category.name;
            menuCategory.appendChild(option2);
        });
    } catch(e) { 
        console.error('Error loading categories:', e); 
    }
}

async function loadMenuItems(){
    try {
        const res = await AdminAPI.request('/menu');
        console.log('Menu API response:', res);
        // API returns {items: [...], pagination: {...}}
        allMenuItems = (res && res.items) ? res.items : [];
        renderMenuItems(allMenuItems);
    } catch(e){ 
        console.error('Error loading menu items:', e);
        allMenuItems = [];
        renderMenuItems([]);
    }
}

function renderMenuItems(menuItems){
    const wrap = document.getElementById('menuList');
    const q = (document.getElementById('searchInput').value || '').trim().toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    wrap.innerHTML = '';
    
    // Ensure menuItems is an array
    const items = Array.isArray(menuItems) ? menuItems : [];
    
    const filtered = items.filter(item => {
        // Search filter
        if (q) {
            const hay = `${(item.name||'')} ${(item.description||'')}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        
        // Category filter
        if (categoryFilter && item.category_id != categoryFilter) return false;
        
        // Status filter
        if (statusFilter && item.status !== statusFilter) return false;
        
        return true;
    });
    
    if (filtered.length === 0) {
        wrap.innerHTML = '<div class="empty-state">Không tìm thấy món nào</div>';
        return;
    }
    const totalPages = Math.max(1, Math.ceil(filtered.length / MENU_PAGE_SIZE));
    const start = (MENU_PAGE - 1) * MENU_PAGE_SIZE;
    const pageItems = filtered.slice(start, start + MENU_PAGE_SIZE);
    
    pageItems.forEach(item => {
        const row = document.createElement('div');
        row.className = 'menu-row';
        const price = item.price || 0;
        const status = (item.status||'').toLowerCase();
        const statusText = {
            'active': 'Đang bán',
            'inactive': 'Ngừng bán'
        }[status] || status;
        
        const category = allCategories.find(c => c.id == item.category_id);
        const categoryName = category ? category.name : 'N/A';
        
        row.innerHTML = `
            <div class="menu-info">
                <div class="menu-image">
                    ${item.image ? `<img src="${item.image}" alt="${item.name}" onerror="this.style.display='none'">` : '<div class="no-image">🍽️</div>'}
                </div>
                <div class="menu-details">
                    <div class="menu-name">${item.name || 'N/A'}</div>
                    <div class="menu-description">${item.description || 'Không có mô tả'}</div>
                    <div class="menu-meta">
                        <span class="category">${categoryName}</span>
                        ${item.preparation_time ? `<span class="prep-time">⏱️ ${item.preparation_time} phút</span>` : ''}
                    </div>
                </div>
            </div>
            <div class="menu-price">
                <div class="price">${Number(price).toLocaleString('vi-VN')} ₫</div>
                <span class="status-badge status-${status}">${statusText}</span>
            </div>
            <div class="menu-actions">
                <button class="btn btn-sm btn-primary" onclick="editMenu(${item.id})">Sửa</button>
                <button class="btn btn-sm btn-danger" onclick="deleteMenu(${item.id})">Xóa</button>
            </div>
        `;
        wrap.appendChild(row);
    });
    renderPager('menuPager', MENU_PAGE, totalPages, (p)=>{ MENU_PAGE = p; renderMenuItems(allMenuItems); });
}

function filterMenu(){ 
    MENU_PAGE = 1;
    renderMenuItems(allMenuItems); 
}

let MENU_BOOTSTRAP_MODAL = null;
document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('menuModal');
    if (window.bootstrap && el) {
        MENU_BOOTSTRAP_MODAL = bootstrap.Modal.getOrCreateInstance(el, { backdrop: false, keyboard: true, focus: true });
    }
});

function openAddMenuModal() {
    document.getElementById('modalTitle').textContent = 'Thêm món ăn mới';
    const form = document.getElementById('menuForm');
    form.reset();
    form.classList.remove('was-validated');
    document.getElementById('menuId').value = '';
    MENU_BOOTSTRAP_MODAL && MENU_BOOTSTRAP_MODAL.show();
}

function editMenu(menuId) {
    const item = allMenuItems.find(m => m.id == menuId);
    if (!item) return;
    
    document.getElementById('modalTitle').textContent = 'Sửa món ăn';
    document.getElementById('menuId').value = item.id;
    document.getElementById('menuName').value = item.name || '';
    document.getElementById('menuDescription').value = item.description || '';
    document.getElementById('menuCategory').value = item.category_id || '';
    document.getElementById('menuPrice').value = item.price || '';
    document.getElementById('menuImage').value = item.image || '';
    document.getElementById('menuStatus').value = item.status || 'active';
    document.getElementById('menuPreparationTime').value = item.preparation_time || '';
    const form = document.getElementById('menuForm');
    form.classList.remove('was-validated');
    MENU_BOOTSTRAP_MODAL && MENU_BOOTSTRAP_MODAL.show();
}

function closeMenuModal() {
    if (MENU_BOOTSTRAP_MODAL) MENU_BOOTSTRAP_MODAL.hide();
}

function validateMenuForm(){
    const form = document.getElementById('menuForm');
    form.classList.add('was-validated');
    const name = document.getElementById('menuName');
    const category = document.getElementById('menuCategory');
    const price = document.getElementById('menuPrice');
    let ok = true;
    if (!name.value.trim()) ok = false;
    if (!category.value) ok = false;
    if (price.value === '' || Number(price.value) < 0) ok = false;
    return ok;
}

async function saveMenu() {
    const form = document.getElementById('menuForm');
    if (!validateMenuForm()) return;
    const btn = document.getElementById('menuSaveBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang lưu...';
    try {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        if (data.id) {
            await AdminAPI.request(`/menu/${data.id}`, { method: 'PUT', body: JSON.stringify(data) });
        } else {
            await AdminAPI.request('/menu', { method: 'POST', body: JSON.stringify(data) });
        }
        closeMenuModal();
        loadMenuItems();
    } catch(e) {
        console.error('Error saving menu item:', e);
        alert('Có lỗi xảy ra: ' + (e.message || 'Không thể lưu món ăn'));
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

async function deleteMenu(menuId) {
    if (!confirm('Bạn có chắc chắn muốn xóa món ăn này?')) return;
    
    try {
        await AdminAPI.request(`/menu/${menuId}`, 'DELETE');
        alert('Xóa món ăn thành công!');
        loadMenuItems();
    } catch(e) {
        console.error('Error deleting menu item:', e);
        alert('Có lỗi xảy ra: ' + (e.message || 'Không thể xóa món ăn'));
    }
}

// Remove legacy outside click handler; Bootstrap handles focus/backdrop

document.addEventListener('DOMContentLoaded', ()=>{ 
    loadCategories();
    loadMenuItems(); 
});
</script>

<style>
.menu-row {
    display: flex;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
    gap: 15px;
}

.menu-info {
    display: flex;
    align-items: center;
    flex: 1;
    gap: 15px;
}

.menu-image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
}

.menu-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.menu-image .no-image {
    font-size: 24px;
    color: #999;
}

.menu-details {
    flex: 1;
}

.menu-name {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 4px;
}

.menu-description {
    color: #666;
    font-size: 14px;
    margin-bottom: 8px;
    line-height: 1.4;
}

.menu-meta {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: #888;
}

.menu-price {
    text-align: right;
    min-width: 120px;
}

.price {
    font-weight: 600;
    font-size: 16px;
    color: #e74c3c;
    margin-bottom: 4px;
}

.menu-actions {
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
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
