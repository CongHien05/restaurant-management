<?php
require_once __DIR__ . '/auth.php';
$pageTitle = 'Báo cáo doanh thu - Panda Admin';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
    <div class="content">
        <header class="topbar">
            <div class="brand">Báo cáo doanh thu</div>
            <div class="nav">
                <?php include __DIR__ . '/includes/notification_header.php'; ?>
                <button class="btn btn-secondary" onclick="exportReport()">
                    <i>📊</i>Xuất báo cáo
                </button>
            </div>
        </header>
        <main class="container">
            <!-- Filter Section -->
            <div class="card">
                <div class="filter-section">
                    <h3>Bộ lọc báo cáo</h3>
                    <div class="filter-controls">
                        <div class="filter-group">
                            <label for="reportType">Loại báo cáo:</label>
                            <select id="reportType" onchange="changeReportType()">
                                <option value="daily">Theo ngày</option>
                                <option value="monthly">Theo tháng</option>
                                <option value="table">Theo bàn</option>
                                <option value="menu">Theo món ăn</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="dateFrom">Từ ngày:</label>
                            <input type="date" id="dateFrom" onchange="loadRevenueData()">
                        </div>
                        <div class="filter-group">
                            <label for="dateTo">Đến ngày:</label>
                            <input type="date" id="dateTo" onchange="loadRevenueData()">
                        </div>
                        <div class="filter-group" id="tableFilterGroup" style="display: none;">
                            <label for="tableFilter">Bàn:</label>
                            <select id="tableFilter" onchange="loadRevenueData()">
                                <option value="">Tất cả bàn</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <button class="btn btn-primary" onclick="loadRevenueData()">Tải dữ liệu</button>
                        </div>
                        <div class="filter-group" style="margin-left:auto">
                            <label>&nbsp;</label>
                            <button class="btn btn-danger" onclick="openCloseDayModal()">Chốt doanh thu ngày</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="card">
                <div class="chart-header">
                    <h3>Biểu đồ doanh thu</h3>
                    <div class="chart-controls">
                        <button class="btn btn-sm btn-secondary" onclick="toggleChart()">Ẩn/Hiện biểu đồ</button>
                    </div>
                </div>
                <div class="chart-container" id="chartContainer">
                    <canvas id="revenueChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Data Table -->
            <div class="card">
                <div class="table-header">
                    <h3>Chi tiết dữ liệu</h3>
                    <div class="table-actions">
                        <button class="btn btn-sm btn-secondary" onclick="refreshData()">🔄 Làm mới</button>
                        <button class="btn btn-sm btn-outline-primary" onclick="toggleClosures()">Lịch sử chốt ngày</button>
                    </div>
                </div>
                <div class="table-list" id="revenueDataList"></div>
            </div>

            <!-- Closures History -->
            <div class="card" id="closuresCard" style="display:none;">
                <div class="table-header">
                    <h3>Lịch sử chốt doanh thu</h3>
                    <div class="table-actions">
                        <button class="btn btn-sm btn-secondary" onclick="loadClosures()">Tải lịch sử</button>
                    </div>
                </div>
                <div class="table-list" id="closuresList"></div>
            </div>
        </main>
    </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let revenueChart = null;
let allTables = [];
let CLOSURES = [], CLOSURES_PAGE = 1, CLOSURES_PAGE_SIZE = 4;
let REVENUE_DATA = [], REVENUE_PAGE = 1, REVENUE_PAGE_SIZE = 4;

// Set default dates (last 30 days)
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
    
    document.getElementById('dateTo').value = today.toISOString().split('T')[0];
    document.getElementById('dateFrom').value = thirtyDaysAgo.toISOString().split('T')[0];
    
    loadTables();
    loadRevenueData();
});

async function loadTables() {
    try {
        const res = await AdminAPI.request('/tables');
        allTables = (res && res.tables) ? res.tables : (res.items || res || []);
        
        const tableFilter = document.getElementById('tableFilter');
        tableFilter.innerHTML = '<option value="">Tất cả bàn</option>';
        
        allTables.forEach(table => {
            const option = document.createElement('option');
            option.value = table.id;
            option.textContent = `Bàn ${table.name}`;
            tableFilter.appendChild(option);
        });
    } catch(e) {
        console.error('Error loading tables:', e);
    }
}

function changeReportType() {
    const reportType = document.getElementById('reportType').value;
    const tableFilterGroup = document.getElementById('tableFilterGroup');
    
    if (reportType === 'table') {
        tableFilterGroup.style.display = 'block';
    } else {
        tableFilterGroup.style.display = 'none';
    }
    
    loadRevenueData();
}

