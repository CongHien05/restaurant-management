<?php
require_once __DIR__ . '/auth.php';
$pageTitle = 'Quản lý bàn - Panda Admin';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
    <div class="content">
        <header class="topbar">
            <div class="brand">Quản lý bàn</div>
            <div class="nav">
                <?php include __DIR__ . '/includes/notification_header.php'; ?>
            </div>
        </header>

        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                <h3>Danh sách bàn</h3>
                <div style="display:flex;gap:8px;align-items:center;">
                    <select id="filterArea" onchange="applyFilters()">
                        <option value="">Tất cả khu vực</option>
                    </select>
                    <select id="filterStatus" onchange="applyFilters()">
                        <option value="">Tất cả trạng thái</option>
                        <option value="available">Trống</option>
                        <option value="occupied">Đang phục vụ</option>
                        <option value="reserved">Đã đặt</option>
                        <option value="maintenance">Bảo trì</option>
                    </select>
                    <select id="sortBy" onchange="applyFilters()">
                        <option value="id">Sắp xếp theo ID</option>
                        <option value="name">Sắp xếp theo Tên</option>
                        <option value="capacity">Sắp xếp theo Sức chứa</option>
                        <option value="status">Sắp xếp theo Trạng thái</option>
                    </select>
                    <button class="btn" onclick="openAddTableModal()">Thêm bàn</button>
                </div>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên bàn</th>
                            <th>Khu vực</th>
                            <th>Sức chứa</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="tablesList">
                        <!-- Tables will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

	<!-- Add/Edit Table Modal (Bootstrap) -->
	<div class="modal fade" id="tableBsModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-md modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="modalTitle">Thêm bàn</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form id="tableForm" class="needs-validation" novalidate>
					<div class="modal-body">
						<input type="hidden" id="tableId" name="id">
						<div class="mb-3">
							<label for="tableName" class="form-label">Tên bàn</label>
							<input type="text" id="tableName" name="name" class="form-control" required>
							<div class="invalid-feedback">Vui lòng nhập tên bàn.</div>
						</div>
						<div class="mb-3">
							<label for="tableArea" class="form-label">Khu vực</label>
							<select id="tableArea" name="area_id" class="form-select" required>
								<option value="">Chọn khu vực</option>
							</select>
							<div class="invalid-feedback">Vui lòng chọn khu vực.</div>
						</div>
						<div class="mb-3">
							<label for="tableCapacity" class="form-label">Sức chứa</label>
							<input type="number" id="tableCapacity" name="capacity" class="form-control" required min="1" max="100">
							<div class="invalid-feedback">Sức chứa phải từ 1 đến 100.</div>
						</div>
						<div class="mb-3" id="statusGroup">
							<label for="tableStatus" class="form-label">Trạng thái</label>
							<select id="tableStatus" name="status" class="form-select">
								<option value="available">Trống</option>
								<option value="occupied">Đang phục vụ</option>
								<option value="reserved">Đã đặt</option>
								<option value="maintenance">Bảo trì</option>
							</select>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
						<button id="btnSaveTable" type="button" class="btn btn-primary" onclick="saveTable()">Lưu</button>
					</div>
				</form>
			</div>
		</div>
	</div>

<script>
let allTables = [];
let allAreas = [];

function viStatus(status){
    switch(String(status||'').toLowerCase()){
        case 'available': return 'Trống';
        case 'occupied': return 'Đang phục vụ';
        case 'reserved': return 'Đã đặt';
        case 'maintenance': return 'Bảo trì';
        default: return status || '—';
    }
}

// Load areas
async function loadAreas() {
    try {
        const res = await AdminAPI.request('/areas');
        const areas = Array.isArray(res) ? res : (res && res.data ? res.data : []);
        allAreas = areas;
        const select = document.getElementById('tableArea');
        if (select) {
            select.innerHTML = '<option value="">Chọn khu vực</option>';
            allAreas.forEach(area => {
                select.innerHTML += `<option value="${area.id}">${area.name}</option>`;
            });
        }
        populateAreaFilter();
    } catch (error) {
        console.error('Lỗi tải khu vực:', error);
    }
}

// Load tables
async function loadTables() {
    try {
        // Ensure areas are loaded first so UI can map area_id -> name and filters are ready
        if (!Array.isArray(allAreas) || allAreas.length === 0) {
            await loadAreas();
        }
        const res = await AdminAPI.request('/tables');
        const data = res || {};
        allTables = (data.tables) ? data.tables : (Array.isArray(data) ? data : []);
        applyFilters();
    } catch (error) {
        console.error('Lỗi tải danh sách bàn:', error);
    }
}

// Render tables (grouped by area)
function renderTables(tables) {
    const tbody = document.getElementById('tablesList');
    if (!tbody) return;
    
    if (!tables || tables.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6">Không có bàn nào</td></tr>';
        return;
    }

    // Group by area_id
    const groups = {};
    tables.forEach(t => {
        const key = String(t.area_id || '0');
        if (!groups[key]) groups[key] = [];
        groups[key].push(t);
    });

    // Build rows with area headers
    let html = '';
    Object.keys(groups).sort().forEach(areaId => {
        const area = allAreas.find(a => String(a.id) === String(areaId));
        const areaName = area ? area.name : 'Chưa gán khu vực';
        html += `<tr><td colspan="6" style="background:#f8fafc;font-weight:700;color:#334155;">Khu vực: ${areaName}</td></tr>`;
        html += groups[areaId].map(table => `
            <tr>
                <td>${table.id}</td>
                <td>${table.name}</td>
                <td>${areaName}</td>
                <td>${table.capacity}</td>
                <td><span class="status-badge status-${table.status}">${viStatus(table.status)}</span></td>
                <td>
                    <button class="btn" onclick="editTable(${table.id})">Sửa</button>
                    <button class="btn btn-danger" onclick="deleteTable(${table.id})">Xóa</button>
                </td>
            </tr>
        `).join('');
    });

    tbody.innerHTML = html;
}

