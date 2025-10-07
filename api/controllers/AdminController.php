<?php

// Database already loaded in BaseController

class AdminController extends BaseController {
    
    public function getStaff() {
        try {
            $page = $this->getQueryParam('page', 1);
            $limit = $this->getQueryParam('limit', 20);
            $role = $this->getQueryParam('role');
            $status = $this->getQueryParam('status');
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($role) {
                $whereClause .= " AND role = ?";
                $params[] = $role;
            }
            
            if ($status) {
                $whereClause .= " AND status = ?";
                $params[] = $status;
            }
            
            // Get total count
            $countStmt = $this->db->prepare("SELECT COUNT(*) as total FROM users $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get paginated results
            $offset = ($page - 1) * $limit;
            $stmt = $this->db->prepare("
                SELECT id, username, full_name, role, phone, email, status, created_at, updated_at
                FROM users 
                $whereClause
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success([
                'users' => $staff,
                'pagination' => $this->getPaginationInfo($page, $limit, $total)
            ]);
            
        } catch (Exception $e) {
            error_log("Get staff error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy danh sách nhân viên', 500);
        }
    }
    
    public function createStaff() {
        try {
            $input = $this->getJsonInput();
            
            $errors = $this->validateRequired($input, ['username', 'password', 'full_name', 'role']);
            if (!empty($errors)) {
                return Response::validationError($errors);
            }
            
            // Check if username exists
            $checkStmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->execute([$input['username']]);
            if ($checkStmt->fetch()) {
                return Response::error('Tên đăng nhập đã tồn tại', 400);
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password, full_name, role, phone, email, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $success = $stmt->execute([
                $input['username'],
                password_hash($input['password'], PASSWORD_DEFAULT),
                $input['full_name'],
                $input['role'],
                $input['phone'] ?? null,
                $input['email'] ?? null,
                $input['status'] ?? 'active'
            ]);
            
            if ($success) {
                $staffId = $this->db->lastInsertId();
                return Response::success(['id' => $staffId], 'Tạo nhân viên thành công', 201);
            }
            
            return Response::error('Lỗi khi tạo nhân viên', 500);
            
        } catch (Exception $e) {
            error_log("Create staff error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function updateStaff($id) {
        try {
            $input = $this->getJsonInput();
            
            $updateFields = [];
            $params = [];
            
            $allowedFields = ['full_name', 'role', 'phone', 'email', 'status'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }
            
            // Allow changing username with uniqueness check
            if (isset($input['username'])) {
                $newUsername = trim($input['username']);
                if ($newUsername === '') {
                    return Response::validationError(['username' => 'Tên đăng nhập không được để trống']);
                }
                // Check if username already used by another user
                $checkStmt = $this->db->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
                $checkStmt->execute([$newUsername, $id]);
                if ($checkStmt->fetch()) {
                    return Response::error('Tên đăng nhập đã tồn tại', 400);
                }
                $updateFields[] = "username = ?";
                $params[] = $newUsername;
            }
            
            if (isset($input['password'])) {
                $updateFields[] = "password = ?";
                $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                return Response::error('Không có dữ liệu để cập nhật', 400);
            }
            
            $params[] = $id;
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($params);
            
            if ($success && $stmt->rowCount() > 0) {
                return Response::success(null, 'Cập nhật nhân viên thành công');
            }
            
            return Response::notFound('Không tìm thấy nhân viên');
            
        } catch (Exception $e) {
            error_log("Update staff error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function deleteStaff($id) {
        try {
            // Don't allow deleting yourself
            $user = $this->getAuthenticatedUser();
            if ($user['user_id'] == $id) {
                return Response::error('Không thể xóa tài khoản của chính mình', 400);
            }
            
            // Check if staff has any orders
            $checkStmt = $this->db->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
            $checkStmt->execute([$id]);
            $orderCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($orderCount > 0) {
                // Soft delete - update status to inactive
                $stmt = $this->db->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                $success = $stmt->execute([$id]);
                
                if ($success && $stmt->rowCount() > 0) {
                    return Response::success(null, 'Vô hiệu hóa nhân viên thành công');
                }
            } else {
                // Hard delete
                $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
                $success = $stmt->execute([$id]);
                
                if ($success && $stmt->rowCount() > 0) {
                    return Response::success(null, 'Xóa nhân viên thành công');
                }
            }
            
            return Response::notFound('Không tìm thấy nhân viên');
            
        } catch (Exception $e) {
            error_log("Delete staff error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function getDailyReport() {
        try {
            $date = $this->getQueryParam('date', date('Y-m-d'));
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(o.id) as total_orders,
                    SUM(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE 0 END) as total_revenue,
                    COUNT(CASE WHEN o.status = 'completed' THEN 1 END) as paid_orders,
                    COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) as cancelled_orders,
                    AVG(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE NULL END) as avg_order_value
                FROM orders o
                WHERE DATE(o.created_at) = ?
            ");
            $stmt->execute([$date]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get hourly breakdown
            $hourlyStmt = $this->db->prepare("
                SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as order_count,
                    SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as revenue
                FROM orders 
                WHERE DATE(created_at) = ?
                GROUP BY HOUR(created_at)
                ORDER BY hour
            ");
            $hourlyStmt->execute([$date]);
            $hourlyData = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get top selling items
            $itemsStmt = $this->db->prepare("
                SELECT 
                    m.name,
                    SUM(oi.quantity) as quantity_sold,
                    SUM(oi.total_price) as revenue
                FROM order_items oi
                INNER JOIN products m ON oi.product_id = m.id
                INNER JOIN orders o ON oi.order_id = o.id
                WHERE DATE(o.created_at) = ? AND o.status = 'completed'
                GROUP BY m.id, m.name
                ORDER BY quantity_sold DESC
                LIMIT 10
            ");
            $itemsStmt->execute([$date]);
            $topItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success([
                'date' => $date,
                'summary' => $summary,
                'hourly_data' => $hourlyData,
                'top_items' => $topItems
            ]);
            
        } catch (Exception $e) {
            error_log("Get daily report error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy báo cáo ngày', 500);
        }
    }
    
    public function getSalesReport() {
        try {
            $start_date = $this->getQueryParam('start_date', date('Y-m-01'));
            $end_date = $this->getQueryParam('end_date', date('Y-m-d'));
            
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_orders,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as paid_orders,
                    SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as revenue
                FROM orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date
            ");
            $stmt->execute([$start_date, $end_date]);
            $dailyReport = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success([
                'start_date' => $start_date,
                'end_date' => $end_date,
                'daily_report' => $dailyReport
            ]);
            
        } catch (Exception $e) {
            error_log("Get sales report error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy báo cáo doanh thu', 500);
        }
    }
    
    public function getStats() {
        try {
            // Get today's date
            $today = date('Y-m-d');
            
            // Today's completed/paid orders count and revenue
            $todayOrdersStmt = $this->db->prepare("
                SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue 
                FROM orders 
                WHERE DATE(created_at) = ? AND status IN ('completed','paid')
            ");
            $todayOrdersStmt->execute([$today]);
            $todayStats = $todayOrdersStmt->fetch(PDO::FETCH_ASSOC);
            $todayRevenue = (float)$todayStats['revenue'];
            $todayCount = (int)$todayStats['count'];
            
            // Subtract closed revenue if any
            $closedStmt = $this->db->prepare("SELECT COALESCE(total_revenue,0) as closed_revenue, COALESCE(total_orders,0) as closed_orders FROM daily_revenue_closures WHERE date = ?");
            try { $closedStmt->execute([$today]); $closed = $closedStmt->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) { $closed = ['closed_revenue'=>0,'closed_orders'=>0]; }
            $closedRevenue = (float)($closed['closed_revenue'] ?? 0);
            $closedOrders = (int)($closed['closed_orders'] ?? 0);
            $unclosedRevenue = max(0, $todayRevenue - $closedRevenue);
            $unclosedOrders = max(0, $todayCount - $closedOrders);
            
            // Active tables count
            $activeTablesStmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM tables 
                WHERE status = 'occupied'
            ");
            $activeTablesStmt->execute();
            $activeTables = $activeTablesStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Pending orders count
            $pendingOrdersStmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM orders 
                WHERE status NOT IN ('completed', 'cancelled')
            ");
            $pendingOrdersStmt->execute();
            $pendingOrders = $pendingOrdersStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Total tables count
            $totalTablesStmt = $this->db->prepare("SELECT COUNT(*) as count FROM tables");
            $totalTablesStmt->execute();
            $totalTables = $totalTablesStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            return Response::success([
                'today_orders' => (int)$unclosedOrders,
                'today_revenue' => (float)$unclosedRevenue,
                'active_tables' => (int)$activeTables,
                'pending_orders' => (int)$pendingOrders,
                'total_tables' => (int)$totalTables
            ]);
            
        } catch (Exception $e) {
            error_log("Get stats error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thống kê', 500);
        }
    }

    public function closeRevenueDay() {
        try {
            $input = $this->getJsonInput();
            $date = $input['date'] ?? date('Y-m-d');
            $notes = $input['notes'] ?? null;
            $user = $this->getAuthenticatedUser();
            $userId = is_array($user) ? ($user['id'] ?? ($user['user_id'] ?? null)) : null;
            
            // Allow multiple closures per day: no unique restriction at API level (keep last as current snapshot)
            
            // Calculate totals for the date
            $sumStmt = $this->db->prepare("
                SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount),0) as total_revenue
                FROM orders
                WHERE DATE(created_at) = ? AND status IN ('completed','paid')
            ");
            $sumStmt->execute([$date]);
            $sum = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_orders'=>0, 'total_revenue'=>0];
            
            // Upsert: nếu đã có bản chốt cho ngày này thì cập nhật (lấy lần chốt mới nhất làm snapshot)
            $ins = $this->db->prepare("INSERT INTO daily_revenue_closures (date, total_orders, total_revenue, closed_by, notes, closed_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    total_orders = VALUES(total_orders),
                    total_revenue = VALUES(total_revenue),
                    closed_by = VALUES(closed_by),
                    notes = VALUES(notes),
                    closed_at = NOW()");
            $ins->execute([$date, (int)$sum['total_orders'], (float)$sum['total_revenue'], $userId, $notes]);
            
            return Response::success([
                'date' => $date,
                'total_orders' => (int)$sum['total_orders'],
                'total_revenue' => (float)$sum['total_revenue']
            ], 'Chốt doanh thu ngày thành công');
        } catch (Exception $e) {
            error_log('Close revenue day error: ' . $e->getMessage());
            return Response::error('Lỗi khi chốt doanh thu ngày: ' . $e->getMessage(), 500);
        }
    }

    public function getRevenueClosures() {
        try {
            $from = $this->getQueryParam('from', date('Y-m-01'));
            $to = $this->getQueryParam('to', date('Y-m-d'));
            $stmt = $this->db->prepare("SELECT * FROM daily_revenue_closures WHERE date BETWEEN ? AND ? ORDER BY date DESC");
            $stmt->execute([$from, $to]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return Response::success(['closures' => $rows]);
        } catch (Exception $e) {
            error_log('Get revenue closures error: ' . $e->getMessage());
            return Response::error('Lỗi khi lấy lịch sử chốt doanh thu', 500);
        }
    }

    // ============ ADMIN TABLE MANAGEMENT METHODS ============
    
    public function getTableDetails($tableId) {
        try {
            // Get table information
            $tableQuery = "SELECT t.id, t.name, a.name as area_name, t.status 
                           FROM tables t 
                           LEFT JOIN areas a ON t.area_id = a.id 
                           WHERE t.id = ?";
            $tableStmt = $this->db->prepare($tableQuery);
            $tableStmt->execute([$tableId]);
            $table = $tableStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$table) {
                return Response::error('Bàn không tồn tại', 404);
            }
            
            // Get current active order
            $orderQuery = "SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at,
                                  s.full_name as staff_name
                           FROM orders o 
                           LEFT JOIN users s ON o.user_id = s.id
                           WHERE o.table_id = ? AND o.status NOT IN ('completed', 'cancelled')
                           ORDER BY o.created_at DESC 
                           LIMIT 1";
            $orderStmt = $this->db->prepare($orderQuery);
            $orderStmt->execute([$tableId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            $orderItems = [];
            $pendingItems = [];
            if ($order) {
                // Get order items
                $itemsQuery = "SELECT oi.id, oi.quantity, oi.unit_price, oi.total_price as subtotal, oi.notes as special_instructions,
                                      m.name as item_name
                               FROM order_items oi
                               JOIN products m ON oi.product_id = m.id
                               WHERE oi.order_id = ?
                               ORDER BY oi.created_at ASC";
                $itemsStmt = $this->db->prepare($itemsQuery);
                $itemsStmt->execute([$order['id']]);
                $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                // Get pending approval items for this order (only new items not yet approved)
                $pendingQuery = "SELECT koi.id, koi.product_id, koi.item_name, koi.quantity, koi.special_instructions, ko.id as kitchen_order_id
                                 FROM kitchen_order_items koi
                                 JOIN kitchen_orders ko ON koi.kitchen_order_id = ko.id
                                 WHERE ko.order_id = ? AND ko.status = 'pending_approval'
                                 ORDER BY koi.created_at ASC";
                $pStmt = $this->db->prepare($pendingQuery);
                $pStmt->execute([$order['id']]);
                $pendingItems = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return Response::success([
                'table' => $table,
                'order' => $order,
                'order_items' => $orderItems,
                'pending_items' => $pendingItems
            ]);
            
        } catch (Exception $e) {
            error_log("Get table details error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thông tin bàn', 500);
        }
    }
    
    public function addTableItem($tableId) {
        try {
            $input = $this->getJsonInput();
            
            $errors = $this->validateRequired($input, ['product_id', 'quantity']);
            if (!empty($errors)) {
                return Response::validationError($errors);
            }
            
            $productId = $input['product_id'];
            $quantity = (int)$input['quantity'];
            $specialInstructions = $input['special_instructions'] ?? '';
            
            if ($quantity <= 0) {
                return Response::error('Số lượng phải lớn hơn 0', 400);
            }
            
            $this->db->beginTransaction();
            
            // Get product details
            $productQuery = "SELECT id, name, price FROM products WHERE id = ? AND status = 'active'";
            $productStmt = $this->db->prepare($productQuery);
            $productStmt->execute([$productId]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception('Món ăn không tồn tại hoặc không khả dụng');
            }
            
            // Check if table has active order, if not create one
            $orderQuery = "SELECT id FROM orders WHERE table_id = ? AND status NOT IN ('completed', 'cancelled') ORDER BY created_at DESC LIMIT 1";
            $orderStmt = $this->db->prepare($orderQuery);
            $orderStmt->execute([$tableId]);
            $existingOrder = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingOrder) {
                $orderId = $existingOrder['id'];
            } else {
                // Create new order with admin user_id
                $createOrderQuery = "INSERT INTO orders (table_id, order_number, user_id, status, total_amount, created_at) VALUES (?, ?, ?, 'pending', 0, NOW())";
                $createOrderStmt = $this->db->prepare($createOrderQuery);
                $orderNumber = 'ORD' . date('YmdHis') . rand(100, 999);
                $adminUser = $this->getAuthenticatedUser();
                $adminUserId = $adminUser ? $adminUser['id'] : 1; // Default to admin ID 1 if not found
                $createOrderStmt->execute([$tableId, $orderNumber, $adminUserId]);
                $orderId = $this->db->lastInsertId();
            }
            
            // Không thêm vào order_items ngay; upsert vào pending ticket hiện có (tạo mới nếu chưa có)
            try {
                // Lấy pending ticket gần nhất, nếu không có thì tạo ticket pending mới (forceNew=true)
                $koFind = $this->db->prepare("SELECT id FROM kitchen_orders WHERE order_id = ? AND status = 'pending_approval' ORDER BY id DESC LIMIT 1");
                $koFind->execute([$orderId]);
                $ko = $koFind->fetch(PDO::FETCH_ASSOC);
                if (!$ko) {
                    $this->createKitchenOrder($orderId, $tableId, ($this->getAuthenticatedUser()['id'] ?? null), true);
                    $koFind->execute([$orderId]);
                    $ko = $koFind->fetch(PDO::FETCH_ASSOC);
                }
                if ($ko && $ko['id']) {
                    // Upsert theo product: nếu đã có trong pending, cộng dồn số lượng
                    $findItem = $this->db->prepare("SELECT id, quantity FROM kitchen_order_items WHERE kitchen_order_id = ? AND product_id = ? ORDER BY id DESC LIMIT 1");
                    $findItem->execute([$ko['id'], $productId]);
                    $koi = $findItem->fetch(PDO::FETCH_ASSOC);
                    if ($koi) {
                        $upd = $this->db->prepare("UPDATE kitchen_order_items SET quantity = quantity + ?, special_instructions = ? WHERE id = ?");
                        $upd->execute([$quantity, $specialInstructions ?: $koi['special_instructions'] ?? null, $koi['id']]);
                    } else {
                        $koiIns = $this->db->prepare("INSERT INTO kitchen_order_items (kitchen_order_id, product_id, item_name, quantity, special_instructions) VALUES (?, ?, ?, ?, ?)");
                        $koiIns->execute([$ko['id'], $productId, $product['name'], $quantity, $specialInstructions]);
                    }
                }
            } catch (Exception $kex) {
                error_log('AddTableItem: pending KOI upsert failed - ' . $kex->getMessage());
            }
            
            // Cập nhật trạng thái bàn: nếu đơn đang ở các trạng thái phục vụ thì giữ 'occupied',
            // chỉ chuyển 'reserved' khi đơn còn đang pending chờ xác nhận ban đầu
            try {
                $ordStatusStmt = $this->db->prepare("SELECT status FROM orders WHERE id = ?");
                $ordStatusStmt->execute([$orderId]);
                $ordRow = $ordStatusStmt->fetch(PDO::FETCH_ASSOC);
                $st = strtolower($ordRow['status'] ?? 'pending');
                $servingStatuses = ['confirmed','preparing','ready','served'];
                if (in_array($st, $servingStatuses, true)) {
                    $this->db->prepare("UPDATE tables SET status = 'occupied' WHERE id = ?")->execute([$tableId]);
                } else {
                    $this->db->prepare("UPDATE tables SET status = 'reserved' WHERE id = ?")->execute([$tableId]);
                }
            } catch (Exception $tsEx) { error_log('update table status addTableItem: ' . $tsEx->getMessage()); }
            
            // Create notification for new order (limited to existing columns)
            try {
                $notificationQuery = "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())";
                $notificationStmt = $this->db->prepare($notificationQuery);
                $notificationTitle = "Đơn hàng mới - Bàn " . $tableId;
                $notificationMessage = "Nhân viên vừa thêm món '{$product['name']}' x{$quantity} cho bàn {$tableId}";
                $notificationStmt->execute([$adminUserId, $notificationTitle, $notificationMessage, 'order']);
            } catch (Exception $notifyEx) {
                error_log("AdminController addTableItem: notification insert failed - " . $notifyEx->getMessage());
            }
            
            // Không tạo snapshot đầy đủ ở đây để tránh trùng lặp; phần pending đã tạo ở trên khi cần
            
            $this->db->commit();
            
            return Response::success([
                'message' => 'Đã thêm món vào hàng chờ xác nhận',
                'order_id' => $orderId,
                'pending_item' => [
                    'name' => $product['name'],
                    'quantity' => $quantity
                ],
                'notification_created' => true
            ]);
            
        } catch (Exception $e) {
            if (isset($this->db)) {
                $this->db->rollBack();
            }
            error_log("Add table item error: " . $e->getMessage());
            return Response::error('Lỗi khi thêm món: ' . $e->getMessage(), 500);
        }
    }
    
    public function processTablePayment($tableId) {
        try {
            $input = $this->getJsonInput();
            
            $errors = $this->validateRequired($input, ['payment_method', 'received_amount']);
            if (!empty($errors)) {
                return Response::validationError($errors);
            }
            
            $paymentMethod = $input['payment_method'];
            $receivedAmount = (float)$input['received_amount'];
            
            if ($receivedAmount <= 0) {
                return Response::error('Số tiền nhận phải lớn hơn 0', 400);
            }
            
            $this->db->beginTransaction();
            
            // Get active order for the table
            $orderQuery = "SELECT id, order_number, total_amount, status FROM orders WHERE table_id = ? AND status NOT IN ('completed', 'cancelled') ORDER BY created_at DESC LIMIT 1";
            $orderStmt = $this->db->prepare($orderQuery);
            $orderStmt->execute([$tableId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                throw new Exception('Không có đơn hàng nào để thanh toán');
            }
            
            // Enforce order must be confirmed/preparing/ready/served before payment
            $eligibleStatuses = ['confirmed','preparing','ready','served'];
            $currentStatus = strtolower($order['status'] ?? '');
            if (!in_array($currentStatus, $eligibleStatuses, true)) {
                throw new Exception('Đơn chưa được xác nhận/chuẩn bị, không thể thanh toán');
            }
            
            $totalAmount = (float)$order['total_amount'];
            $change = $receivedAmount - $totalAmount;
            
            if ($receivedAmount < $totalAmount) {
                throw new Exception('Số tiền nhận ít hơn tổng tiền đơn hàng');
            }
            
            // Update order status to completed
            $updateOrderQuery = "UPDATE orders SET status = 'completed', payment_status = 'paid', payment_method = ?, updated_at = NOW() WHERE id = ?";
            $updateOrderStmt = $this->db->prepare($updateOrderQuery);
            $updateOrderStmt->execute([$paymentMethod, $order['id']]);
            // Clear order items to avoid leftovers on client apps fetching by order_id
            try {
                $this->db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order['id']]);
            } catch (Exception $e) { error_log('processTablePayment: clear items failed - ' . $e->getMessage()); }
            
            // Create payment record
            $paymentQuery = "INSERT INTO payments (order_id, amount, payment_method, received_amount, change_amount, created_at) 
                             VALUES (?, ?, ?, ?, ?, NOW())";
            $paymentStmt = $this->db->prepare($paymentQuery);
            $paymentStmt->execute([$order['id'], $totalAmount, $paymentMethod, $receivedAmount, $change]);
            
            // Update table status to available
            $updateTableQuery = "UPDATE tables SET 
                status = 'available', 
                updated_at = NOW()
                WHERE id = ?";
            $updateTableStmt = $this->db->prepare($updateTableQuery);
            $updateTableStmt->execute([$tableId]);
            
            // Close related kitchen order snapshots (cancel any pending approvals)
            try {
                $this->db->prepare("UPDATE kitchen_orders SET status = 'cancelled', updated_at = NOW() WHERE order_id = ? AND status = 'pending_approval'")->execute([$order['id']]);
                $this->db->prepare("UPDATE kitchen_orders SET status = 'served', updated_at = NOW() WHERE order_id = ? AND status NOT IN ('cancelled', 'served')")->execute([$order['id']]);
            } catch (Exception $e) { error_log('processTablePayment: close kitchen orders failed - ' . $e->getMessage()); }
            
            // Create notification for staff about payment completion (limited columns)
            $adminUser = $this->getAuthenticatedUser();
            $notificationQuery = "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())";
            $notificationStmt = $this->db->prepare($notificationQuery);
            $notificationTitle = "Thanh toán hoàn tất - Bàn " . $tableId;
            $notificationMessage = "Admin đã thanh toán đơn hàng {$order['order_number']} cho bàn {$tableId}. Tổng tiền: " . number_format($totalAmount, 0, ',', '.') . " ₫";
            $notificationStmt->execute([$adminUser['id'], $notificationTitle, $notificationMessage, 'payment']);
            
            $this->db->commit();
            
            return Response::success([
                'message' => 'Thanh toán thành công',
                'payment_details' => [
                    'order_number' => $order['order_number'],
                    'total_amount' => $totalAmount,
                    'received_amount' => $receivedAmount,
                    'change_amount' => $change,
                    'payment_method' => $paymentMethod
                ]
            ]);
            
        } catch (Exception $e) {
            if (isset($this->db)) {
                $this->db->rollBack();
            }
            error_log("Process table payment error: " . $e->getMessage());
            return Response::error('Lỗi khi thanh toán: ' . $e->getMessage(), 500);
        }
    }
    
    // ============ NOTIFICATION & KITCHEN METHODS ============
    
    public function getNotifications() {
        try {
            $page = $this->getQueryParam('page', 1);
            $limit = $this->getQueryParam('limit', 20);
            $unreadOnly = $this->getQueryParam('unread_only', false);
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($unreadOnly) {
                $whereClause .= " AND is_read = 0";
            }
            
            // Get total count
            $countStmt = $this->db->prepare("SELECT COUNT(*) as total FROM notifications $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get paginated results
            $offset = ($page - 1) * $limit;
            $stmt = $this->db->prepare("
                SELECT n.*, s.full_name as staff_name
                FROM notifications n
                LEFT JOIN users s ON n.user_id = s.id
                $whereClause
                ORDER BY n.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success([
                'notifications' => $notifications,
                'pagination' => $this->getPaginationInfo($page, $limit, $total)
            ]);
            
        } catch (Exception $e) {
            error_log("Get notifications error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thông báo', 500);
        }
    }
    
    public function markNotificationAsRead($notificationId) {
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
            $stmt->execute([$notificationId]);
            
            return Response::success(['message' => 'Đã đánh dấu đã đọc']);
            
        } catch (Exception $e) {
            error_log("Mark notification as read error: " . $e->getMessage());
            return Response::error('Lỗi khi đánh dấu đã đọc', 500);
        }
    }
    
    public function markAllNotificationsAsRead() {
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
            $stmt->execute();
            
            return Response::success(['message' => 'Đã đánh dấu tất cả đã đọc']);
            
        } catch (Exception $e) {
            error_log("Mark all notifications as read error: " . $e->getMessage());
            return Response::error('Lỗi khi đánh dấu tất cả đã đọc', 500);
        }
    }
    
    public function getKitchenOrders() {
        try {
            $status = $this->getQueryParam('status', 'pending_approval');
            
            $stmt = $this->db->prepare("
                SELECT ko.*, t.name as table_name
                FROM kitchen_orders ko
                JOIN tables t ON ko.table_id = t.id
                WHERE ko.status = ?
                ORDER BY ko.created_at ASC
            ");
            $stmt->execute([$status]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get items for each order
            foreach ($orders as &$order) {
                $itemsStmt = $this->db->prepare("
                    SELECT koi.*, m.name as item_name
                    FROM kitchen_order_items koi
                    JOIN products m ON koi.product_id = m.id
                    WHERE koi.kitchen_order_id = ?
                    ORDER BY koi.created_at ASC
                ");
                $itemsStmt->execute([$order['id']]);
                $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return Response::success(['kitchen_orders' => $orders]);
            
        } catch (Exception $e) {
            error_log("Get kitchen orders error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy đơn hàng bếp', 500);
        }
    }

    public function getKitchenOrder($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM kitchen_orders WHERE id = ?");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) return Response::notFound('Không tìm thấy phiếu bếp');
            $itemsStmt = $this->db->prepare("SELECT koi.*, m.name as item_name FROM kitchen_order_items koi JOIN products m ON koi.product_id = m.id WHERE koi.kitchen_order_id = ? ORDER BY koi.created_at ASC");
            $itemsStmt->execute([$id]);
            $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            return Response::success(['kitchen_order' => $order]);
        } catch (Exception $e) {
            error_log('Get kitchen order error: ' . $e->getMessage());
            return Response::error('Lỗi khi lấy phiếu bếp', 500);
        }
    }
    
    public function getPendingApprovalOrders() {
        try {
            $stmt = $this->db->prepare("
                SELECT ko.*, t.name as table_name
                FROM kitchen_orders ko
                JOIN tables t ON ko.table_id = t.id
                WHERE ko.status = 'pending_approval'
                ORDER BY ko.created_at ASC
            ");
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get items for each order
            foreach ($orders as &$order) {
                $itemsStmt = $this->db->prepare("
                    SELECT koi.*, m.name as item_name
                    FROM kitchen_order_items koi
                    JOIN products m ON koi.product_id = m.id
                    WHERE koi.kitchen_order_id = ?
                    ORDER BY koi.created_at ASC
                ");
                $itemsStmt->execute([$order['id']]);
                $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return Response::success(['pending_orders' => $orders]);
            
        } catch (Exception $e) {
            error_log("Get pending approval orders error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy đơn hàng chờ chấp nhận', 500);
        }
    }
    
    public function updateKitchenOrderStatus($kitchenOrderId) {
        try {
            $input = $this->getJsonInput();
            $status = $input['status'] ?? 'preparing';
            $user = $this->getAuthenticatedUser();
            $userId = is_array($user) ? ($user['id'] ?? ($user['user_id'] ?? null)) : null;
            
            if ($status === 'printed') {
                $stmt = $this->db->prepare("UPDATE kitchen_orders SET status = 'printed', printed_by = ?, printed_at = NOW(), print_count = print_count + 1, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$userId, $kitchenOrderId]);
            } else {
            $stmt = $this->db->prepare("UPDATE kitchen_orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $kitchenOrderId]);
            }
            
            return Response::success(['message' => 'Cập nhật trạng thái thành công']);
            
        } catch (Exception $e) {
            error_log("Update kitchen order status error: " . $e->getMessage());
            return Response::error('Lỗi khi cập nhật trạng thái', 500);
        }
    }
    
    public function approveKitchenOrder($kitchenOrderId) {
        try {
            $user = $this->getAuthenticatedUser();
            $userId = is_array($user) ? ($user['id'] ?? ($user['user_id'] ?? null)) : null;
            $this->db->beginTransaction();
            // Approve kitchen order
            $stmt = $this->db->prepare("UPDATE kitchen_orders SET status = 'approved', approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$userId, $kitchenOrderId]);

            // Move related order to 'served' to allow payment according to business rule
            $orderIdStmt = $this->db->prepare("SELECT order_id, table_id FROM kitchen_orders WHERE id = ?");
            $orderIdStmt->execute([$kitchenOrderId]);
            $row = $orderIdStmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['order_id']) {
                // Materialize approved items from this KO into order_items and update total
                $itemsStmt = $this->db->prepare("SELECT product_id, item_name, quantity, special_instructions FROM kitchen_order_items WHERE kitchen_order_id = ?");
                $itemsStmt->execute([$kitchenOrderId]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($items as $it) {
                    // fetch current price
                    $priceStmt = $this->db->prepare("SELECT price FROM products WHERE id = ?");
                    $priceStmt->execute([$it['product_id']]);
                    $priceRow = $priceStmt->fetch(PDO::FETCH_ASSOC);
                    $unit = (float)($priceRow['price'] ?? 0);
                    $deltaQty = (int)$it['quantity'];
                    if ($deltaQty > 0) {
                        // Increase or add
                        // Try update existing
                        $find = $this->db->prepare("SELECT id, quantity, unit_price FROM order_items WHERE order_id = ? AND product_id = ? ORDER BY id DESC LIMIT 1");
                        $find->execute([$row['order_id'], $it['product_id']]);
                        $found = $find->fetch(PDO::FETCH_ASSOC);
                        if ($found) {
                            $newQty = (int)$found['quantity'] + $deltaQty;
                            $upd = $this->db->prepare("UPDATE order_items SET quantity = ?, total_price = unit_price * ?, updated_at = NOW() WHERE id = ?");
                            $upd->execute([$newQty, $newQty, $found['id']]);
                        } else {
                            $total = $unit * $deltaQty;
                            $ins = $this->db->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, notes, created_at) VALUES (?, ?, ?, ?, ?, NULL, NOW())");
                            $ins->execute([$row['order_id'], $it['product_id'], $deltaQty, $unit, $total]);
                        }
                    } elseif ($deltaQty < 0) {
                        // Decrease or remove
                        $find = $this->db->prepare("SELECT id, quantity, unit_price FROM order_items WHERE order_id = ? AND product_id = ? ORDER BY id DESC LIMIT 1");
                        $find->execute([$row['order_id'], $it['product_id']]);
                        $found = $find->fetch(PDO::FETCH_ASSOC);
                        if ($found) {
                            $newQty = max(0, (int)$found['quantity'] + $deltaQty);
                            if ($newQty === 0 || strtoupper(trim((string)($it['special_instructions'] ?? ''))) === 'REMOVE') {
                                $this->db->prepare("DELETE FROM order_items WHERE id = ?")->execute([$found['id']]);
                            } else {
                                $upd = $this->db->prepare("UPDATE order_items SET quantity = ?, total_price = unit_price * ?, updated_at = NOW() WHERE id = ?");
                                $upd->execute([$newQty, $newQty, $found['id']]);
                            }
                        }
                    }
                }
                // Update order total based on order_items
                $updTotal = $this->db->prepare("UPDATE orders SET total_amount = (SELECT COALESCE(SUM(total_price),0) FROM order_items WHERE order_id = ?), updated_at = NOW() WHERE id = ?");
                $updTotal->execute([$row['order_id'], $row['order_id']]);
                // Only update if not completed/cancelled
                $updOrder = $this->db->prepare("UPDATE orders SET status = 'served', updated_at = NOW() WHERE id = ? AND status NOT IN ('completed','cancelled')");
                $updOrder->execute([$row['order_id']]);
                // Keep table occupied while serving
                if ($row['table_id']) {
                    $this->db->prepare("UPDATE tables SET status = 'occupied', updated_at = NOW() WHERE id = ?")->execute([$row['table_id']]);
                }
            }

            $this->db->commit();
            
            return Response::success(['message' => 'Đã chấp nhận đơn hàng cho bếp']);
            
        } catch (Exception $e) {
            if (isset($this->db)) { $this->db->rollBack(); }
            error_log("Approve kitchen order error: " . $e->getMessage());
            return Response::error('Lỗi khi chấp nhận đơn hàng', 500);
        }
    }
    
    public function addItemToExistingOrder($tableId, $orderId) {
        try {
            $input = $this->getJsonInput();
            
            $errors = $this->validateRequired($input, ['product_id', 'quantity']);
            if (!empty($errors)) {
                return Response::validationError($errors);
            }
            
            // Get order details
            $orderStmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return Response::error('Đơn hàng không tồn tại', 404);
            }
            
            // Get product details
            $productStmt = $this->db->prepare("SELECT * FROM products WHERE id = ?");
            $productStmt->execute([$input['product_id']]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                return Response::error('Món ăn không tồn tại', 404);
            }
            
            // Add item to order
            $itemStmt = $this->db->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $totalPrice = $product['price'] * $input['quantity'];
            $itemStmt->execute([
                $orderId,
                $input['product_id'],
                $input['quantity'],
                $product['price'],
                $totalPrice,
                $input['notes'] ?? null
            ]);
            
            // Update order total
            $totalStmt = $this->db->prepare("
                UPDATE orders SET total_amount = (
                    SELECT SUM(total_price) FROM order_items WHERE order_id = ?
                ), updated_at = NOW() WHERE id = ?
            ");
            $totalStmt->execute([$orderId, $orderId]);
            
            // Create notification for new item (limited columns per DB schema)
            $notificationStmt = $this->db->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
            
            $notificationTitle = "Thêm món - Bàn " . $tableId;
            $notificationMessage = "Admin vừa thêm món '{$product['name']}' x{$input['quantity']} cho bàn {$tableId}";
            
            $notificationStmt->execute([
                ($this->getAuthenticatedUser()['id'] ?? null),
                $notificationTitle,
                $notificationMessage,
                'order'
            ]);
            
            // Create kitchen order item for the new item only
            $this->createKitchenOrderItem($orderId, $input['product_id'], $input['quantity'], $input['notes'] ?? null);
            
            return Response::success(['message' => 'Đã thêm món thành công']);
            
        } catch (Exception $e) {
            error_log("Add item to existing order error: " . $e->getMessage());
            return Response::error('Lỗi khi thêm món', 500);
        }
    }
    
    private function createKitchenOrderItem($orderId, $productId, $quantity, $notes = null) {
        try {
            // Ensure a pending_approval kitchen order snapshot exists for new items
            $latestKoStmt = $this->db->prepare("SELECT id, status FROM kitchen_orders WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
            $latestKoStmt->execute([$orderId]);
            $kitchenOrder = $latestKoStmt->fetch(PDO::FETCH_ASSOC);
            if (!$kitchenOrder || strtolower($kitchenOrder['status']) !== 'pending_approval') {
                // Create a new pending_approval snapshot capturing only new items
                $orderStmt = $this->db->prepare("SELECT table_id FROM orders WHERE id = ?");
                $orderStmt->execute([$orderId]);
                $orderRow = $orderStmt->fetch(PDO::FETCH_ASSOC);
                if ($orderRow) { $this->createKitchenOrder($orderId, $orderRow['table_id'], ($this->getAuthenticatedUser()['id'] ?? null), true); }
                $latestKoStmt->execute([$orderId]);
                $kitchenOrder = $latestKoStmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Get product name
            $productStmt = $this->db->prepare("SELECT name FROM products WHERE id = ?");
            $productStmt->execute([$productId]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            
            // Add kitchen order item
            $kitchenItemStmt = $this->db->prepare("
                INSERT INTO kitchen_order_items (kitchen_order_id, product_id, item_name, quantity, special_instructions)
                VALUES (?, ?, ?, ?, ?)
            ");
            $kitchenItemStmt->execute([
                $kitchenOrder['id'],
                $productId,
                $product['name'],
                $quantity,
                $notes
            ]);
            
        } catch (Exception $e) {
            error_log("Create kitchen order item error: " . $e->getMessage());
        }
    }
    
    private function createKitchenOrder($orderId, $tableId, $staffId, $forceNew = false) {
        try {
            // Get order details
            $orderStmt = $this->db->prepare("
                SELECT o.*, t.name as table_name, s.full_name as staff_name
                FROM orders o
                JOIN tables t ON o.table_id = t.id
                JOIN users s ON o.user_id = s.id
                WHERE o.id = ?
            ");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                throw new Exception('Order not found');
            }
            
            if (!$forceNew) {
            // Check if kitchen order already exists
            $checkStmt = $this->db->prepare("SELECT id FROM kitchen_orders WHERE order_id = ?");
            $checkStmt->execute([$orderId]);
            if ($checkStmt->fetch()) {
                return; // Already exists
                }
            }
            
            // Create kitchen order with pending_approval status (schema-aligned)
            $kitchenOrderStmt = $this->db->prepare("
                INSERT INTO kitchen_orders (order_id, table_id, table_name, order_number, staff_name, status)
                VALUES (?, ?, ?, ?, ?, 'pending_approval')
            ");
            $kitchenOrderStmt->execute([
                $orderId,
                $tableId,
                $order['table_name'],
                $order['order_number'],
                $order['staff_name']
            ]);
            $kitchenOrderId = $this->db->lastInsertId();
            
            // For initial snapshot, copy existing order_items; for forceNew, leave empty to only hold new items
            if (!$forceNew) {
            $itemsStmt = $this->db->prepare("
                SELECT oi.*, m.name as item_name
                FROM order_items oi
                JOIN products m ON oi.product_id = m.id
                WHERE oi.order_id = ?
            ");
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                $kitchenItemStmt = $this->db->prepare("
                    INSERT INTO kitchen_order_items (kitchen_order_id, product_id, item_name, quantity, special_instructions)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $kitchenItemStmt->execute([
                    $kitchenOrderId,
                    $item['product_id'],
                    $item['item_name'],
                    $item['quantity'],
                    $item['notes'] ?? null
                ]);
                }
            }
            
        } catch (Exception $e) {
            error_log("Create kitchen order error: " . $e->getMessage());
        }
    }
    
    public function getRevenueReport() {
        try {
            $type = $this->getQueryParam('type', 'daily');
            $from = $this->getQueryParam('from', date('Y-m-d', strtotime('-30 days')));
            $to = $this->getQueryParam('to', date('Y-m-d'));
            $tableId = $this->getQueryParam('table_id');
            
            $whereClause = "WHERE o.status IN ('completed', 'paid') AND DATE(o.created_at) BETWEEN ? AND ?";
            $params = [$from, $to];
            
            if ($tableId) {
                $whereClause .= " AND o.table_id = ?";
                $params[] = $tableId;
            }
            
            // Get summary data
            $summaryStmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(o.total_amount) as total_revenue,
                    AVG(o.total_amount) as avg_order_value
                FROM orders o 
                $whereClause
            ");
            $summaryStmt->execute($params);
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get top item (use kitchen snapshots to avoid missing data when order_items are cleared after payment)
            // Filter by order completion date (updated_at when status changed to completed)
            $topItemStmt = $this->db->prepare("
                SELECT p.name, SUM(koi.quantity) as total_quantity
                FROM kitchen_order_items koi
                JOIN kitchen_orders ko ON koi.kitchen_order_id = ko.id
                JOIN orders o ON ko.order_id = o.id
                JOIN products p ON koi.product_id = p.id
                WHERE o.status IN ('completed','paid') AND DATE(o.updated_at) BETWEEN ? AND ?
                " . ($tableId ? " AND o.table_id = ?" : "") . "
                GROUP BY p.id, p.name
                ORDER BY total_quantity DESC
                LIMIT 1
            ");
            $topItemParams = [$from, $to];
            if ($tableId) { $topItemParams[] = $tableId; }
            $topItemStmt->execute($topItemParams);
            $topItem = $topItemStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get chart data based on type
            $chartData = [];
            switch ($type) {
                case 'daily':
                    $chartStmt = $this->db->prepare("
                        SELECT DATE(o.created_at) as label, SUM(o.total_amount) as value
                        FROM orders o 
                        $whereClause
                        GROUP BY DATE(o.created_at)
                        ORDER BY DATE(o.created_at)
                    ");
                    break;
                case 'monthly':
                    $chartStmt = $this->db->prepare("
                        SELECT DATE_FORMAT(o.created_at, '%Y-%m') as label, SUM(o.total_amount) as value
                        FROM orders o 
                        $whereClause
                        GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
                        ORDER BY DATE_FORMAT(o.created_at, '%Y-%m')
                    ");
                    break;
                case 'table':
                    $chartStmt = $this->db->prepare("
                        SELECT CONCAT('Bàn ', t.name) as label, SUM(o.total_amount) as value, o.table_id
                        FROM orders o 
                        JOIN tables t ON o.table_id = t.id
                        $whereClause
                        GROUP BY o.table_id, t.name
                        ORDER BY value DESC
                    ");
                    break;
                case 'menu':
                    // Use kitchen snapshots to compute per-item quantities and revenue (join product price)
                    // Filter by order completion date (updated_at when status changed to completed)
                    $chartStmt = $this->db->prepare("
                        SELECT p.name as label,
                               SUM(koi.quantity * p.price) as value,
                               SUM(koi.quantity) as quantity
                        FROM kitchen_order_items koi
                        JOIN kitchen_orders ko ON koi.kitchen_order_id = ko.id
                        JOIN orders o ON ko.order_id = o.id
                        JOIN products p ON koi.product_id = p.id
                        WHERE o.status IN ('completed','paid') AND DATE(o.updated_at) BETWEEN ? AND ?
                        " . ($tableId ? " AND o.table_id = ?" : "") . "
                        GROUP BY p.id, p.name
                        ORDER BY value DESC
                    ");
                    break;
            }
            
            $chartStmt->execute($params);
            $chartData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Enrich chartData with 'orders' count for daily/monthly/table types
            $detailData = $chartData;
            if (in_array($type, ['daily', 'monthly', 'table'])) {
                foreach ($detailData as &$item) {
                    // Count orders for this specific label/date/table
                    $countWhere = $whereClause;
                    $countParams = $params;
                    
                    if ($type === 'daily') {
                        $countWhere .= " AND DATE(o.created_at) = ?";
                        $countParams[] = $item['label'];
                    } elseif ($type === 'monthly') {
                        $countWhere .= " AND DATE_FORMAT(o.created_at, '%Y-%m') = ?";
                        $countParams[] = $item['label'];
                    } elseif ($type === 'table' && isset($item['table_id'])) {
                        // Already filtered by table_id if provided globally, otherwise count for this specific table
                        if (!$tableId) {
                            $countWhere .= " AND o.table_id = ?";
                            $countParams[] = $item['table_id'];
                        }
                    }
                    
                    $countStmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM orders o $countWhere");
                    $countStmt->execute($countParams);
                    $cnt = $countStmt->fetch(PDO::FETCH_ASSOC);
                    $item['orders'] = (int)($cnt['cnt'] ?? 0);
                }
            }
            
            return Response::success([
                'summary' => [
                    'total_revenue' => $summary['total_revenue'] ?? 0,
                    'total_orders' => $summary['total_orders'] ?? 0,
                    'avg_order_value' => $summary['avg_order_value'] ?? 0,
                    'top_item' => $topItem['name'] ?? null
                ],
                'chartData' => $chartData,
                'data' => $detailData
            ]);
            
        } catch (Exception $e) {
            error_log("Get revenue report error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy báo cáo doanh thu', 500);
        }
    }
    
}
?>