async function loadRevenueData() {
    const reportType = document.getElementById('reportType').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    const tableId = document.getElementById('tableFilter').value;
    
    if (!dateFrom || !dateTo) {
        alert('Vui lòng chọn khoảng thời gian');
        return;
    }
    
    try {
        // Load revenue data based on report type
        let endpoint = `/revenue?from=${dateFrom}&to=${dateTo}&type=${reportType}`;
        if (tableId) {
            endpoint += `&table_id=${tableId}`;
        }
        
        const res = await AdminAPI.request(endpoint);
        updateChart(res.chartData || []);
        updateDataTable(res.data || []);
        
    } catch(e) {
        console.error('Error loading revenue data:', e);
        alert('Không thể tải dữ liệu doanh thu');
    }
}

function updateChart(chartData) {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    if (revenueChart) {
        revenueChart.destroy();
    }
    
    const reportType = document.getElementById('reportType').value;
    let label = 'Ngày';
    let dataLabel = 'Doanh thu';
    
    switch(reportType) {
        case 'monthly':
            label = 'Tháng';
            break;
        case 'table':
            label = 'Bàn';
            break;
        case 'menu':
            label = 'Món ăn';
            break;
    }
    
    revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.map(item => item.label),
            datasets: [{
                label: dataLabel,
                data: chartData.map(item => item.value),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('vi-VN') + ' ₫';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return dataLabel + ': ' + context.parsed.y.toLocaleString('vi-VN') + ' ₫';
                        }
                    }
                }
            }
        }
    });
}

function updateDataTable(data) {
    REVENUE_DATA = data || [];
    REVENUE_PAGE = 1;
    renderRevenueData();
}

function renderRevenueData() {
    const wrap = document.getElementById('revenueDataList');
    const reportType = document.getElementById('reportType').value;
    
    wrap.innerHTML = '';
    
    if (REVENUE_DATA.length === 0) {
        wrap.innerHTML = '<div class="empty-state">Không có dữ liệu</div>';
        return;
    }
    
    const totalPages = Math.max(1, Math.ceil(REVENUE_DATA.length / REVENUE_PAGE_SIZE));
    const start = (REVENUE_PAGE - 1) * REVENUE_PAGE_SIZE;
    const pageItems = REVENUE_DATA.slice(start, start + REVENUE_PAGE_SIZE);
    
    pageItems.forEach(item => {
        const row = document.createElement('div');
        row.className = 'revenue-row';
        
        let label = item.label || 'N/A';
        let details = '';
        
        switch(reportType) {
            case 'daily':
                details = `Đơn hàng: ${item.orders || 0}`;
                break;
            case 'monthly':
                details = `Đơn hàng: ${item.orders || 0}`;
                break;
            case 'table':
                const table = allTables.find(t => t.id == item.table_id);
                label = table ? `Bàn ${table.name}` : label;
                details = `Đơn hàng: ${item.orders || 0}`;
                break;
            case 'menu':
                details = `Số lượng: ${item.quantity || 0}`;
                break;
        }
        
        row.innerHTML = `
            <div class="revenue-info">
                <div class="revenue-label">${label}</div>
                <div class="revenue-details">${details}</div>
            </div>
            <div class="revenue-amount">
                ${Number((item.revenue!=null?item.revenue:item.value) || 0).toLocaleString('vi-VN')} ₫
            </div>
        `;
        if (reportType === 'daily' && item.label) {
            row.style.cursor = 'pointer';
            row.addEventListener('click', () => openDailyDetail(item.label));
        }
        wrap.appendChild(row);
    });
    
    // Render pagination
    let pager = document.getElementById('revenueDataPager');
    if (!pager) {
        pager = document.createElement('div');
        pager.id = 'revenueDataPager';
        pager.className = 'd-flex justify-content-center py-2';
        wrap.parentElement.appendChild(pager);
    }
    renderPager('revenueDataPager', REVENUE_PAGE, totalPages, (p) => {
        REVENUE_PAGE = p;
        renderRevenueData();
    });
}

async function openDailyDetail(dateLabel){
    // Use closure detail modal to show per-day detail (menu breakdown)
    openClosureDetailModal(dateLabel);
}

function toggleChart() {
    const container = document.getElementById('chartContainer');
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}

function refreshData() {
    loadRevenueData();
}

