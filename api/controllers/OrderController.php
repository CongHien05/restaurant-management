<?php

// Database already loaded in BaseController

class OrderController extends BaseController {
    
    public function index() {
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $status = $_GET['status'] ?? null;
            $table_id = $_GET['table_id'] ?? null;
            $date = $_GET['date'] ?? null;
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($status) {
                $whereClause .= " AND o.status = ?";
                $params[] = $status;
            }
            
            if ($table_id) {
                $whereClause .= " AND o.table_id = ?";
                $params[] = $table_id;
            }
            
            if ($date) {
                $whereClause .= " AND DATE(o.created_at) = ?";
                $params[] = $date;
            }
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM orders o 
                $whereClause
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get paginated results
            $offset = ($page - 1) * $limit;
            $stmt = $this->db->prepare("
                SELECT o.*, 
                       t.name as table_name, 
                       a.name as area_name,
                       s.full_name as staff_name
                FROM orders o 
                INNER JOIN tables t ON o.table_id = t.id
                INNER JOIN areas a ON t.area_id = a.id
                LEFT JOIN users s ON o.user_id = s.id
                $whereClause
                ORDER BY o.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success([
                'orders' => $orders,
                'pagination' => $this->getPaginationInfo($page, $limit, $total)
            ]);
            
        } catch (Exception $e) {
            error_log("Get orders error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy danh sách đơn hàng', 500);
        }
    }
    
    public function show($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT o.*, 
                       t.name as table_name, 
                       a.name as area_name,
                       s.full_name as staff_name
                FROM orders o 
                INNER JOIN tables t ON o.table_id = t.id
                INNER JOIN areas a ON t.area_id = a.id
                LEFT JOIN users s ON o.user_id = s.id
                WHERE o.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return Response::notFound('Không tìm thấy đơn hàng');
            }
            
            // Get order items
            $itemsStmt = $this->db->prepare("
                SELECT oi.*, m.name as item_name, m.price as unit_price
                FROM order_items oi 
                INNER JOIN products m ON oi.product_id = m.id
                WHERE oi.order_id = ?
                ORDER BY oi.id
            ");
            $itemsStmt->execute([$id]);
            $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success($order);
            
        } catch (Exception $e) {
            error_log("Get order error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thông tin đơn hàng', 500);
        }
    }
    
    public function getByTable($table_id) {
        try {
            $status = $this->getQueryParam('status');
            
            $whereClause = "WHERE o.table_id = ?";
            $params = [$table_id];
            
            if ($status) {
                $whereClause .= " AND o.status = ?";
                $params[] = $status;
            }
            
            $stmt = $this->db->prepare("
                SELECT o.*, s.full_name as staff_name
                FROM orders o 
                LEFT JOIN users s ON o.user_id = s.id
                $whereClause
                ORDER BY o.created_at DESC
            ");
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success($orders);
            
        } catch (Exception $e) {
            error_log("Get orders by table error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy đơn hàng theo bàn', 500);
        }
    }
    
    public function store() {
        try {
            $input = $this->getJsonInput();
            $user = $this->getAuthenticatedUser();
            
            $errors = $this->validateRequired($input, ['table_id']);
            if (!empty($errors)) {
                return Response::validationError($errors);
            }
            
            $this->db->beginTransaction();
            
            // Generate order number
            $orderNumber = $this->generateOrderNumber();
            
            // Create order
            $stmt = $this->db->prepare("
                INSERT INTO orders (order_number, table_id, user_id, status, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $success = $stmt->execute([
                $orderNumber,
                $input['table_id'],
                $user['id'],
                'pending',
                $input['notes'] ?? null
            ]);
            
            if (!$success) {
                $this->db->rollBack();
                return Response::error('Lỗi khi tạo đơn hàng', 500);
            }
            
            $orderId = $this->db->lastInsertId();
            
            // Add items if provided
            if (isset($input['items']) && is_array($input['items'])) {
                foreach ($input['items'] as $item) {
                    if (!isset($item['product_id']) || !isset($item['quantity'])) {
                        continue;
                    }
                    
                    $itemStmt = $this->db->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, notes) 
                        SELECT ?, ?, ?, price, (price * ?), ?
                        FROM products 
                        WHERE id = ?
                    ");
                    
                    $itemStmt->execute([
                        $orderId,
                        $item['product_id'],
                        $item['quantity'],
                        $item['quantity'],
                        $item['notes'] ?? null,
                        $item['product_id']
                    ]);
                }
            }
            
            $this->db->commit();
            
            // Get the created order with full details
            $orderStmt = $this->db->prepare("
                SELECT o.*, 
                       t.name as table_name, 
                       a.name as area_name,
                       s.full_name as staff_name
                FROM orders o 
                INNER JOIN tables t ON o.table_id = t.id
                INNER JOIN areas a ON t.area_id = a.id
                LEFT JOIN users s ON o.user_id = s.id
                WHERE o.id = ?
            ");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            return Response::success($order, 'Tạo đơn hàng thành công', 201);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Create order error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    private function generateOrderNumber() {
        $date = date('Ymd');
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM orders 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
        return "ORD{$date}" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
    
    public function updateStatus($id) {
        try {
            $input = $this->getJsonInput();
            
            if (!isset($input['status'])) {
                return Response::validationError(['status' => 'Trạng thái là bắt buộc']);
            }
            
            $allowedStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'served', 'completed', 'cancelled'];
            if (!in_array($input['status'], $allowedStatuses)) {
                return Response::validationError(['status' => 'Trạng thái không hợp lệ']);
            }
            
            if ($input['status'] === 'completed') {
                // Hoàn tất: cập nhật đơn + payment, giải phóng bàn, đóng kitchen_orders, xóa món để bàn trống thật sự
                $stmt = $this->db->prepare("UPDATE orders SET status = 'completed', payment_status = 'paid', updated_at = NOW() WHERE id = ?");
                $success = $stmt->execute([$id]);
                try { $this->db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$id]); } catch (Exception $e) { error_log('clear items on complete: '.$e->getMessage()); }
                try { $this->db->prepare("UPDATE tables SET status = 'available', updated_at = NOW() WHERE id = (SELECT table_id FROM orders WHERE id = ?)")->execute([$id]); } catch (Exception $e) { error_log('free table on complete: '.$e->getMessage()); }
                // Cancel pending approvals and mark other kitchen orders as served
                try { 
                    $this->db->prepare("UPDATE kitchen_orders SET status = 'cancelled', updated_at = NOW() WHERE order_id = ? AND status = 'pending_approval'")->execute([$id]); 
                    $this->db->prepare("UPDATE kitchen_orders SET status = 'served', updated_at = NOW() WHERE order_id = ? AND status NOT IN ('cancelled', 'served')")->execute([$id]); 
                } catch (Exception $e) { error_log('close kitchen order on complete: '.$e->getMessage()); }
            } else {
                $stmt = $this->db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                $success = $stmt->execute([$input['status'], $id]);
            }
            
            if ($success && $stmt->rowCount() > 0) {
                return Response::success(null, 'Cập nhật trạng thái đơn hàng thành công');
            }
            
            return Response::notFound('Không tìm thấy đơn hàng');
            
        } catch (Exception $e) {
            error_log("Update order status error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function submit($id) {
        try {
            // Check if order exists and is in draft status
            $stmt = $this->db->prepare("
                SELECT status FROM orders WHERE id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return Response::notFound('Không tìm thấy đơn hàng');
            }
            
            if ($order['status'] !== 'pending') {
                return Response::validationError(['status' => 'Chỉ có thể submit đơn hàng ở trạng thái pending']);
            }
            
            // Update order status to confirmed
            $stmt = $this->db->prepare("
                UPDATE orders 
                SET status = 'confirmed', 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            
            $success = $stmt->execute([$id]);
            
            if ($success && $stmt->rowCount() > 0) {
                return Response::success(null, 'Gửi đơn hàng thành công');
            }
            
            return Response::error('Lỗi khi gửi đơn hàng', 500);
            
        } catch (Exception $e) {
            error_log("Submit order error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
}
?>