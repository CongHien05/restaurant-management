<?php
require_once __DIR__ . '/auth.php';
$pageTitle = 'Bảng điều khiển - Panda Admin';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
    <div class="content">
        <header class="topbar">
            <div class="brand">Bảng điều khiển</div>
            <div class="nav">
                
                <div class="mr-16 text-muted">Xin chào, <?php 
                    $adminUser = $_SESSION['admin_user'] ?? 'Admin';
                    if (is_array($adminUser)) {
                        echo htmlspecialchars($adminUser['full_name'] ?? $adminUser['username'] ?? 'Admin');
                    } else {
                        echo htmlspecialchars($adminUser);
                    }
                ?></div>
                <form method="post" action="logout.php" class="no-margin">
                    <button type="submit" class="btn btn-danger">Đăng xuất</button>
                </form>
            </div>
        </header>
        
        <main class="container-fluid">
            <div class="row g-3">
                <div class="col-12">
            <div class="card">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Thống kê tổng quan</h2>
                            <div class="row g-3" id="statsContainer">
                                <div class="col-6 col-md-4 col-lg-2">
                                    <div class="p-3 border rounded bg-white">
                                        <div class="text-secondary small">Tổng số bàn</div>
                                        <div class="h4 m-0" id="totalTables">0</div>
                </div>
            </div>
                                <div class="col-6 col-md-4 col-lg-2">
                                    <div class="p-3 border rounded bg-white">
                                        <div class="text-secondary small">Bàn đang phục vụ</div>
                                        <div class="h4 m-0" id="activeTables">0</div>
                        </div>
                    </div>
                                <div class="col-6 col-md-4 col-lg-2">
                                    <div class="p-3 border rounded bg-white">
                                        <div class="text-secondary small">Bàn trống</div>
                                        <div class="h4 m-0" id="availableTables">0</div>
                        </div>
                    </div>
                                <div class="col-6 col-md-4 col-lg-2">
                                    <div class="p-3 border rounded bg-white">
                                        <div class="text-secondary small">Đơn đang xử lý</div>
                                        <div class="h4 m-0" id="pendingOrders">0</div>
                        </div>
                    </div>
                                <div class="col-6 col-md-4 col-lg-2">
                                    <div class="p-3 border rounded bg-white">
                                        <div class="text-secondary small">Đơn hôm nay</div>
                                        <div class="h4 m-0" id="todayOrders">0</div>
                        </div>
                    </div>
                                <div class="col-6 col-md-4 col-lg-2">
                                    <div class="p-3 border rounded bg-white">
                                        <div class="text-secondary small">Doanh thu hôm nay</div>
                                        <div class="h4 m-0" id="todayRevenue">0 ₫</div>
                        </div>
                    </div>
                        </div>
                    </div>
                </div>
            </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <h2 class="h5 m-0">Quản lý bàn</h2>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <input id="tableSearch" class="form-control form-control-sm" placeholder="Tìm bàn..." oninput="applyTableFilters()" style="min-width:180px;" />
                                    <select id="tableAreaFilter" class="form-select form-select-sm" onchange="applyTableFilters()">
                                    <option value="">Tất cả khu vực</option>
                                </select>
                                    <select id="tableStatusFilter" class="form-select form-select-sm" onchange="applyTableFilters()">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="available">Trống</option>
                                    <option value="occupied">Đang phục vụ</option>
                                    <option value="reserved">Đã đặt</option>
                                    <option value="maintenance">Bảo trì</option>
                                </select>
                                    <select id="tableSortBy" class="form-select form-select-sm" onchange="applyTableFilters()">
                                    <option value="status">Sắp xếp: Trạng thái</option>
                                    <option value="name">Sắp xếp: Tên</option>
                                    <option value="pending">Sắp xếp: Tiền chờ</option>
                                    <option value="id">Sắp xếp: ID</option>
                                </select>
                            </div>
                        </div>
                            <div id="tablesGrid" class="row g-3"></div>
                            <div id="tablesPager" class="d-flex justify-content-center py-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="notification" class="notification"></div>

    <script>
        async function loadStats() {
            try {
                const data = await AdminAPI.request('/admin/stats');
                if (data) {
                    document.getElementById('totalTables').textContent = data.total_tables ?? 0;
                    document.getElementById('activeTables').textContent = data.active_tables ?? 0;
                    document.getElementById('availableTables').textContent = (data.total_tables ?? 0) - (data.active_tables ?? 0);
                    document.getElementById('pendingOrders').textContent = data.pending_orders ?? 0;
                    document.getElementById('todayOrders').textContent = data.today_orders ?? 0;
                    document.getElementById('todayRevenue').textContent = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(data.today_revenue ?? 0);
                }
            } catch (e) { console.error('Error loading stats:', e); }
        }

        let ALL_TABLES = [];
        let ALL_AREAS = [];
        // Pagination states
        let TABLES_PAGE = 1, TABLES_PAGE_SIZE = 12, FILTERED_TABLES = [];
        let RECENT_PAGE = 1, RECENT_PAGE_SIZE = 10, RECENT_ORDERS = [];
        let PAYMENT_PAGE = 1, PAYMENT_PAGE_SIZE = 8, PAYMENT_LIST = [];
        let TOP_PAGE = 1, TOP_PAGE_SIZE = 10, TOP_LIST = [];
        var PM_ITEMS_PAGE = 1, PM_ITEMS_PAGE_SIZE = 8;

        function renderPager(elemId, current, total, onPage){
            const el = document.getElementById(elemId);
            if (!el) return;
            if (total <= 1) { el.innerHTML = ''; return; }
            const prevDisabled = current <= 1 ? 'disabled' : '';
            const nextDisabled = current >= total ? 'disabled' : '';
            el.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary" ${prevDisabled} onclick="(${onPage})( ${Math.max(1, current-1)} )">‹</button>
                    <span class="small text-muted">Trang ${current}/${total}</span>
                    <button class="btn btn-sm btn-outline-secondary" ${nextDisabled} onclick="(${onPage})( ${Math.min(total, current+1)} )">›</button>
                </div>`;
        }
        function populateAreaFilterFromTables(tables){
            const sel = document.getElementById('tableAreaFilter');
            if (!sel) return;
            let areaNames = Array.from(new Set((tables||[]).map(t => t.area_name).filter(Boolean)));
            // Fallback to ALL_AREAS if tables do not contain area_name
            if (!areaNames.length && (ALL_AREAS||[]).length){
                areaNames = ALL_AREAS.map(a => a.name).filter(Boolean);
            }
            sel.innerHTML = '<option value="">Tất cả khu vực</option>' + (areaNames||[]).map(a => `<option value="${a}">${a}</option>`).join('');
        }

        function findAreaName(table){
            if (!table) return '';
            if (table.area_name) return table.area_name;
            const areaId = table.area_id || table.area || null;
            if (areaId && (ALL_AREAS||[]).length){
                const a = ALL_AREAS.find(x => String(x.id) === String(areaId));
                if (a && a.name) return a.name;
            }
            return '';
        }

        function renderTablesGrid(tables){
            const grid = document.getElementById('tablesGrid');
            grid.innerHTML = '';
            const totalPages = Math.max(1, Math.ceil((tables||[]).length / TABLES_PAGE_SIZE));
            const start = (TABLES_PAGE - 1) * TABLES_PAGE_SIZE;
            const pageItems = (tables || []).slice(start, start + TABLES_PAGE_SIZE);
            pageItems.forEach(t => {
                const pending = t.pending_amount || 0;
                const status = (t.status || '').toLowerCase();
                const areaName = findAreaName(t) || 'Chưa gán khu';
                const col = document.createElement('div');
                col.className = 'col-12 col-md-6 col-lg-3';
                col.innerHTML = `
                    <div class="card h-100" role="button" onclick="window.location.href='control.php?table=${t.id}'">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold">Bàn ${t.name || t.table_name || t.table_number || t.id}</div>
                                <span class="badge bg-light text-dark border ${status}">${statusLabel(status)}</span>
                            </div>
                            <div class="d-flex justify-content-between text-secondary small">
                                <span>${areaName}</span>
                                ${pending > 0 ? `<span class=\"fw-semibold text-dark\">${Number(pending).toLocaleString('vi-VN')} ₫</span>` : ''}
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); window.location.href='control.php?table=${t.id}'">Chi tiết</button>
                                <div class="dropdown" onclick="event.stopPropagation();">
                                  <button class="btn btn-sm btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Thanh toán</button>
                                  <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); openPaymentModal(${t.id}, 'pay');">Thanh toán</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); openPaymentModal(${t.id}, 'print');">In bill tạm</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); openPaymentModal(${t.id}, 'edit');">Chỉnh sửa món</a></li>
                                  </ul>
                    </div>
                    </div>
                            </div>
                            </div>`;
                grid.appendChild(col);
                    });
            renderPager('tablesPager', TABLES_PAGE, totalPages, (p)=>{ TABLES_PAGE = p; renderTablesGrid(FILTERED_TABLES); });
        }

        function applyTableFilters(){
            const q = (document.getElementById('tableSearch').value || '').trim().toLowerCase();
            const area = (document.getElementById('tableAreaFilter').value || '').trim();
            const statusVal = (document.getElementById('tableStatusFilter').value || '').trim();
            const sortBy = (document.getElementById('tableSortBy').value || 'status');

            let filtered = ALL_TABLES.slice();
            if (area) filtered = filtered.filter(t => (t.area_name||findAreaName(t)||'') === area);
            if (statusVal) filtered = filtered.filter(t => (String(t.status||'').toLowerCase()) === statusVal);
            if (q) filtered = filtered.filter(t => {
                const hay = `${t.id||''} ${(t.name||t.table_name||t.table_number||'')} ${(findAreaName(t)||'')}`.toLowerCase();
                return hay.includes(q);
            });

            filtered.sort((a,b) => {
                switch (sortBy){
                    case 'name': return String(a.name||a.table_name||'').localeCompare(String(b.name||b.table_name||''));
                    case 'pending': return (b.pending_amount||0) - (a.pending_amount||0);
                    case 'id': return (a.id||0) - (b.id||0);
                    default: return String(a.status||'').localeCompare(String(b.status||''));
                }
            });
            FILTERED_TABLES = filtered;
            TABLES_PAGE = 1;
            renderTablesGrid(FILTERED_TABLES);
        }

        async function loadAreas(){
            try {
                const res = await AdminAPI.request('/areas');
                ALL_AREAS = (res && res.areas) ? res.areas : (Array.isArray(res) ? res : []);
            } catch(e){ console.warn('loadAreas error', e); ALL_AREAS = []; }
        }

        async function loadTables() {
            try {
                const res = await AdminAPI.request('/tables');
                ALL_TABLES = (res && res.tables) ? res.tables : (res.items || res || []);
                // Normalize area_name for initial render if API doesn't provide it
                ALL_TABLES = (ALL_TABLES||[]).map(t => {
                    if (!t) return t;
                    if (!t.area_name) {
                        const areaId = t.area_id || t.area;
                        if (areaId && (ALL_AREAS||[]).length){
                            const a = ALL_AREAS.find(x => String(x.id) === String(areaId));
                            if (a && a.name) t.area_name = a.name;
                        }
                    }
                    return t;
                });
                populateAreaFilterFromTables(ALL_TABLES);
                    applyTableFilters();
            } catch (e) { console.error('Error loading tables:', e); }
        }

        function statusLabel(status){
            switch(status){
                case 'available': return 'Trống';
                case 'occupied': return 'Đang phục vụ';
                case 'reserved': return 'Đã đặt';
                case 'maintenance': return 'Bảo trì';
                default: return status || '—';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadAreas().finally(() => loadTables());
        });

        setInterval(() => { loadStats(); }, 30000);
    </script>

    <!-- Modal: Payment / Print / Edit (Pro version) -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="true">
        <div class="modal-dialog modal-xl modal-fullscreen-md-down modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xử lý đơn hàng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="small text-secondary" id="pmTableInfo"></div>
                        <div class="d-flex gap-2">
                            <span class="badge rounded-pill text-bg-light">In bill tạm</span>
                            <span class="badge rounded-pill text-bg-light">Chỉnh sửa món</span>
                            <span class="badge rounded-pill text-bg-success">Thanh toán</span>
                        </div>
                    </div>
                    <div id="pmAlert" class="alert alert-warning d-none"></div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold">Danh sách món</span>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printTempBill()">In bill tạm</button>
                                        <button type="button" class="btn btn-sm btn-success" onclick="pmShowPayment()">Tiếp tục thanh toán</button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Món</th>
                                                    <th class="text-center" style="width:130px;">Số lượng</th>
                                                    <th class="text-end" style="width:140px;">Đơn giá</th>
                                                    <th class="text-end" style="width:140px;">Thành tiền</th>
                                                    <th style="width:44px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="pmItemsBody"></tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="5">
                                                        <div id="pmItemsPager" class="d-flex justify-content-center py-2"></div>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="pmSection" class="col-12" style="display:none;">
                            <div class="card">
                                <div class="card-header py-2">
                                    <span class="fw-semibold">Thanh toán</span>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Phương thức</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="pmMethodRadio" id="pmMethodCash" value="cash" checked>
                                                <label class="form-check-label" for="pmMethodCash">Tiền mặt</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="pmMethodRadio" id="pmMethodCard" value="card">
                                                <label class="form-check-label" for="pmMethodCard">Thẻ</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="pmMethodRadio" id="pmMethodTransfer" value="transfer">
                                                <label class="form-check-label" for="pmMethodTransfer">Chuyển khoản</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label small mb-1">Giảm (%)</label>
                                            <div class="input-group input-group-sm">
                                                <input id="pmDiscount" type="number" min="0" max="100" value="0" class="form-control" />
                                                <button class="btn btn-outline-secondary" type="button" onclick="pmQuickDiscount(0)">0%</button>
                                                <button class="btn btn-outline-secondary" type="button" onclick="pmQuickDiscount(5)">5%</button>
                                                <button class="btn btn-outline-secondary" type="button" onclick="pmQuickDiscount(10)">10%</button>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small mb-1">Thuế (%)</label>
                                            <input id="pmTax" type="number" min="0" max="30" value="0" class="form-control form-control-sm" />
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small mb-1">Phục vụ (%)</label>
                                            <input id="pmService" type="number" min="0" max="30" value="0" class="form-control form-control-sm" />
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small mb-1">Phụ thu (₫)</label>
                                            <input id="pmSurcharge" type="number" min="0" value="0" class="form-control form-control-sm" />
                                        </div>
                                    </div>

                                    <hr />

                                    <div class="mb-2" id="pmCashBlock">
                                        <label class="form-label small mb-1">Tiền khách đưa (₫)</label>
                                        <input id="pmCashReceived" type="number" min="0" value="0" class="form-control form-control-sm" placeholder="Nhập số tiền khách đưa" />
                                        <div class="d-flex justify-content-between small mt-1">
                                            <span>Tiền thừa</span>
                                            <span class="fw-bold" id="pmChange">0 ₫</span>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Tên khách hàng</label>
                                        <input id="pmCustomer" type="text" class="form-control form-control-sm" placeholder="(tuỳ chọn)" />
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Ghi chú</label>
                                        <textarea id="pmNote" rows="2" class="form-control form-control-sm" placeholder="(tuỳ chọn)"></textarea>
                                    </div>

                                    <div class="border rounded p-2 bg-light">
                                        <div class="d-flex justify-content-between small"><span>Tạm tính</span><span id="pmSubtotal">0 ₫</span></div>
                                        <div class="d-flex justify-content-between small"><span>Giảm</span><span id="pmDiscountAmount">-0 ₫</span></div>
                                        <div class="d-flex justify-content-between small"><span>Phục vụ</span><span id="pmServiceAmount">+0 ₫</span></div>
                                        <div class="d-flex justify-content-between small"><span>Thuế</span><span id="pmTaxAmount">+0 ₫</span></div>
                                        <div class="d-flex justify-content-between small"><span>Phụ thu</span><span id="pmSurchargeAmount">+0 ₫</span></div>
                                        <hr class="my-2" />
                                        <div class="d-flex justify-content-between fw-bold"><span>Tổng thanh toán</span><span id="pmTotal">0 ₫</span></div>
                                    </div>
                                    <div class="d-grid gap-2 mt-3">
                                        <button type="button" class="btn btn-outline-secondary" onclick="printTempBill()">In bill tạm</button>
                                        <button id="pmConfirmBtn" type="button" class="btn btn-success" onclick="confirmPayment()" disabled>Xác nhận thanh toán</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let PAYMENT_MODAL, CURRENT_ORDER = null, CURRENT_TABLE_ID = null;
        let PM_SETTINGS = { discount: 0, tax: 0, service: 0, surcharge: 0, method: 'cash', customer: '', note: '', cashReceived: 0 };
        document.addEventListener('DOMContentLoaded', function(){
            const el = document.getElementById('paymentModal');
            try {
                if (window.bootstrap && el) {
                    PAYMENT_MODAL = bootstrap.Modal.getOrCreateInstance(el, { backdrop: false, keyboard: true, focus: true });
                }
            } catch(e){ console.warn('Bootstrap modal init failed', e); }
        });

        async function openPaymentModal(tableId, action){
            try {
                CURRENT_TABLE_ID = tableId;
                // lấy đơn mới nhất theo bàn (giả định đơn đầu tiên là hoạt động)
                const orders = await AdminAPI.request(`/orders?table_id=${tableId}&limit=1`);
                const order = Array.isArray(orders?.orders) ? orders.orders[0] : (Array.isArray(orders) ? orders[0] : null);
                if (!order) {
                    showPmAlert('Không tìm thấy đơn hoạt động của bàn này.');
                    return;
                }
                // lấy chi tiết đơn hàng (bao gồm pending_items)
                const tableDetails = await AdminAPI.request(`/admin/tables/${tableId}/details`);
                CURRENT_ORDER = {
                    id: order.id,
                    table_name: tableDetails?.table?.name || order.table_name,
                    items: tableDetails?.order_items || [],
                    pending_items: tableDetails?.pending_items || [],
                    status: order.status
                };
                // Kiểm tra trạng thái đơn để cho phép thanh toán
                const eligible = ['served'];
                const canPay = eligible.includes(String(CURRENT_ORDER?.status||'').toLowerCase());
                const confirmBtn = document.getElementById('pmConfirmBtn');
                if (!canPay) {
                    showPmAlert('Chỉ thanh toán khi đơn đã ở trạng thái phục vụ (served).');
                }
                if (confirmBtn) confirmBtn.disabled = !canPay;
                // reset settings mỗi lần mở
                PM_SETTINGS = { discount: 0, tax: 0, service: 0, surcharge: 0, method: 'cash', customer: '', note: '', cashReceived: 0 };
                pmBindSummaryInputs();
                renderPmOrder(CURRENT_ORDER);
                PAYMENT_MODAL?.show();
            } catch(e){
                showPmAlert('Không thể tải dữ liệu đơn hàng.');
                console.error(e);
            }
        }

        function renderPmOrder(order){
            document.getElementById('pmAlert').classList.add('d-none');
            document.getElementById('pmTableInfo').textContent = `Bàn ${order.table_name || ''} • Mã đơn #${order.id}`;
            const body = document.getElementById('pmItemsBody');
            body.innerHTML = '';
            const items = order.items||[];
            const pending = order.pending_items||[];
            // Tính tổng trên toàn bộ danh sách (không phụ thuộc trang hiển thị)
            const subtotalAll = items.reduce((sum, it) => sum + Number((it.unit_price||it.price||0) * (it.quantity||1)), 0);
            const totalItemPages = Math.max(1, Math.ceil(items.length / PM_ITEMS_PAGE_SIZE));
            const startIt = (PM_ITEMS_PAGE - 1) * PM_ITEMS_PAGE_SIZE;
            const pageItems = items.slice(startIt, startIt + PM_ITEMS_PAGE_SIZE);
            // Confirmed items section rows
            pageItems.forEach(it => {
                const unit = Number(it.unit_price||it.price||0);
                const qty = Number(it.quantity||1);
                const sum = unit * qty;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${it.item_name || it.name}</td>
                    <td class="text-center">
                        <div class="input-group input-group-sm">
                            <button class="btn btn-outline-secondary" onclick="pmChangeQty(${order.id}, ${it.id}, ${qty-1})">-</button>
                            <input type="number" min="1" value="${qty}" class="form-control text-center" style="max-width:64px;" onchange="pmChangeQty(${order.id}, ${it.id}, this.value)">
                            <button class="btn btn-outline-secondary" onclick="pmChangeQty(${order.id}, ${it.id}, ${qty+1})">+</button>
                    </div>
                    </td>
                    <td class="text-end">${unit.toLocaleString('vi-VN')} ₫</td>
                    <td class="text-end">${sum.toLocaleString('vi-VN')} ₫</td>
                    <td class="text-center"><button class="btn btn-sm btn-outline-danger" onclick="pmDeleteItem(${order.id}, ${it.id})">×</button></td>
                `;
                body.appendChild(tr);
            });
            // Pending items section header + rows (read-only)
            if (pending.length){
                const trSep = document.createElement('tr');
                trSep.innerHTML = `<td colspan="5"><div class="mt-2 mb-1 fw-semibold text-danger">Đang chờ xác nhận</div></td>`;
                body.appendChild(trSep);
                pending.forEach(pi => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${pi.item_name}</td>
                        <td class="text-center">${pi.quantity}</td>
                        <td class="text-end">—</td>
                        <td class="text-end">—</td>
                        <td class="text-center"><span class="badge text-bg-warning">Pending</span></td>
                    `;
                    body.appendChild(tr);
                });
            }

            renderPager('pmItemsPager', PM_ITEMS_PAGE, totalItemPages, (p)=>{ PM_ITEMS_PAGE = p; renderPmOrder(CURRENT_ORDER); });
            // Tính toán tổng
            const discountAmount = Math.round(subtotalAll * Math.max(0, PM_SETTINGS.discount)/100);
            const serviceAmount = Math.round(subtotalAll * Math.max(0, PM_SETTINGS.service)/100);
            const taxableBase = subtotalAll - discountAmount + serviceAmount + Math.max(0, PM_SETTINGS.surcharge);
            const taxAmount = Math.round(taxableBase * Math.max(0, PM_SETTINGS.tax)/100);
            const total = taxableBase + taxAmount;

            // Cập nhật UI
            const n = (v)=>Number(v).toLocaleString('vi-VN') + ' ₫';
            document.getElementById('pmSubtotal').textContent = n(subtotalAll);
            document.getElementById('pmDiscountAmount').textContent = '-' + n(discountAmount).replace(' ₫',' ₫');
            document.getElementById('pmServiceAmount').textContent = '+' + n(serviceAmount).replace(' ₫',' ₫');
            document.getElementById('pmTaxAmount').textContent = '+' + n(taxAmount).replace(' ₫',' ₫');
            document.getElementById('pmSurchargeAmount').textContent = '+' + n(PM_SETTINGS.surcharge).replace(' ₫',' ₫');
            document.getElementById('pmTotal').textContent = n(total);

            // Tiền mặt: tính tiền thừa và enable nút thanh toán
            const cashBlock = document.getElementById('pmCashBlock');
            const changeEl = document.getElementById('pmChange');
            const confirmBtn = document.getElementById('pmConfirmBtn');
            const isCash = PM_SETTINGS.method === 'cash';
            if (cashBlock) cashBlock.style.display = isCash ? 'block' : 'none';
            if (changeEl) {
                const change = Math.max(0, (PM_SETTINGS.cashReceived||0) - total);
                changeEl.textContent = n(change);
            }
            // Enable when có món và nếu cash thì đủ tiền
            const hasItems = (order.items||[]).length > 0;
            let canPay = hasItems;
            if (isCash) {
                canPay = canPay && (PM_SETTINGS.cashReceived||0) >= total;
            }
            if (confirmBtn) confirmBtn.disabled = !canPay;
        }
        function pmBindSummaryInputs(){
            const d = document.getElementById('pmDiscount');
            const t = document.getElementById('pmTax');
            const s = document.getElementById('pmService');
            const sc = document.getElementById('pmSurcharge');
            const mCash = document.getElementById('pmMethodCash');
            const mCard = document.getElementById('pmMethodCard');
            const mTrans = document.getElementById('pmMethodTransfer');
            const c = document.getElementById('pmCustomer');
            const n = document.getElementById('pmNote');
            const cash = document.getElementById('pmCashReceived');
            if (!d || !t || !s || !sc) return;
            const onChange = () => {
                PM_SETTINGS.discount = Number(d.value||0);
                PM_SETTINGS.tax = Number(t.value||0);
                PM_SETTINGS.service = Number(s.value||0);
                PM_SETTINGS.surcharge = Number(sc.value||0);
                PM_SETTINGS.method = (mCash?.checked && 'cash') || (mCard?.checked && 'card') || (mTrans?.checked && 'transfer') || 'cash';
                PM_SETTINGS.customer = c?.value || '';
                PM_SETTINGS.note = n?.value || '';
                PM_SETTINGS.cashReceived = Number(cash?.value||0);
                if (CURRENT_ORDER) renderPmOrder(CURRENT_ORDER);
            };
            [d,t,s,sc,c,n,cash,mCash,mCard,mTrans].forEach(el => el && el.addEventListener('input', onChange));
            [mCash,mCard,mTrans].forEach(el => el && el.addEventListener('change', onChange));
        }

        function pmShowPayment(){
            const sec = document.getElementById('pmSection');
            if (sec) sec.style.display = 'block';
        }

        function pmHidePayment(){
            const sec = document.getElementById('pmSection');
            if (sec) sec.style.display = 'none';
        }

        function pmQuickDiscount(v){
            const d = document.getElementById('pmDiscount');
            if (!d) return;
            d.value = v;
            PM_SETTINGS.discount = Number(v||0);
            if (CURRENT_ORDER) renderPmOrder(CURRENT_ORDER);
        }


        function showPmAlert(msg){
            const el = document.getElementById('pmAlert');
            el.textContent = msg;
            el.classList.remove('d-none');
        }

        async function pmChangeQty(orderId, itemId, qty){
            const q = Math.max(1, parseInt(qty, 10)||1);
            try {
                const updated = await AdminAPI.request(`/orders/${orderId}/items/${itemId}`, { method: 'PUT', body: JSON.stringify({ quantity: q }) });
                // cập nhật trong CURRENT_ORDER
                CURRENT_ORDER.items = (CURRENT_ORDER.items||[]).map(x => x.id===itemId ? Object.assign({}, x, { quantity: q, total_price: (x.unit_price||x.price||0)*q }) : x);
                renderPmOrder(CURRENT_ORDER);
            } catch(e){ console.error('change qty error', e); showPmAlert('Không thể cập nhật số lượng.'); }
        }

        async function pmDeleteItem(orderId, itemId){
            try {
                await AdminAPI.request(`/orders/${orderId}/items/${itemId}`, { method: 'DELETE' });
                CURRENT_ORDER.items = (CURRENT_ORDER.items||[]).filter(x => x.id!==itemId);
                renderPmOrder(CURRENT_ORDER);
            } catch(e){ console.error('delete item error', e); showPmAlert('Không thể xóa món.'); }
        }

        function printTempBill(){
            try {
                const win = window.open('', 'PRINT', 'height=650,width=900,top=100,left=100');
                if (!win) return;
                const order = CURRENT_ORDER || {};
                const items = order.items||[];
                let rows = items.map(it => `<tr><td>${it.item_name||it.name}</td><td class="text-center">${it.quantity}</td><td class="text-end">${Number(it.unit_price||it.price||0).toLocaleString('vi-VN')} ₫</td><td class="text-end">${Number((it.unit_price||it.price||0)*it.quantity).toLocaleString('vi-VN')} ₫</td></tr>`).join('');
                let subtotal = items.reduce((s,it)=>s+Number((it.unit_price||it.price||0)*(it.quantity||1)),0);
                const discountAmount = Math.round(subtotal * Math.max(0, PM_SETTINGS.discount)/100);
                const serviceAmount = Math.round(subtotal * Math.max(0, PM_SETTINGS.service)/100);
                const taxableBase = subtotal - discountAmount + serviceAmount + Math.max(0, PM_SETTINGS.surcharge);
                const taxAmount = Math.round(taxableBase * Math.max(0, PM_SETTINGS.tax)/100);
                const total = taxableBase + taxAmount;
                win.document.write(`
                    <html><head><title>Panda Restaurant - Bill tạm #${order.id||''}</title>
                    <style>
                        body{font-family:Segoe UI,Roboto,Arial,sans-serif;padding:20px;color:#111}
                        .brand{display:flex;align-items:center;gap:12px;margin-bottom:6px}
                        .brand .logo{width:40px;height:40px;background:#f3f4f6;border-radius:8px;display:grid;place-items:center;font-weight:700}
                        .muted{color:#6b7280}
                        table{width:100%;border-collapse:collapse;margin-top:12px}
                        th,td{border-bottom:1px solid #eee;padding:8px}
                        th{text-align:left;background:#fafafa}
                        .text-end{text-align:right}.text-center{text-align:center}
                        .totals{margin-top:12px;max-width:360px;margin-left:auto}
                        .totals .row{display:flex;justify-content:space-between;margin:4px 0}
                        .totals .strong{font-weight:700}
                        .footer{margin-top:16px;text-align:center;color:#6b7280}
                    </style>
                    </head><body>
                    <div class="brand"><div class="logo">🐼</div><div><div style="font-size:18px;font-weight:700">Panda Restaurant</div><div class="muted" style="font-size:12px">Bill tạm • ${new Date().toLocaleString('vi-VN')}</div></div></div>
                    <div class="muted" style="font-size:12px">Bàn ${order.table_name||''} • Đơn #${order.id||''}${PM_SETTINGS.customer?` • KH: ${PM_SETTINGS.customer}`:''}</div>
                    <table><thead><tr><th>Món</th><th class="text-center">SL</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th></tr></thead><tbody>${rows}</tbody></table>
                    <div class="totals">
                        <div class="row"><span>Tạm tính</span><span>${subtotal.toLocaleString('vi-VN')} ₫</span></div>
                        <div class="row"><span>Giảm (${PM_SETTINGS.discount}%)</span><span>-${discountAmount.toLocaleString('vi-VN')} ₫</span></div>
                        <div class="row"><span>Phục vụ (${PM_SETTINGS.service}%)</span><span>+${serviceAmount.toLocaleString('vi-VN')} ₫</span></div>
                        <div class="row"><span>Thuế (${PM_SETTINGS.tax}%)</span><span>+${taxAmount.toLocaleString('vi-VN')} ₫</span></div>
                        <div class="row"><span>Phụ thu</span><span>+${(PM_SETTINGS.surcharge||0).toLocaleString('vi-VN')} ₫</span></div>
                        <div class="row strong" style="border-top:1px solid #ddd;padding-top:6px;margin-top:6px"><span>Tổng</span><span>${total.toLocaleString('vi-VN')} ₫</span></div>
                        </div>
                    <div class="footer">Cảm ơn quý khách! Vui lòng kiểm tra lại thông tin trước khi thanh toán.</div>
                    <script>window.onload=function(){window.focus();window.print();setTimeout(()=>window.close(),300);};<\/script>
                    </body></html>`);
                win.document.close();
                win.focus();
            } catch(e){ console.error('print error', e); showPmAlert('Không thể in bill tạm.'); }
        }

        async function confirmPayment(){
            try {
                if (!CURRENT_ORDER?.id) return;
                // cập nhật status sang completed (tùy backend thay bằng /payments)
                await AdminAPI.request(`/orders/${CURRENT_ORDER.id}/status`, { method: 'PUT', body: JSON.stringify({ status: 'completed' }) });
                // Đưa bàn về trạng thái trống ngay sau khi thanh toán
                if (CURRENT_TABLE_ID) {
                    try { await AdminAPI.request(`/tables/${CURRENT_TABLE_ID}/status`, { method: 'PUT', body: JSON.stringify({ status: 'available' }) }); } catch(e) { console.warn('update table status failed', e); }
                }
                // Xoá danh sách món trong UI để phản ánh bàn trống
                if (CURRENT_ORDER) { CURRENT_ORDER.items = []; renderPmOrder(CURRENT_ORDER); }
                PAYMENT_MODAL?.hide();
                
                // Refresh all relevant UI components
            loadStats();
                if (typeof loadMiniChart === 'function') { loadMiniChart(); }
                if (typeof loadTopItems === 'function') { loadTopItems(); }
                if (typeof renderPaymentQueue === 'function') { renderPaymentQueue(); }
            loadTables();
                
                // Refresh approvals badge count (in case completed order had pending items)
                if (typeof window.updatePendingApprovalsBadge === 'function') {
                    window.updatePendingApprovalsBadge();
                }
                
                alert('Thanh toán thành công!');
            } catch(e){ 
                console.error('payment error', e); 
                showPmAlert('Không thể thanh toán: ' + (e.message || 'Lỗi không xác định')); 
            }
        }
    </script>

<?php include __DIR__ . '/includes/footer.php'; ?>