function exportReport() {
    const reportType = document.getElementById('reportType').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    // Simple CSV export
    const data = [];
    const rows = document.querySelectorAll('.revenue-row');
    
    rows.forEach(row => {
        const label = row.querySelector('.revenue-label').textContent;
        const amount = row.querySelector('.revenue-amount').textContent;
        data.push([label, amount]);
    });
    
    const csvContent = "data:text/csv;charset=utf-8," 
        + "Tên,Doanh thu\n"
        + data.map(row => row.join(",")).join("\n");
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `bao_cao_doanh_thu_${dateFrom}_${dateTo}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function toggleClosures(){
    const card = document.getElementById('closuresCard');
    card.style.display = card.style.display === 'none' ? 'block' : 'none';
    if (card.style.display === 'block') loadClosures();
}

async function loadClosures(){
    try {
        const from = document.getElementById('dateFrom').value || new Date().toISOString().split('T')[0];
        const to = document.getElementById('dateTo').value || new Date().toISOString().split('T')[0];
        const res = await AdminAPI.request(`/admin/revenue/closures?from=${from}&to=${to}`);
        CLOSURES = (res && res.closures) ? res.closures : [];
        CLOSURES_PAGE = 1;
        renderClosures();
    } catch(e){
        document.getElementById('closuresList').innerHTML = '<div class="empty-state">Không thể tải lịch sử chốt</div>';
    }
}

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

function renderClosures(){
    const wrap = document.getElementById('closuresList');
    wrap.innerHTML = '';
    if (!CLOSURES.length) { wrap.innerHTML = '<div class="empty-state">Chưa có bản chốt trong khoảng này</div>'; return; }
    const totalPages = Math.max(1, Math.ceil(CLOSURES.length / CLOSURES_PAGE_SIZE));
    const start = (CLOSURES_PAGE - 1) * CLOSURES_PAGE_SIZE;
    const pageItems = CLOSURES.slice(start, start + CLOSURES_PAGE_SIZE);
    pageItems.forEach(r => {
        const row = document.createElement('div');
        row.className = 'revenue-row';
        row.style.cursor = 'pointer';
        row.innerHTML = `
            <div class="revenue-info">
                <div class="revenue-label">${r.date}</div>
                <div class="revenue-details">Đơn: ${r.total_orders}</div>
            </div>
            <div class="revenue-amount">${Number(r.total_revenue||0).toLocaleString('vi-VN')} ₫</div>`;
        row.onclick = () => openClosureDetailModal(r.date);
        wrap.appendChild(row);
    });
    // pager
    let pager = document.getElementById('closuresPager');
    if (!pager){
        pager = document.createElement('div');
        pager.id = 'closuresPager';
        pager.className = 'd-flex justify-content-center py-2';
        wrap.parentElement.appendChild(pager);
    }
    renderPager('closuresPager', CLOSURES_PAGE, totalPages, (p)=>{ CLOSURES_PAGE = p; renderClosures(); });
}
async function closeRevenueDay(){
    const date = document.getElementById('dateTo').value || new Date().toISOString().split('T')[0];
    if (!confirm(`Chốt doanh thu ngày ${date}?`)) return;
    try {
        const res = await AdminAPI.request('/admin/revenue/close-day', { method: 'POST', body: JSON.stringify({ date }) });
        alert(`Đã chốt doanh thu ngày ${date}: ${Number(res.total_revenue||0).toLocaleString('vi-VN')} ₫ (${res.total_orders||0} đơn)`);
        loadRevenueData();
    } catch(e){
        if (e && e.status === 409) {
            alert('Ngày này đã được chốt trước đó.');
        } else {
            console.error('Close day error', e);
            alert('Không thể chốt doanh thu ngày.');
        }
    }
}
</script>

<!-- Close Day Modal -->
<div class="modal fade" id="closeDayModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Chốt doanh thu ngày</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 small text-secondary" id="cdDateInfo"></div>
        <div id="cdAlert" class="alert alert-warning d-none"></div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>Món</th>
                <th class="text-center" style="width:100px;">SL</th>
                <th class="text-end" style="width:140px;">Doanh thu</th>
              </tr>
            </thead>
            <tbody id="cdItemsBody"></tbody>
            <tfoot>
              <tr>
                <td colspan="2" class="text-end fw-semibold">Tổng doanh thu ngày</td>
                <td class="text-end fw-bold" id="cdTotal">0 ₫</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary" onclick="printCloseDay()">In tổng hợp</button>
        <div>
          <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">Hủy</button>
          <button type="button" class="btn btn-danger" onclick="confirmCloseDay()">Xác nhận chốt ngày</button>
        </div>
      </div>
    </div>
  </div>
  </div>

<!-- Modal: Xem chi tiết ngày đã chốt -->
<div class="modal fade" id="closureDetailModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Chi tiết doanh thu ngày</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 small text-secondary" id="cldDateInfo"></div>
        <div id="cldAlert" class="alert alert-warning d-none"></div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>Món</th>
                <th class="text-center" style="width:100px;">SL</th>
                <th class="text-end" style="width:140px;">Doanh thu</th>
              </tr>
            </thead>
            <tbody id="cldItemsBody"></tbody>
            <tfoot>
              <tr>
                <td colspan="2" class="text-end fw-semibold">Tổng doanh thu ngày</td>
                <td class="text-end fw-bold" id="cldTotal">0 ₫</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" onclick="printClosureDetail()">In tổng hợp</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>

<script>
let CLOSE_DAY_MODAL = null;
let CLOSURE_DETAIL_MODAL = null;
document.addEventListener('DOMContentLoaded', function(){
  const el = document.getElementById('closeDayModal');
  if (window.bootstrap && el) {
    CLOSE_DAY_MODAL = bootstrap.Modal.getOrCreateInstance(el, { backdrop: false, keyboard: true, focus: true });
    // A11y: avoid aria-hidden with focused descendant
    el.addEventListener('hide.bs.modal', function(){
      if (el.contains(document.activeElement)) {
        try { document.activeElement.blur(); } catch (e) {}
      }
    });
    el.addEventListener('hidden.bs.modal', function(){
      const trigger = document.querySelector('button[onclick="openCloseDayModal()"]');
      if (trigger && typeof trigger.focus === 'function') { trigger.focus(); }
      else { try { document.body.focus(); } catch (e) {} }
    });
  }
  
  const cldEl = document.getElementById('closureDetailModal');
  if (window.bootstrap && cldEl) {
    CLOSURE_DETAIL_MODAL = bootstrap.Modal.getOrCreateInstance(cldEl, { backdrop: false, keyboard: true, focus: true });
  }
});

async function openCloseDayModal(){
  const dateFrom = document.getElementById('dateFrom').value;
  const dateTo = document.getElementById('dateTo').value;
  const date = dateTo || new Date().toISOString().split('T')[0];
  document.getElementById('cdDateInfo').textContent = `Ngày: ${date}`;
  document.getElementById('cdAlert').classList.add('d-none');
  try {
    // Lấy theo menu (món) trong ngày để tổng hợp
    const res = await AdminAPI.request(`/revenue?from=${date}&to=${date}&type=menu`);
    const items = res && res.chartData ? res.chartData : [];
    const body = document.getElementById('cdItemsBody');
    body.innerHTML = '';
    let total = 0;
    items.forEach(it => {
      const qty = it.quantity || it.orders || 0;
      const val = Number(it.value||0); total += val;
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${it.label||'-'}</td><td class="text-center">${qty}</td><td class="text-end">${val.toLocaleString('vi-VN')} ₫</td>`;
      body.appendChild(tr);
    });
    document.getElementById('cdTotal').textContent = `${total.toLocaleString('vi-VN')} ₫`;
    CLOSE_DAY_MODAL?.show();
  } catch(e){
    console.error('openCloseDayModal error', e);
    const al = document.getElementById('cdAlert');
    al.textContent = 'Không thể tải dữ liệu tổng hợp ngày.';
    al.classList.remove('d-none');
  }
}

