<?php
$path = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
function active($file) { global $path; return $path === $file ? 'active' : ''; }
?>
<!-- Mobile Menu Toggle Button (Bootstrap Offcanvas Trigger) -->
<button class="mobile-menu-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Toggle menu">
  ☰
</button>

<!-- Desktop Sidebar (Always visible on large screens) -->
<aside class="sidebar d-none d-lg-block" id="desktopSidebar">
  <div class="sidebar-header">
    <h2>🐼 Panda Admin</h2>
  </div>
  
  <nav class="sidebar-nav">
    <div class="list-group list-group-flush">
      <a href="dashboard.php" class="list-group-item list-group-item-action <?php echo active('dashboard.php'); ?>">
        📊 Bảng điều khiển
      </a>
      <a href="tables.php" class="list-group-item list-group-item-action <?php echo active('tables.php'); ?>">
        🪑 Quản lý bàn
      </a>
      <a href="menu.php" class="list-group-item list-group-item-action <?php echo active('menu.php'); ?>">
        🍽️ Thực đơn
      </a>
      <a href="approvals.php" class="list-group-item list-group-item-action <?php echo active('approvals.php'); ?>">
        ✅ Bàn cần xác nhận
        <span id="pendingApprovalBadge" class="badge bg-danger ms-2" style="display:none;">0</span>
      </a>
      <a href="users.php" class="list-group-item list-group-item-action <?php echo active('users.php'); ?>">
        👥 Nhân viên
      </a>
      <a href="revenue.php" class="list-group-item list-group-item-action <?php echo active('revenue.php'); ?>">
        💰 Doanh thu
      </a>
    </div>
  </nav>
</aside>

<!-- Mobile Sidebar (Bootstrap Offcanvas) -->
<div class="offcanvas offcanvas-start sidebar-offcanvas" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="mobileSidebarLabel">🐼 Panda Admin</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0">
    <nav class="sidebar-nav">
      <div class="list-group list-group-flush">
        <a href="dashboard.php" class="list-group-item list-group-item-action <?php echo active('dashboard.php'); ?>">
          📊 Bảng điều khiển
        </a>
        <a href="tables.php" class="list-group-item list-group-item-action <?php echo active('tables.php'); ?>">
          🪑 Quản lý bàn
        </a>
        <a href="menu.php" class="list-group-item list-group-item-action <?php echo active('menu.php'); ?>">
          🍽️ Thực đơn
        </a>
        <a href="approvals.php" class="list-group-item list-group-item-action <?php echo active('approvals.php'); ?>">
          ✅ Bàn cần xác nhận
          <span id="pendingApprovalBadgeMobile" class="badge bg-danger ms-2" style="display:none;">0</span>
        </a>
        <a href="users.php" class="list-group-item list-group-item-action <?php echo active('users.php'); ?>">
          👥 Nhân viên
        </a>
        <a href="revenue.php" class="list-group-item list-group-item-action <?php echo active('revenue.php'); ?>">
          💰 Doanh thu
        </a>
      </div>
    </nav>
  </div>
</div>

<script>
// Handle mobile menu navigation
(function() {
  const offcanvasEl = document.getElementById('mobileSidebar');
  if (!offcanvasEl) return;
  
  const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
  const menuLinks = offcanvasEl.querySelectorAll('a.list-group-item');
  
  menuLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      // Allow navigation to happen
      // Offcanvas will auto-close when page unloads
      offcanvas.hide();
    });
  });
})();
</script>