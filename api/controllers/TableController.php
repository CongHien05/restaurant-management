<?php

// Database already loaded in BaseController

class TableController extends BaseController {
    
    public function index() {
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $area_id = $_GET['area_id'] ?? null;
            $status = $_GET['status'] ?? null;
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($area_id) {
                $whereClause .= " AND t.area_id = ?";
                $params[] = $area_id;
            }
            
            if ($status) {
                $whereClause .= " AND t.status = ?";
                $params[] = $status;
            }
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM tables t 
                INNER JOIN areas a ON t.area_id = a.id 
                $whereClause
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get paginated results with pending amount
            $offset = ($page - 1) * $limit;
            $stmt = $this->db->prepare("
                SELECT 
                    t.*, 
                    a.name as area_name,
                    CASE 
                        WHEN EXISTS(SELECT 1 FROM orders o WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled')) 
                        THEN 'occupied' 
                        ELSE t.status 
                    END as actual_status,
                    COALESCE((
                        SELECT SUM(o2.total_amount) 
                        FROM orders o2 
                        WHERE o2.table_id = t.id AND o2.status NOT IN ('completed', 'cancelled')
                    ), 0) as pending_amount,
                    COALESCE((
                        SELECT COUNT(o3.id) 
                        FROM orders o3 
                        WHERE o3.table_id = t.id AND o3.status NOT IN ('completed', 'cancelled')
                    ), 0) as active_orders,
                    (SELECT o.id FROM orders o 
                     WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled') 
                     ORDER BY o.created_at DESC LIMIT 1) as current_order_id,
                    (SELECT o.total_amount FROM orders o 
                     WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled') 
                     ORDER BY o.created_at DESC LIMIT 1) as total_amount,
                    (SELECT o.status FROM orders o 
                     WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled') 
                     ORDER BY o.created_at DESC LIMIT 1) as order_status,
                    (SELECT o.created_at FROM orders o 
                     WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled') 
                     ORDER BY o.created_at DESC LIMIT 1) as order_created_at
                FROM tables t 
                INNER JOIN areas a ON t.area_id = a.id 
                $whereClause
                ORDER BY a.name, t.name 
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPages = ceil($total / $limit);
            $pagination = [
                'current_page' => (int) $page,
                'per_page' => (int) $limit,
                'total' => (int) $total,
                'total_pages' => (int) $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ];
            
            return Response::success([
                'tables' => $tables,
                'pagination' => $pagination
            ]);
            
        } catch (Exception $e) {
            error_log("Get tables error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy danh sách bàn', 500);
        }
    }
    
    public function show($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    t.*, 
                    a.name as area_name,
                    CASE 
                        WHEN EXISTS(SELECT 1 FROM orders o WHERE o.table_id = t.id AND o.status IN ('pending', 'confirmed', 'preparing', 'ready')) 
                        THEN 'occupied' 
                        ELSE t.status 
                    END as actual_status,
                    COALESCE((
                        SELECT SUM(o2.total_amount) 
                        FROM orders o2 
                        WHERE o2.table_id = t.id AND o2.status NOT IN ('completed', 'cancelled')
                    ), 0) as pending_amount,
                    COALESCE((
                        SELECT COUNT(o3.id) 
                        FROM orders o3 
                        WHERE o3.table_id = t.id AND o3.status NOT IN ('completed', 'cancelled')
                    ), 0) as active_orders,
                    (SELECT o.id FROM orders o 
                     WHERE o.table_id = t.id AND o.status IN ('pending', 'confirmed', 'preparing', 'ready') 
                     ORDER BY o.created_at DESC LIMIT 1) as current_order_id,
                    (SELECT o.total_amount FROM orders o 
                     WHERE o.table_id = t.id AND o.status IN ('pending', 'confirmed', 'preparing', 'ready') 
                     ORDER BY o.created_at DESC LIMIT 1) as total_amount,
                    (SELECT o.status FROM orders o 
                     WHERE o.table_id = t.id AND o.status IN ('pending', 'confirmed', 'preparing', 'ready') 
                     ORDER BY o.created_at DESC LIMIT 1) as order_status,
                    (SELECT o.created_at FROM orders o 
                     WHERE o.table_id = t.id AND o.status IN ('pending', 'confirmed', 'preparing', 'ready') 
                     ORDER BY o.created_at DESC LIMIT 1) as order_created_at
                FROM tables t 
                INNER JOIN areas a ON t.area_id = a.id
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            $table = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$table) {
                return Response::notFound('Không tìm thấy bàn');
            }
            
            // Get current order if exists
            $orderStmt = $this->db->prepare("
                SELECT o.*, s.full_name as staff_name
                FROM orders o 
                LEFT JOIN users s ON o.user_id = s.id
                WHERE o.table_id = ? AND o.status NOT IN ('completed','cancelled')
                ORDER BY o.created_at DESC 
                LIMIT 1
            ");
            $orderStmt->execute([$id]);
            $currentOrder = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            $table['current_order'] = $currentOrder ?: null;
            
            return Response::success($table);
            
        } catch (Exception $e) {
            error_log("Get table error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thông tin bàn', 500);
        }
    }
    
    public function getByArea($area_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, a.name as area_name,
                       CASE 
                           WHEN EXISTS(SELECT 1 FROM orders o WHERE o.table_id = t.id AND o.status IN ('pending', 'confirmed', 'preparing', 'ready')) 
                           THEN 'occupied' 
                           ELSE t.status 
                       END as actual_status
                FROM tables t 
                INNER JOIN areas a ON t.area_id = a.id 
                WHERE t.area_id = ?
                ORDER BY t.name
            ");
            $stmt->execute([$area_id]);
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success($tables);
            
        } catch (Exception $e) {
            error_log("Get tables by area error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy danh sách bàn theo khu vực', 500);
        }
    }
    
    public function store() {
        try {
            $input = $this->getJsonInput();
            
            $errors = $this->validateRequired($input, ['area_id', 'name', 'capacity']);
            if (!empty($errors)) {
                return Response::validationError($errors);
            }
            
            // Check if table name exists in area
            $checkStmt = $this->db->prepare("
                SELECT id FROM tables WHERE area_id = ? AND name = ?
            ");
            $checkStmt->execute([$input['area_id'], $input['name']]);
            if ($checkStmt->fetch()) {
                return Response::error('Tên bàn đã tồn tại trong khu vực này', 400);
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO tables (area_id, name, capacity, status) 
                VALUES (?, ?, ?, ?)
            ");
            
            $success = $stmt->execute([
                $input['area_id'],
                $input['name'],
                $input['capacity'],
                $input['status'] ?? 'available'
            ]);
            
            if ($success) {
                $tableId = $this->db->lastInsertId();
                return Response::success(['id' => $tableId], 'Tạo bàn thành công', 201);
            }
            
            return Response::error('Lỗi khi tạo bàn', 500);
            
        } catch (Exception $e) {
            error_log("Create table error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function updateStatus($id) {
        try {
            $input = $this->getJsonInput();
            
            if (!isset($input['status'])) {
                return Response::validationError(['status' => 'Trạng thái là bắt buộc']);
            }
            
            $allowedStatuses = ['available', 'occupied', 'reserved', 'maintenance'];
            if (!in_array($input['status'], $allowedStatuses)) {
                return Response::validationError(['status' => 'Trạng thái không hợp lệ']);
            }
            
            $stmt = $this->db->prepare("
                UPDATE tables 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $success = $stmt->execute([$input['status'], $id]);
            
            if ($success) {
                return Response::success(null, 'Cập nhật trạng thái bàn thành công');
            }
            
            return Response::error('Lỗi khi cập nhật trạng thái bàn', 500);
            
        } catch (Exception $e) {
            error_log("Update table status error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function updateOrderInfo($id) {
        try {
            $input = $this->getJsonInput();
            error_log("updateOrderInfo called for table $id with input: " . json_encode($input));
            
            if (!isset($input['current_order_id']) || !isset($input['total_amount'])) {
                return Response::validationError([
                    'current_order_id' => 'ID đơn hàng là bắt buộc',
                    'total_amount' => 'Tổng tiền là bắt buộc'
                ]);
            }
            
            // Update order's total_amount if order exists
            if ($input['current_order_id']) {
                $orderStmt = $this->db->prepare("
                    SELECT status, created_at 
                    FROM orders 
                    WHERE id = ?
                ");
                $orderStmt->execute([$input['current_order_id']]);
                $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($order) {
                    // Update order's total_amount
                    $updateOrderTotalStmt = $this->db->prepare("
                        UPDATE orders 
                        SET total_amount = ?, 
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $success = $updateOrderTotalStmt->execute([
                        $input['total_amount'],
                        $input['current_order_id']
                    ]);
                    error_log("Updated order {$input['current_order_id']} total_amount to {$input['total_amount']}");
                } else {
                    $success = false;
                }
            } else {
                $success = true; // No order to update
            }
            
            if ($success) {
                return Response::success(null, 'Cập nhật thông tin đơn hàng bàn thành công');
            }
            
            return Response::error('Lỗi khi cập nhật thông tin đơn hàng bàn', 500);
            
        } catch (Exception $e) {
            error_log("Update table order info error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function getCurrentOrder($id) {
        try {
            // Get the most recent active order for this table (including served but not yet paid)
            $stmt = $this->db->prepare("
                SELECT 
                    o.*,
                    s.full_name as staff_name,
                    s.username as username
                FROM orders o
                LEFT JOIN users s ON o.user_id = s.id
                WHERE o.table_id = ? 
                AND o.status IN ('pending', 'confirmed', 'preparing', 'ready', 'served')
                ORDER BY o.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                // Get order items
                $itemStmt = $this->db->prepare("
                    SELECT 
                        oi.*,
                        m.name as item_name,
                        m.price as menu_price,
                        m.image as menu_image_url,
                        c.name as category_name
                    FROM order_items oi
                    INNER JOIN products m ON oi.product_id = m.id
                    LEFT JOIN categories c ON m.category_id = c.id
                    WHERE oi.order_id = ?
                    ORDER BY oi.created_at ASC
                ");
                $itemStmt->execute([$order['id']]);
                $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $order['items'] = $items;

                // Also return pending approval items for this order so staff can see items being added but not yet approved
                try {
                    $pendingStmt = $this->db->prepare("
                        SELECT koi.id, koi.product_id, koi.item_name, koi.quantity, koi.special_instructions
                        FROM kitchen_order_items koi
                        JOIN kitchen_orders ko ON koi.kitchen_order_id = ko.id
                        WHERE ko.order_id = ? AND ko.status = 'pending_approval'
                        ORDER BY koi.created_at ASC
                    ");
                    $pendingStmt->execute([$order['id']]);
                    $pendingItems = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
                    $order['pending_items'] = $pendingItems;
                } catch (Exception $e) {
                    $order['pending_items'] = [];
                    error_log('getCurrentOrder: pending_items load failed - ' . $e->getMessage());
                }
                
                return Response::success(['order' => $order]);
            }
            // Không có đơn hoạt động: thử lấy pending ticket gần nhất để hiển thị món đang chờ
            try {
                $pendingKo = $this->db->prepare("
                    SELECT ko.id, ko.order_id
                    FROM kitchen_orders ko
                    JOIN orders o ON ko.order_id = o.id
                    WHERE o.table_id = ? AND ko.status = 'pending_approval'
                    ORDER BY ko.created_at DESC
                    LIMIT 1
                ");
                $pendingKo->execute([$id]);
                $ko = $pendingKo->fetch(PDO::FETCH_ASSOC);
                if ($ko) {
                    $pendingItemsStmt = $this->db->prepare("
                        SELECT koi.id, koi.product_id, koi.item_name, koi.quantity, koi.special_instructions
                        FROM kitchen_order_items koi
                        WHERE koi.kitchen_order_id = ?
                        ORDER BY koi.created_at ASC
                    ");
                    $pendingItemsStmt->execute([$ko['id']]);
                    $pendingItems = $pendingItemsStmt->fetchAll(PDO::FETCH_ASSOC);
                    $fallback = [
                        'id' => (int)$ko['order_id'],
                        'status' => 'pending',
                        'items' => [],
                        'pending_items' => $pendingItems
                    ];
                    return Response::success(['order' => $fallback]);
                }
            } catch (Exception $e) { error_log('getCurrentOrder pending fallback failed - ' . $e->getMessage()); }

            return Response::success(['order' => null]);
            
        } catch (Exception $e) {
            error_log("Get current order error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thông tin đơn hàng hiện tại', 500);
        }
    }
    
    public function update($id) {
        try {
            $input = $this->getJsonInput();
            
            $errors = $this->validateRequired($input, ['area_id', 'name', 'capacity']);
            if (!empty($errors)) {
                return Response::validationError($errors);
            }
            
            // Check if table exists
            $checkStmt = $this->db->prepare("SELECT id FROM tables WHERE id = ?");
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                return Response::error('Bàn không tồn tại', 404);
            }
            
            // Check if table name exists in area (excluding current table)
            $checkStmt = $this->db->prepare("
                SELECT id FROM tables WHERE area_id = ? AND name = ? AND id != ?
            ");
            $checkStmt->execute([$input['area_id'], $input['name'], $id]);
            if ($checkStmt->fetch()) {
                return Response::error('Tên bàn đã tồn tại trong khu vực này', 400);
            }
            
            $stmt = $this->db->prepare("
                UPDATE tables 
                SET area_id = ?, name = ?, capacity = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $success = $stmt->execute([
                $input['area_id'],
                $input['name'],
                $input['capacity'],
                $input['status'] ?? 'available',
                $id
            ]);
            
            if ($success) {
                return Response::success(['id' => $id], 'Cập nhật bàn thành công');
            }
            
            return Response::error('Lỗi khi cập nhật bàn', 500);
            
        } catch (Exception $e) {
            error_log("Update table error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function delete($id) {
        try {
            // Check if table exists
            $checkStmt = $this->db->prepare("SELECT id FROM tables WHERE id = ?");
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                return Response::error('Bàn không tồn tại', 404);
            }
            
            // Check if table has active orders
            $orderStmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM orders 
                WHERE table_id = ? AND status IN ('pending', 'confirmed', 'preparing', 'ready')
            ");
            $orderStmt->execute([$id]);
            $activeOrders = $orderStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($activeOrders > 0) {
                return Response::error('Không thể xóa bàn đang có đơn hàng hoạt động', 400);
            }
            
            $stmt = $this->db->prepare("DELETE FROM tables WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                return Response::success(['id' => $id], 'Xóa bàn thành công');
            }
            
            return Response::error('Lỗi khi xóa bàn', 500);
            
        } catch (Exception $e) {
            error_log("Delete table error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
}
?>