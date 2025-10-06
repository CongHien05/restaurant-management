<?php
$path = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
function active($file) { global $path; return $path === $file ? 'active' : ''; }
?>
<aside class="sidebar">
  <div class="sidebar-header">
    <h2>Panda Admin</h2>
  </div>
  
  <nav class="sidebar-nav">
    <ul>
      <li><a href="dashboard.php" class="<?php echo active('dashboard.php'); ?>">Bảng điều khiển</a></li>
      <li><a href="tables.php" class="<?php echo active('tables.php'); ?>">Quản lý bàn</a></li>
      <li><a href="menu.php" class="<?php echo active('menu.php'); ?>">Thực đơn</a></li>
      <li><a href="orders.php" class="<?php echo active('orders.php'); ?>">Đơn hàng</a></li>
      <li>
        <a href="approvals.php" class="<?php echo active('approvals.php'); ?>">
          Bàn cần xác nhận
          <span id="pendingApprovalBadge" class="badge bg-danger ms-2" style="display:none;">0</span>
        </a>
      </li>
      <li><a href="users.php" class="<?php echo active('users.php'); ?>">Nhân viên</a></li>
      <li><a href="revenue.php" class="<?php echo active('revenue.php'); ?>">Doanh thu</a></li>
    </ul>
  </nav>

</aside>