<?php
require_once __DIR__ . '/auth.php';
$pageTitle = 'Bàn cần xác nhận - Panda Admin';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
    <div class="content">
        <header class="topbar">
            <div class="brand">Bàn cần xác nhận</div>
            <div class="nav">
                <div class="d-flex gap-2">
                    <select id="statusFilter" class="form-select form-select-sm" onchange="loadApprovals()">
                        <option value="pending_approval">Chờ duyệt</option>
                        <option value="approved">Đã duyệt</option>
                        <option value="printed">Đã in</option>
                        <option value="preparing">Đang chuẩn bị</option>
                        <option value="ready">Sẵn sàng</option>
                        <option value="served">Đã phục vụ</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                    <input id="searchInput" class="form-control form-control-sm" placeholder="Tìm bàn/phiếu..." oninput="renderApprovals(currentList)" />
                </div>
            </div>
        </header>
        <main class="container-fluid">
            <div class="card">
                <div class="card-body">
                    <div id="approvalsList" class="list-group"></div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="approvalModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Phiếu xác nhận</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="apAlert" class="alert alert-warning d-none"></div>
                    <div class="d-flex justify-content-between small text-secondary mb-2">
                        <div id="apInfo"></div>
                        <div id="apMeta"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Món</th><th class="text-center" style="width:100px;">SL</th><th>Ghi chú</th></tr>
                            </thead>
                            <tbody id="apItems"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" onclick="printApproval()">In thử</button>
                    <button id="apApproveBtn" class="btn btn-primary" onclick="approveAndPrint()">Duyệt + In</button>
                    <button class="btn btn-success" onclick="markPrinted()">Đã in</button>
                </div>
            </div>
        </div>
    </div>

<script>
let APPROVAL_MODAL = null;
let currentList = [];
let currentOrder = null;

document.addEventListener('DOMContentLoaded', function(){
    const el = document.getElementById('approvalModal');
    if (window.bootstrap && el) {
        APPROVAL_MODAL = bootstrap.Modal.getOrCreateInstance(el, { backdrop: false, keyboard: true, focus: true });
    }
    loadApprovals();
});

async function loadApprovals(){
    try{
        const status = document.getElementById('statusFilter').value || 'pending_approval';
        const res = await AdminAPI.request(`/admin/kitchen/orders?status=${encodeURIComponent(status)}`);
        currentList = (res && res.kitchen_orders) ? res.kitchen_orders : [];
        renderApprovals(currentList);
    }catch(e){ console.error('load approvals error', e); renderApprovals([]); }
}

function renderApprovals(list){
    const wrap = document.getElementById('approvalsList');
    const q = (document.getElementById('searchInput').value || '').toLowerCase();
    wrap.innerHTML = '';
    const items = (list||[]).filter(x=>{
        if (!q) return true;
        const hay = `${x.id||''} ${x.table_name||''} ${x.order_number||''} ${x.staff_name||''}`.toLowerCase();
        return hay.includes(q);
    });
    if (!items.length){ wrap.innerHTML = '<div class="text-muted p-3">Không có phiếu phù hợp</div>'; return; }
    items.forEach(o=>{
        const a = document.createElement('a');
        a.href = '#'; a.className = 'list-group-item list-group-item-action';
        a.onclick = (ev)=>{ ev.preventDefault(); openApproval(o.id); };
        a.innerHTML = `
            <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1">Bàn ${o.table_name || o.table_id} • Phiếu #${o.id}</h6>
                <small class="text-secondary">${(o.created_at||'').replace('T',' ').slice(0,19)}</small>
            </div>
            <div class="d-flex gap-3 small text-secondary">
                <span>Order: ${o.order_number||'—'}</span>
                <span>NV: ${o.staff_name||'—'}</span>
                <span class="badge text-bg-light border">${o.status}</span>
            </div>`;
        wrap.appendChild(a);
    });
}

async function openApproval(id){
    try{
        const res = await AdminAPI.request(`/admin/kitchen/orders/${id}`);
        currentOrder = res && res.kitchen_order ? res.kitchen_order : null;
        if (!currentOrder) return;
        document.getElementById('apAlert').classList.add('d-none');
        document.getElementById('apInfo').textContent = `Bàn ${currentOrder.table_name||currentOrder.table_id} • Phiếu #${currentOrder.id}`;
        document.getElementById('apMeta').textContent = `Order ${currentOrder.order_number||'—'} • NV: ${currentOrder.staff_name||'—'}`;
        const body = document.getElementById('apItems'); body.innerHTML = '';
        (currentOrder.items||[]).forEach(it=>{
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${it.item_name}</td><td class="text-center">${it.quantity}</td><td>${it.special_instructions||''}</td>`;
            body.appendChild(tr);
        });
        const approveBtn = document.getElementById('apApproveBtn');
        approveBtn.disabled = currentOrder.status !== 'pending_approval';
        APPROVAL_MODAL && APPROVAL_MODAL.show();
    }catch(e){ console.error('open approval error', e); showApAlert('Không thể tải phiếu.'); }
}

function showApAlert(msg){
    const el = document.getElementById('apAlert');
    el.textContent = msg; el.classList.remove('d-none');
}

async function approveAndPrint(){
    if (!currentOrder?.id) return;
    try{
        await AdminAPI.request(`/admin/kitchen/orders/${currentOrder.id}/approve`, { method: 'PUT' });
        await markPrinted();
        loadApprovals();
    }catch(e){ console.error('approve error', e); showApAlert('Không thể duyệt.'); }
}

async function markPrinted(){
    if (!currentOrder?.id) return;
    try{
        await AdminAPI.request(`/admin/kitchen/orders/${currentOrder.id}/status`, { method: 'PUT', body: JSON.stringify({ status: 'printed' }) });
        printApproval();
    }catch(e){ console.error('print error', e); showApAlert('Không thể cập nhật in.'); }
}

function printApproval(){
    try{
        const win = window.open('', 'PRINT', 'height=650,width=900,top=100,left=100');
        if (!win) return;
        const o = currentOrder||{}; const items = o.items||[];
        const rows = items.map(it=>`<tr><td>${it.item_name}</td><td class="text-center">${it.quantity}</td><td>${it.special_instructions||''}</td></tr>`).join('');
        win.document.write(`
            <html><head><title>Panda - Phiếu bếp #${o.id||''}</title>
            <style>body{font-family:Segoe UI,Roboto,Arial,sans-serif;padding:20px;color:#111}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border-bottom:1px solid #eee;padding:8px}th{text-align:left;background:#fafafa}.text-center{text-align:center}.muted{color:#6b7280}</style>
            </head><body>
            <div style="font-weight:700;font-size:18px">Phiếu bếp</div>
            <div class="muted" style="font-size:12px">Bàn ${o.table_name||o.table_id} • Phiếu #${o.id||''} • ${new Date().toLocaleString('vi-VN')}</div>
            <div class="muted" style="font-size:12px">Order ${o.order_number||'—'} • NV: ${o.staff_name||'—'}</div>
            <table><thead><tr><th>Món</th><th class="text-center">SL</th><th>Ghi chú</th></tr></thead><tbody>${rows}</tbody></table>
            <script>window.onload=function(){window.focus();window.print();setTimeout(()=>window.close(),300);};<\/script>
            </body></html>`);
        win.document.close(); win.focus();
    }catch(e){ console.error('print window error', e); }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