async function confirmCloseDay(){
  const date = document.getElementById('dateTo').value || new Date().toISOString().split('T')[0];
  try {
    const res = await AdminAPI.request('/admin/revenue/close-day', { method: 'POST', body: JSON.stringify({ date }) });
    alert(`Đã chốt doanh thu ngày ${date}: ${Number(res.total_revenue||0).toLocaleString('vi-VN')} ₫ (${res.total_orders||0} đơn)`);
    CLOSE_DAY_MODAL?.hide();
    loadRevenueData();
        // Nếu đang mở lịch sử chốt thì làm mới
        const card = document.getElementById('closuresCard');
        if (card && card.style.display !== 'none') { loadClosures(); }
  } catch(e){
    if (e && e.status === 409) {
      alert('Ngày này đã được chốt trước đó.');
    } else {
      alert('Không thể chốt doanh thu ngày.');
    }
  }
}

function printCloseDay(){
  try {
    const date = document.getElementById('dateTo').value || new Date().toISOString().split('T')[0];
    const rows = Array.from(document.querySelectorAll('#cdItemsBody tr'));
    let tr = rows.map(r => `<tr>${r.innerHTML}</tr>`).join('');
    const total = document.getElementById('cdTotal').textContent;
    const win = window.open('', 'PRINT', 'height=650,width=900,top=100,left=100');
    win.document.write(`
      <html><head><title>Chốt doanh thu ${date}</title>
      <style>body{font-family:Segoe UI,Roboto,Arial,sans-serif;padding:20px;color:#111} table{width:100%;border-collapse:collapse} th,td{border-bottom:1px solid #eee;padding:8px} th{text-align:left;background:#fafafa} .text-end{text-align:right}.text-center{text-align:center}</style>
      </head><body>
      <h3 style="margin:0 0 8px;">Tổng hợp doanh thu ngày ${date}</h3>
      <table><thead><tr><th>Món</th><th class="text-center">SL</th><th class="text-end">Doanh thu</th></tr></thead><tbody>${tr}</tbody>
      <tfoot><tr><td colspan="2" class="text-end"><b>Tổng</b></td><td class="text-end"><b>${total}</b></td></tr></tfoot></table>
      <script>window.onload=function(){window.focus();window.print();setTimeout(()=>window.close(),300);};<\/script>
      </body></html>`);
    win.document.close();
    win.focus();
  } catch(e){ alert('Không thể in tổng hợp'); }
}

