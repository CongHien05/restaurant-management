<?php

// Database already loaded in BaseController

class KitchenController extends BaseController {
    
    public function getPendingOrders() {
        try {
            $status = $this->getQueryParam('status', 'submitted,confirmed');
            $statusArray = explode(',', $status);
            $placeholders = implode(',', array_fill(0, count($statusArray), '?'));
            
            $stmt = $this->db->prepare("
                SELECT o.*, 
                       t.table_number,
                       a.name as area_name,
                       s.full_name as staff_name,
                       COUNT(oi.id) as item_count
                FROM orders o 
                INNER JOIN tables t ON o.table_id = t.id
                INNER JOIN areas a ON t.area_id = a.id
                LEFT JOIN users s ON o.user_id = s.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.status IN ($placeholders)
                GROUP BY o.id
                ORDER BY o.created_at ASC
            ");
            $stmt->execute($statusArray);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get items for each order
            foreach ($orders as &$order) {
                $itemsStmt = $this->db->prepare("
                    SELECT oi.*, m.name as item_name
                    FROM order_items oi 
                    INNER JOIN menu_items m ON oi.menu_item_id = m.id
                    WHERE oi.order_id = ?
                    ORDER BY oi.id
                ");
                $itemsStmt->execute([$order['id']]);
                $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return Response::success($orders);
            
        } catch (Exception $e) {
            error_log("Get pending orders error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy đơn hàng chờ', 500);
        }
    }
    
    public function confirmOrder($id) {
        try {
            $user = $this->getAuthenticatedUser();
            
            // Check if order exists and can be confirmed
            $orderStmt = $this->db->prepare("
                SELECT status FROM orders WHERE id = ? AND status = 'submitted'
            ");
            $orderStmt->execute([$id]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return Response::error('Đơn hàng không tồn tại hoặc không thể xác nhận', 400);
            }
            
            $stmt = $this->db->prepare("
                UPDATE orders 
                SET status = 'confirmed', updated_at = NOW() 
                WHERE id = ?
            ");
            
            $success = $stmt->execute([$id]);
            
            if ($success) {
                return Response::success(null, 'Xác nhận đơn hàng thành công');
            }
            
            return Response::error('Lỗi khi xác nhận đơn hàng', 500);
            
        } catch (Exception $e) {
            error_log("Confirm order error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function markOrderReady($id) {
        try {
            $user = $this->getAuthenticatedUser();
            
            // Check if order exists and can be marked ready
            $orderStmt = $this->db->prepare("
                SELECT status FROM orders WHERE id = ? AND status IN ('confirmed', 'preparing')
            ");
            $orderStmt->execute([$id]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return Response::error('Đơn hàng không tồn tại hoặc không thể đánh dấu sẵn sàng', 400);
            }
            
            $stmt = $this->db->prepare("
                UPDATE orders 
                SET status = 'ready', updated_at = NOW() 
                WHERE id = ?
            ");
            
            $success = $stmt->execute([$id]);
            
            if ($success) {
                return Response::success(null, 'Đánh dấu đơn hàng sẵn sàng thành công');
            }
            
            return Response::error('Lỗi khi đánh dấu đơn hàng sẵn sàng', 500);
            
        } catch (Exception $e) {
            error_log("Mark order ready error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function getStats() {
        try {
            $date = $this->getQueryParam('date', date('Y-m-d'));
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(CASE WHEN status = 'submitted' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_orders,
                    COUNT(CASE WHEN status = 'preparing' THEN 1 END) as preparing_orders,
                    COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready_orders,
                    COUNT(CASE WHEN status = 'served' THEN 1 END) as served_orders,
                    AVG(CASE WHEN status IN ('ready', 'served', 'paid') 
                        THEN TIMESTAMPDIFF(MINUTE, created_at, updated_at) 
                        ELSE NULL END) as avg_preparation_time
                FROM orders 
                WHERE DATE(created_at) = ?
            ");
            $stmt->execute([$date]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get popular items
            $popularStmt = $this->db->prepare("
                SELECT m.name, SUM(oi.quantity) as total_quantity
                FROM order_items oi
                INNER JOIN menu_items m ON oi.menu_item_id = m.id
                INNER JOIN orders o ON oi.order_id = o.id
                WHERE DATE(o.created_at) = ?
                GROUP BY m.id, m.name
                ORDER BY total_quantity DESC
                LIMIT 5
            ");
            $popularStmt->execute([$date]);
            $popularItems = $popularStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['popular_items'] = $popularItems;
            
            return Response::success($stats);
            
        } catch (Exception $e) {
            error_log("Get kitchen stats error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thống kê bếp', 500);
        }
    }
}
?>