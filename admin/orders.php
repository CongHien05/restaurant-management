<?php
require_once __DIR__ . '/auth.php';
$pageTitle = 'Quản lý đơn hàng - Panda Admin';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
    <div class="content">
        <header class="topbar">
            <div class="brand">Quản lý đơn hàng</div>
            <div class="nav">
                <?php include __DIR__ . '/includes/notification_header.php'; ?>
            </div>
        </header>
        
        <main class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h3 class="mb-0">Danh sách đơn hàng</h3>
                    <div class="d-flex gap-2 flex-wrap">
                        <select id="statusFilter" class="form-select form-select-sm" onchange="loadOrders()">
                            <option value="">Tất cả trạng thái</option>
                            <option value="draft">Nháp</option>
                            <option value="submitted">Đã gửi</option>
                            <option value="confirmed">Đã xác nhận</option>
                            <option value="preparing">Đang chế biến</option>
                            <option value="served">Đã phục vụ</option>
                            <option value="completed">Hoàn thành</option>
                            <option value="cancelled">Đã hủy</option>
                        </select>
                        <input type="date" id="dateFilter" class="form-control form-control-sm" onchange="loadOrders()" />
                    </div>
                </div>
                <div class="card-body">
                    <div id="ordersTable" class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Bàn</th>
                                    <th>Nhân viên</th>
                                    <th>Số lượng món</th>
                                    <th class="text-end">Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Thời gian</th>
                                    <th class="text-center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="ordersBody">
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        Đang tải dữ liệu...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="ordersPagination" class="d-flex justify-content-center mt-3"></div>
                </div>
            </div>
        </main>
    </div>

<!-- Order Detail Modal -->
<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết đơn hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="orderDetailContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
const pageSize = 20;
let orderDetailModal;

document.addEventListener('DOMContentLoaded', function() {
    orderDetailModal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    loadOrders();
});

async function loadOrders() {
    const status = document.getElementById('statusFilter').value;
    const date = document.getElementById('dateFilter').value;
    
    let url = '/orders';
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    if (date) params.append('date', date);
    params.append('page', currentPage);
    params.append('limit', pageSize);
    
    if (params.toString()) url += '?' + params.toString();
    
    try {
        const data = await AdminAPI.request(url);
        renderOrders(data.orders || []);
        renderPagination(data.pagination || {});
    } catch (e) {
        console.error('Load orders error:', e);
        document.getElementById('ordersBody').innerHTML = `
            <tr><td colspan="8" class="text-center text-danger py-4">
                ❌ Không thể tải dữ liệu: ${e.message || 'Lỗi không xác định'}
            </td></tr>`;
    }
}

function renderOrders(orders) {
    const tbody = document.getElementById('ordersBody');
    
    if (!orders.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Không có đơn hàng nào</td></tr>';
        return;
    }
    
    tbody.innerHTML = orders.map(order => {
        const statusBadge = getStatusBadge(order.status);
        const itemCount = Array.isArray(order.items) ? order.items.length : 0;
        const total = Number(order.total_amount || 0);
        
        return `
            <tr>
                <td><strong>#${order.order_number || order.id}</strong></td>
                <td>${order.table_name || `Bàn ${order.table_id}`}</td>
                <td>${order.staff_name || '—'}</td>
                <td class="text-center">${itemCount}</td>
                <td class="text-end">${total.toLocaleString('vi-VN')} ₫</td>
                <td>${statusBadge}</td>
                <td><small>${formatDateTime(order.created_at)}</small></td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary" onclick="viewOrderDetail(${order.id})">
                        Chi tiết
                    </button>
                </td>
            </tr>`;
    }).join('');
}

function renderPagination(pagination) {
    const container = document.getElementById('ordersPagination');
    if (!pagination || pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    const { current_page, total_pages } = pagination;
    container.innerHTML = `
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item ${current_page <= 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${current_page - 1}); return false;">‹</a>
                </li>
                <li class="page-item active">
                    <span class="page-link">${current_page} / ${total_pages}</span>
                </li>
                <li class="page-item ${current_page >= total_pages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${current_page + 1}); return false;">›</a>
                </li>
            </ul>
        </nav>`;
}

function changePage(page) {
    currentPage = page;
    loadOrders();
}

async function viewOrderDetail(orderId) {
    try {
        const data = await AdminAPI.request(`/orders/${orderId}`);
        const order = data.order || data;
        
        const items = order.items || [];
        const itemsHtml = items.map(item => `
            <tr>
                <td>${item.item_name || item.name}</td>
                <td class="text-center">${item.quantity}</td>
                <td class="text-end">${Number(item.unit_price || 0).toLocaleString('vi-VN')} ₫</td>
                <td class="text-end">${Number(item.total_price || 0).toLocaleString('vi-VN')} ₫</td>
            </tr>`).join('');
        
        const content = `
            <div class="mb-3">
                <div class="row g-2">
                    <div class="col-6"><strong>Mã đơn:</strong> #${order.order_number || order.id}</div>
                    <div class="col-6"><strong>Bàn:</strong> ${order.table_name || `Bàn ${order.table_id}`}</div>
                    <div class="col-6"><strong>Nhân viên:</strong> ${order.staff_name || '—'}</div>
                    <div class="col-6"><strong>Trạng thái:</strong> ${getStatusBadge(order.status)}</div>
                    <div class="col-6"><strong>Thời gian:</strong> ${formatDateTime(order.created_at)}</div>
                </div>
            </div>
            <h6 class="border-top pt-3">Danh sách món:</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Món</th>
                            <th class="text-center">SL</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-end">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>${itemsHtml || '<tr><td colspan="4" class="text-center text-muted">Chưa có món nào</td></tr>'}</tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="3" class="text-end">Tổng cộng:</td>
                            <td class="text-end">${Number(order.total_amount || 0).toLocaleString('vi-VN')} ₫</td>
                        </tr>
                    </tfoot>
                </table>
            </div>`;
        
        document.getElementById('orderDetailContent').innerHTML = content;
        orderDetailModal.show();
    } catch (e) {
        console.error('View order detail error:', e);
        alert('Không thể tải chi tiết đơn hàng: ' + (e.message || 'Lỗi không xác định'));
    }
}

function getStatusBadge(status) {
    const badges = {
        'draft': '<span class="badge bg-secondary">Nháp</span>',
        'submitted': '<span class="badge bg-info">Đã gửi</span>',
        'confirmed': '<span class="badge bg-primary">Đã xác nhận</span>',
        'preparing': '<span class="badge bg-warning">Đang chế biến</span>',
        'served': '<span class="badge bg-success">Đã phục vụ</span>',
        'completed': '<span class="badge bg-dark">Hoàn thành</span>',
        'cancelled': '<span class="badge bg-danger">Đã hủy</span>'
    };
    return badges[status] || `<span class="badge bg-light text-dark">${status}</span>`;
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} ${hours}:${minutes}`;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