async function openClosureDetailModal(date){
  document.getElementById('cldDateInfo').textContent = `Ngày: ${date}`;
  document.getElementById('cldAlert').classList.add('d-none');
  try {
    // Fetch revenue detail for this date
    const res = await AdminAPI.request(`/revenue?from=${date}&to=${date}&type=menu`);
    const items = res && res.chartData ? res.chartData : [];
    const body = document.getElementById('cldItemsBody');
    body.innerHTML = '';
    let total = 0;
    items.forEach(it => {
      const qty = it.quantity || it.orders || 0;
      const val = Number(it.value||0); total += val;
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${it.label||'-'}</td><td class="text-center">${qty}</td><td class="text-end">${val.toLocaleString('vi-VN')} ₫</td>`;
      body.appendChild(tr);
    });
    document.getElementById('cldTotal').textContent = `${total.toLocaleString('vi-VN')} ₫`;
    CLOSURE_DETAIL_MODAL?.show();
  } catch(e){
    console.error('openClosureDetailModal error', e);
    const al = document.getElementById('cldAlert');
    al.textContent = 'Không thể tải dữ liệu chi tiết ngày.';
    al.classList.remove('d-none');
  }
}

function printClosureDetail(){
  try {
    const date = document.getElementById('cldDateInfo').textContent.replace('Ngày: ', '');
    const rows = Array.from(document.querySelectorAll('#cldItemsBody tr'));
    let tr = rows.map(r => `<tr>${r.innerHTML}</tr>`).join('');
    const total = document.getElementById('cldTotal').textContent;
    const win = window.open('', 'PRINT', 'height=650,width=900,top=100,left=100');
    win.document.write(`
      <html><head><title>Chi tiết doanh thu ${date}</title>
      <style>body{font-family:Segoe UI,Roboto,Arial,sans-serif;padding:20px;color:#111} table{width:100%;border-collapse:collapse} th,td{border-bottom:1px solid #eee;padding:8px} th{text-align:left;background:#fafafa} .text-end{text-align:right}.text-center{text-align:center}</style>
      </head><body>
      <h3 style="margin:0 0 8px;">Chi tiết doanh thu ngày ${date}</h3>
      <table><thead><tr><th>Món</th><th class="text-center">SL</th><th class="text-end">Doanh thu</th></tr></thead><tbody>${tr}</tbody>
      <tfoot><tr><td colspan="2" class="text-end"><b>Tổng</b></td><td class="text-end"><b>${total}</b></td></tr></tfoot></table>
      <script>window.onload=function(){window.focus();window.print();setTimeout(()=>window.close(),300);};<\/script>
      </body></html>`);
    win.document.close();
    win.focus();
  } catch(e){ alert('Không thể in chi tiết'); }
}
</script>

<style>
.filter-section {
    padding: 20px;
}

.filter-controls {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-weight: 500;
    color: #555;
    font-size: 14px;
}

.filter-group select,
.filter-group input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.summary-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.card-icon {
    font-size: 32px;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card-content {
    flex: 1;
}

.card-title {
    font-size: 14px;
    color: #666;
    margin-bottom: 4px;
}

.card-value {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 20px 0;
}

.chart-container {
    padding: 20px;
    height: 400px;
}

.revenue-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.revenue-info {
    flex: 1;
}

.revenue-label {
    font-weight: 500;
    margin-bottom: 4px;
}

.revenue-details {
    font-size: 12px;
    color: #666;
}

.revenue-amount {
    font-weight: 600;
    color: #e74c3c;
    font-size: 16px;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #666;
    font-style: italic;
}

@media (max-width: 768px) {
    .filter-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .chart-container {
        height: 300px;
    }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
