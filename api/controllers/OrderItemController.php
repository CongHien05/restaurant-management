<?php

// Database already loaded in BaseController

class OrderItemController extends BaseController {
    
    public function getByOrder($order_id) {
        try {
                    $stmt = $this->db->prepare("
            SELECT oi.*, m.name as item_name, m.price as unit_price, c.name as category_name
            FROM order_items oi 
            INNER JOIN products m ON oi.product_id = m.id
            INNER JOIN categories c ON m.category_id = c.id
            WHERE oi.order_id = ?
            ORDER BY oi.id
        ");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success($items);
            
        } catch (Exception $e) {
            error_log("Get order items error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy danh sách món ăn', 500);
        }
    }
    
    public function store($order_id) {
        try {
            error_log("OrderItemController::store called with order_id: $order_id");
            $input = $this->getJsonInput();
            error_log("Input data: " . json_encode($input));
            
            $errors = $this->validateRequired($input, ['product_id', 'quantity']);
            if (!empty($errors)) {
                error_log("Validation errors: " . json_encode($errors));
                return Response::validationError($errors);
            }
            
            // Check if order exists and is editable
            $orderStmt = $this->db->prepare("SELECT status FROM orders WHERE id = ?");
            $orderStmt->execute([$order_id]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            error_log("Order status: " . ($order ? $order['status'] : 'null'));
            
            if (!$order) {
                error_log("Order not found with id: $order_id");
                return Response::notFound('Không tìm thấy đơn hàng');
            }
            
            error_log("Order status: " . $order['status'] . " for order_id: $order_id");
            
            // Temporarily allow all statuses for debugging
            // if (!in_array($order['status'], ['draft', 'submitted', 'pending', 'confirmed'])) {
            //     error_log("Order status not allowed: " . $order['status']);
            //     return Response::error('Không thể thêm món vào đơn hàng đã xác nhận', 400);
            // }
            
            // Get menu item details
            $menuStmt = $this->db->prepare("SELECT id, name, price, status FROM products WHERE id = ?");
            $ok = $menuStmt->execute([$input['product_id']]);
            if (!$ok) {
                error_log("Menu SELECT failed for product_id=" . $input['product_id']);
            }
            $menuItem = $menuStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$menuItem) {
                return Response::notFound('Không tìm thấy món ăn');
            }
            
            if (($menuItem['status'] ?? null) != 'active') {
                error_log("Product not active: product_id=" . $input['product_id']);
                return Response::error('Món ăn hiện không có sẵn', 400);
            }
            
            // Check if item already exists in order
            $existingStmt = $this->db->prepare("
                SELECT id, quantity FROM order_items 
                WHERE order_id = ? AND product_id = ?
            ");
            $existingStmt->execute([$order_id, $input['product_id']]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update quantity
                $newQuantity = $existing['quantity'] + $input['quantity'];
                $updateStmt = $this->db->prepare("
                    UPDATE order_items 
                    SET quantity = ?, notes = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $success = $updateStmt->execute([
                    $newQuantity,
                    $input['notes'] ?? null,
                    $existing['id']
                ]);
                
                if ($success) {
                    // Get the updated order item with full details
                    $getItemStmt = $this->db->prepare("
                        SELECT oi.*, m.name as item_name, m.price as unit_price, c.name as category_name
                        FROM order_items oi 
                        INNER JOIN products m ON oi.product_id = m.id
                        INNER JOIN categories c ON m.category_id = c.id
                        WHERE oi.id = ?
                    ");
                    $getItemStmt->execute([$existing['id']]);
                    $orderItem = $getItemStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Ensure a kitchen order exists and sync the item quantity for approvals
                    try {
                        $this->ensureKitchenOrderAndUpsertItem($order_id, $input['product_id'], $orderItem['item_name'], $newQuantity, $input['notes'] ?? null);
                    } catch (Exception $kex) { error_log("Kitchen sync (update) failed: " . $kex->getMessage()); }

                    return Response::success($orderItem, 'Cập nhật số lượng thành công');
                }
            } else {
                // Add new item: ONLY to kitchen_order_items (pending approval), NOT to order_items
                // order_items will be populated when admin approves the kitchen order
                
                $itemName = $menuItem['name'];
                
                // Ensure a kitchen order exists and insert item for approvals
                try {
                    $this->ensureKitchenOrderAndUpsertItem($order_id, $input['product_id'], $itemName, (int)$input['quantity'], $input['notes'] ?? null);
                } catch (Exception $kex) { 
                    error_log("Kitchen sync (insert) failed: " . $kex->getMessage()); 
                    return Response::error('Lỗi khi thêm món: ' . $kex->getMessage(), 500);
                }
                
                // Return a mock order item for UI (actual item will be created on approval)
                $mockOrderItem = [
                    'id' => null, // Pending, no real order_item yet
                    'order_id' => $order_id,
                    'product_id' => $input['product_id'],
                    'item_name' => $itemName,
                    'quantity' => $input['quantity'],
                    'unit_price' => $menuItem['price'],
                    'total_price' => $menuItem['price'] * $input['quantity'],
                    'notes' => $input['notes'] ?? null,
                    'category_name' => null
                ];
                
                return Response::success($mockOrderItem, 'Yêu cầu thêm món đang chờ xác nhận', 201);
            }
            
            if (isset($stmt)) {
                $info = $stmt->errorInfo();
                error_log("OrderItemController::store insert failed: " . json_encode($info));
            }
            error_log("OrderItemController::store failed to add item for order_id=$order_id");
            return Response::error('Lỗi khi thêm món', 500);
            
        } catch (Exception $e) {
            error_log("Add order item error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return Response::error('Lỗi server: ' . $e->getMessage(), 500);
        }
    }
    
    public function update($order_id, $id) {
        try {
            error_log("OrderItemController::update called with order_id: $order_id, id: $id");
            $input = $this->getJsonInput();
            error_log("Update input data: " . json_encode($input));
            
            // Check if order exists and is editable
            $orderStmt = $this->db->prepare("SELECT status FROM orders WHERE id = ?");
            $orderStmt->execute([$order_id]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return Response::notFound('Không tìm thấy đơn hàng');
            }
            
            // Chỉ chặn khi đã hoàn tất hoặc huỷ
            if (in_array($order['status'], ['completed', 'cancelled'])) {
                return Response::error('Không thể sửa món vì trạng thái đơn không cho phép', 400);
            }
            
            $updateFields = [];
            $params = [];
            
            if (isset($input['quantity'])) {
                if ($input['quantity'] <= 0) {
                    return Response::validationError(['quantity' => 'Số lượng phải lớn hơn 0']);
                }
                // Nếu đơn đang phục vụ, không chỉnh trực tiếp order_items: tạo delta pending
                $servingStatuses = ['confirmed','preparing','ready','served'];
                if (in_array(strtolower($order['status']), $servingStatuses, true)) {
                    // Lấy item hiện tại để tính delta
                    $curStmt = $this->db->prepare("SELECT oi.*, m.name as item_name FROM order_items oi JOIN products m ON oi.product_id = m.id WHERE oi.order_id = ? AND oi.id = ?");
                    $curStmt->execute([$order_id, $id]);
                    $cur = $curStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$cur) return Response::notFound('Không tìm thấy món trong đơn');
                    $delta = (int)$input['quantity'] - (int)$cur['quantity'];
                    if ($delta === 0) return Response::success(null, 'Không có thay đổi số lượng');
                    // Delta dương: tăng; âm: giảm (lưu âm để pending thể hiện rõ)
                    try {
                        $this->ensureKitchenOrderAndUpsertItem($order_id, $cur['product_id'], $cur['item_name'], (int)$delta, $input['notes'] ?? 'DELTA');
                    } catch (Exception $kex) { error_log('Pending delta (update) failed: ' . $kex->getMessage()); }
                    return Response::success(['pending_delta' => $delta], 'Đã ghi nhận thay đổi, chờ xác nhận');
                }
                // Trường hợp chưa phục vụ: cho phép cập nhật trực tiếp
                $updateFields[] = "quantity = ?";
                $params[] = $input['quantity'];
                // Also update total_price = unit_price * quantity
                $updateFields[] = "total_price = unit_price * ?";
                $params[] = $input['quantity'];
            }
            
            if (isset($input['notes'])) {
                $updateFields[] = "notes = ?";
                $params[] = $input['notes'];
            }
            
            if (empty($updateFields)) {
                return Response::error('Không có dữ liệu để cập nhật', 400);
            }
            
            $params[] = $id;
            $params[] = $order_id;
            
            $sql = "UPDATE order_items SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ? AND order_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($params);
            
            if ($success && $stmt->rowCount() > 0) {
                // Recalculate order total
                $recalcStmt = $this->db->prepare("UPDATE orders SET total_amount = COALESCE((SELECT SUM(total_price) FROM order_items WHERE order_id = ?), 0), updated_at = NOW() WHERE id = ?");
                $recalcStmt->execute([$order_id, $order_id]);

                // Get the updated order item with full details
                $getItemStmt = $this->db->prepare("
                    SELECT oi.*, m.name as item_name, m.price as unit_price, c.name as category_name
                    FROM order_items oi 
                    INNER JOIN products m ON oi.product_id = m.id
                    INNER JOIN categories c ON m.category_id = c.id
                    WHERE oi.id = ?
                ");
                $getItemStmt->execute([$id]);
                $orderItem = $getItemStmt->fetch(PDO::FETCH_ASSOC);
                
                return Response::success($orderItem, 'Cập nhật món thành công');
            }
            
            return Response::notFound('Không tìm thấy món ăn trong đơn hàng');
            
        } catch (Exception $e) {
            error_log("Update order item error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function delete($order_id, $id) {
        try {
            error_log("OrderItemController::delete called with order_id: $order_id, id: $id");
            
            // Check if order exists and is editable
            $orderStmt = $this->db->prepare("SELECT status FROM orders WHERE id = ?");
            $orderStmt->execute([$order_id]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return Response::notFound('Không tìm thấy đơn hàng');
            }
            
            // Chỉ chặn khi đã hoàn tất hoặc huỷ
            if (in_array($order['status'], ['completed', 'cancelled'])) {
                return Response::error('Không thể xóa món vì trạng thái đơn không cho phép', 400);
            }
            
            $servingStatuses = ['confirmed','preparing','ready','served'];
            if (in_array(strtolower($order['status']), $servingStatuses, true)) {
                // Khi đang phục vụ: không xoá ngay; tạo delta pending giảm bằng toàn bộ số lượng hiện tại
                $curStmt = $this->db->prepare("SELECT oi.*, m.name as item_name FROM order_items oi JOIN products m ON oi.product_id = m.id WHERE oi.order_id = ? AND oi.id = ?");
                $curStmt->execute([$order_id, $id]);
                $cur = $curStmt->fetch(PDO::FETCH_ASSOC);
                if (!$cur) return Response::notFound('Không tìm thấy món trong đơn');
                $delta = -1 * (int)$cur['quantity'];
                try {
                    $this->ensureKitchenOrderAndUpsertItem($order_id, $cur['product_id'], $cur['item_name'], $delta, 'REMOVE');
                } catch (Exception $kex) { error_log('Pending delta (remove) failed: ' . $kex->getMessage()); }
                return Response::success(['pending_delta' => $delta], 'Đã ghi nhận xoá món, chờ xác nhận');
            } else {
                $stmt = $this->db->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
                $success = $stmt->execute([$id, $order_id]);
                if ($success && $stmt->rowCount() > 0) {
                    // Recalculate order total after deletion
                    $recalcStmt = $this->db->prepare("UPDATE orders SET total_amount = COALESCE((SELECT SUM(total_price) FROM order_items WHERE order_id = ?), 0), updated_at = NOW() WHERE id = ?");
                    $recalcStmt->execute([$order_id, $order_id]);
                    return Response::success(null, 'Xóa món thành công');
                }
            }
            
            return Response::notFound('Không tìm thấy món ăn trong đơn hàng');
            
        } catch (Exception $e) {
            error_log("Delete order item error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    private function createNotificationForOrderItem($orderId, $orderItem) {
        try {
            // Get order and table details
            $orderStmt = $this->db->prepare("
                SELECT o.*, t.table_number, t.table_name, s.full_name as staff_name
                FROM orders o
                JOIN tables t ON o.table_id = t.id
                JOIN users s ON o.user_id = s.id
                WHERE o.id = ?
            ");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                error_log("Order not found for notification: $orderId");
                return;
            }
            
            // Create notification
            $notificationQuery = "INSERT INTO notifications (type, title, message, table_id, order_id, user_id, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $notificationStmt = $this->db->prepare($notificationQuery);
            
            $notificationTitle = "Đơn hàng mới - Bàn " . $order['table_number'];
            $notificationMessage = "Nhân viên {$order['staff_name']} vừa thêm món '{$orderItem['item_name']}' x{$orderItem['quantity']} cho bàn {$order['table_number']}";
            
            $notificationStmt->execute([
                'new_order',
                $notificationTitle,
                $notificationMessage,
                $order['table_id'],
                $orderId,
                $order['user_id']
            ]);
            
            error_log("Notification created for order item: " . $orderItem['item_name']);
            
        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
        }
    }

    private function ensureKitchenOrderAndUpsertItem($orderId, $productId, $itemName, $quantity, $notes = null) {
        // 1) Ensure kitchen order exists for this order in pending_approval.
        // If latest is approved/printed/... create a new pending_approval snapshot for new items
        $koStmt = $this->db->prepare("SELECT id, status FROM kitchen_orders WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $koStmt->execute([$orderId]);
        $ko = $koStmt->fetch(PDO::FETCH_ASSOC);
        if (!$ko || ($ko && $ko['status'] !== 'pending_approval')) {
            // fetch minimal order info
            $oStmt = $this->db->prepare("SELECT o.id, o.table_id, o.order_number, t.name as table_name, u.full_name as staff_name FROM orders o JOIN tables t ON o.table_id = t.id JOIN users u ON o.user_id = u.id WHERE o.id = ?");
            $oStmt->execute([$orderId]);
            $o = $oStmt->fetch(PDO::FETCH_ASSOC);
            if (!$o) throw new Exception('Order not found for kitchen order');
            $insKO = $this->db->prepare("INSERT INTO kitchen_orders (order_id, table_id, table_name, order_number, staff_name, status) VALUES (?, ?, ?, ?, ?, 'pending_approval')");
            $insKO->execute([$orderId, $o['table_id'], $o['table_name'], $o['order_number'], $o['staff_name']]);
            $koId = $this->db->lastInsertId();
        } else {
            $koId = $ko['id'];
        }
        // 2) Upsert item: if exists in pending ticket, ADD to quantity (cumulative), otherwise insert
        $findItem = $this->db->prepare("SELECT id, quantity FROM kitchen_order_items WHERE kitchen_order_id = ? AND product_id = ? ORDER BY id DESC LIMIT 1");
        $findItem->execute([$koId, $productId]);
        $koi = $findItem->fetch(PDO::FETCH_ASSOC);
        if ($koi) {
            // Add to existing quantity in pending ticket
            $upd = $this->db->prepare("UPDATE kitchen_order_items SET item_name = ?, quantity = quantity + ?, special_instructions = ?, created_at = created_at WHERE id = ?");
            $upd->execute([$itemName, (int)$quantity, $notes ?: ($koi['special_instructions'] ?? null), $koi['id']]);
        } else {
            $insItem = $this->db->prepare("INSERT INTO kitchen_order_items (kitchen_order_id, product_id, item_name, quantity, special_instructions) VALUES (?, ?, ?, ?, ?)");
            $insItem->execute([$koId, $productId, $itemName, (int)$quantity, $notes]);
        }
    }
}
?>