function populateAreaFilter() {
    const sel = document.getElementById('filterArea');
    if (!sel) return;
    sel.innerHTML = '<option value="">Tất cả khu vực</option>';
    allAreas.forEach(a => {
        sel.innerHTML += `<option value="${a.id}">${a.name}</option>`;
    });
}

function applyFilters() {
    const areaVal = (document.getElementById('filterArea').value || '').trim();
    const statusVal = (document.getElementById('filterStatus').value || '').trim();
    const sortBy = (document.getElementById('sortBy').value || 'id');

    let filtered = allTables.slice();
    if (areaVal) filtered = filtered.filter(t => String(t.area_id) === areaVal);
    if (statusVal) filtered = filtered.filter(t => String(t.status) === statusVal);

    filtered.sort((a, b) => {
        switch (sortBy) {
            case 'name': return String(a.name||'').localeCompare(String(b.name||''));
            case 'capacity': return (a.capacity||0) - (b.capacity||0);
            case 'status': return String(a.status||'').localeCompare(String(b.status||''));
            default: return (a.id||0) - (b.id||0);
        }
    });

    renderTables(filtered);
}

// Bootstrap modal instance
let TABLE_BS_MODAL = null;
document.addEventListener('DOMContentLoaded', ()=>{
    const el = document.getElementById('tableBsModal');
    if (window.bootstrap && el) TABLE_BS_MODAL = bootstrap.Modal.getOrCreateInstance(el, { backdrop: true, keyboard: true, focus: true });
});

// Open add table modal
function openAddTableModal() {
    document.getElementById('modalTitle').textContent = 'Thêm bàn';
    const tableFormEl = document.getElementById('tableForm');
    tableFormEl.classList.remove('was-validated');
    tableFormEl.reset();
    document.getElementById('tableId').value = '';
    document.getElementById('statusGroup').style.display = 'none';
    // Refresh areas just in case
    loadAreas().finally(() => { TABLE_BS_MODAL?.show(); });
}

// Edit table
function editTable(tableId) {
    const table = allTables.find(t => t.id == tableId);
    if (!table) return;
    
    document.getElementById('modalTitle').textContent = 'Sửa bàn';
    document.getElementById('tableId').value = table.id;
    document.getElementById('tableName').value = table.name || '';
    document.getElementById('tableArea').value = table.area_id || '';
    document.getElementById('tableCapacity').value = table.capacity || '';
    document.getElementById('tableStatus').value = table.status || 'available';
    document.getElementById('statusGroup').style.display = 'block';
    const tableFormEl = document.getElementById('tableForm');
    tableFormEl.classList.remove('was-validated');
    TABLE_BS_MODAL?.show();
}

// Close modal
function closeTableModal() {
    TABLE_BS_MODAL?.hide();
}

// Save table
async function saveTable() {
    const tableFormEl = document.getElementById('tableForm');
    const formData = new FormData(tableFormEl);
    const data = Object.fromEntries(formData.entries());
    // Client-side validation
    let hasError = false;
    const name = String(data.name||'').trim();
    const areaId = String(data.area_id||'').trim();
    const capacity = Number(data.capacity||0);
    const tableFormEl2 = document.getElementById('tableForm');
    tableFormEl2.classList.add('was-validated');
    if (!name || !areaId || !Number.isFinite(capacity) || capacity < 1 || capacity > 100) { hasError = true; }
    if (hasError) return;
    
    const tableId = data.id;
    delete data.id;
    
    try {
        const btn = document.getElementById('btnSaveTable');
        if (btn) { btn.disabled = true; btn.textContent = 'Đang lưu...'; }
        if (tableId) {
            // Update existing table
            await AdminAPI.request(`/tables/${tableId}`, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
        } else {
            // Create new table
            await AdminAPI.request('/tables', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        }
        alert('Lưu bàn thành công');
        closeTableModal();
        loadTables();
    } catch (error) {
        console.error('Lỗi lưu bàn:', error);
        alert('Lỗi lưu bàn: ' + error.message);
    } finally {
        const btn = document.getElementById('btnSaveTable');
        if (btn) { btn.disabled = false; btn.textContent = 'Lưu'; }
    }
}

// Delete table
async function deleteTable(tableId) {
    if (!confirm('Bạn có chắc chắn muốn xóa bàn này?')) return;
    
    try {
        await AdminAPI.request(`/tables/${tableId}`, {
            method: 'DELETE'
        });
        alert('Đã xóa bàn');
        loadTables();
    } catch (error) {
        console.error('Lỗi xóa bàn:', error);
        alert('Lỗi xóa bàn: ' + error.message);
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('tableModal');
    if (event.target === modal) {
        closeTableModal();
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadAreas();
    loadTables();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>