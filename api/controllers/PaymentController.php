<?php

// Database already loaded in BaseController

class PaymentController extends BaseController {
    
    public function index() {
        try {
            $page = $this->getQueryParam('page', 1);
            $limit = $this->getQueryParam('limit', 20);
            $date = $this->getQueryParam('date');
            $payment_method = $this->getQueryParam('payment_method');
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($date) {
                $whereClause .= " AND DATE(o.created_at) = ?";
                $params[] = $date;
            }
            
            if ($payment_method) {
                $whereClause .= " AND o.payment_method = ?";
                $params[] = $payment_method;
            }
            
            // Count orders matching filters (acts as payments list)
            $countStmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM orders o $whereClause"
            );
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Paginated results
            $offset = ($page - 1) * $limit;
            $stmt = $this->db->prepare(
                "SELECT 
                    o.id,
                    o.order_number,
                    o.total_amount,
                    o.payment_status,
                    o.payment_method,
                    o.status,
                    o.created_at,
                    t.name AS table_name,
                    a.name AS area_name,
                    s.full_name AS staff_name
                FROM orders o
                INNER JOIN tables t ON o.table_id = t.id
                INNER JOIN areas a ON t.area_id = a.id
                LEFT JOIN users s ON o.user_id = s.id
                $whereClause
                ORDER BY o.created_at DESC 
                LIMIT ? OFFSET ?"
            );
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success([
                'payments' => $payments,
                'pagination' => $this->getPaginationInfo($page, $limit, $total)
            ]);
            
        } catch (Exception $e) {
            error_log("Get payments error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy danh sách thanh toán', 500);
        }
    }
    
    public function show($id) {
        try {
            // Return order payment snapshot by order id
            $stmt = $this->db->prepare(
                "SELECT 
                    o.id,
                    o.order_number,
                    o.total_amount AS order_total,
                    o.payment_status,
                    o.payment_method,
                    o.status,
                    o.created_at,
                    t.name AS table_name,
                    a.name AS area_name,
                    s.full_name AS staff_name
                FROM orders o
                INNER JOIN tables t ON o.table_id = t.id
                INNER JOIN areas a ON t.area_id = a.id
                LEFT JOIN users s ON o.user_id = s.id
                WHERE o.id = ?"
            );
            $stmt->execute([$id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                return Response::notFound('Không tìm thấy thanh toán');
            }
            
            return Response::success($payment);
            
        } catch (Exception $e) {
            error_log("Get payment error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thông tin thanh toán', 500);
        }
    }
    
    public function getByOrder($order_id) {
        try {
            // Return the order and items as a pseudo payment history
            $orderStmt = $this->db->prepare(
                "SELECT o.*, t.name AS table_name, u.full_name AS staff_name
                FROM orders o
                JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.id = ?"
            );
            $orderStmt->execute([$order_id]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) { return Response::notFound('Đơn hàng không tồn tại'); }
            
            $itemsStmt = $this->db->prepare(
                "SELECT oi.*, p.name AS item_name
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
                ORDER BY oi.created_at ASC"
            );
            $itemsStmt->execute([$order_id]);
            $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success($order);
            
        } catch (Exception $e) {
            error_log("Get payments by order error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thanh toán theo đơn hàng', 500);
        }
    }
    
    public function store() {
        try {
            $input = $this->getJsonInput();
            $user = $this->getAuthenticatedUser();
            
            $errors = $this->validateRequired($input, ['order_id', 'amount', 'payment_method']);
            if (!empty($errors)) {
                return Response::validationError($errors);
            }
            
            // Validate payment method (DB supports cash/card/transfer in proc)
            $allowedMethods = ['cash', 'card', 'transfer'];
            if (!in_array($input['payment_method'], $allowedMethods)) {
                return Response::validationError(['payment_method' => 'Phương thức thanh toán không hợp lệ']);
            }
            
            // Ensure order exists
            $orderStmt = $this->db->prepare(
                "SELECT id, total_amount, status 
                FROM orders 
                WHERE id = ? AND status IN ('pending', 'confirmed', 'preparing', 'ready', 'served')"
            );
            $orderStmt->execute([$input['order_id']]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                return Response::error('Đơn hàng không tồn tại hoặc chưa sẵn sàng thanh toán', 400);
            }
            
            $this->db->beginTransaction();
            
            // Mark order as completed and paid directly on orders table
            $updateOrderStmt = $this->db->prepare(
                "UPDATE orders 
                SET payment_status = 'paid', payment_method = ?, status = 'completed', updated_at = NOW() 
                WHERE id = ?"
            );
            $updateOrderStmt->execute([$input['payment_method'], $input['order_id']]);
            
            $this->db->commit();
            
            return Response::success([
                'order_id' => (int)$input['order_id'],
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => $input['payment_method']
            ], 'Thanh toán thành công', 201);
            
        } catch (Exception $e) {
            if (isset($this->db)) { $this->db->rollBack(); }
            error_log("Create payment error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
}
?>