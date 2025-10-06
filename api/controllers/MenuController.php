<?php

// Database already loaded in BaseController

class MenuController extends BaseController {
    
    public function index() {
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $category_id = $_GET['category_id'] ?? null;
            $available_only = $_GET['available_only'] ?? false;
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($category_id) {
                $whereClause .= " AND m.category_id = ?";
                $params[] = $category_id;
            }
            
            if ($available_only) {
                $whereClause .= " AND m.status = 'active'";
            }
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM products m 
                INNER JOIN categories c ON m.category_id = c.id 
                $whereClause
            ");
            $countStmt->execute($params);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Calculate pagination
            $offset = ($page - 1) * $limit;
            $totalPages = ceil($totalCount / $limit);
            
            // Get menu items with pagination
            $stmt = $this->db->prepare("
                SELECT m.*, c.name as category_name
                FROM products m 
                INNER JOIN categories c ON m.category_id = c.id 
                $whereClause
                ORDER BY c.sort_order, m.sort_order, m.name
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create paginated response
            $pagination = [
                'current_page' => (int)$page,
                'per_page' => (int)$limit,
                'total' => (int)$totalCount,
                'total_pages' => (int)$totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ];
            
            $response = [
                'items' => $menuItems,
                'pagination' => $pagination
            ];
            
            return Response::success($response);
            
        } catch (Exception $e) {
            error_log("Get menu error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thực đơn', 500);
        }
    }
    
    public function show($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, c.name as category_name
                FROM products m 
                INNER JOIN categories c ON m.category_id = c.id 
                WHERE m.id = ?
            ");
            $stmt->execute([$id]);
            $menuItem = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$menuItem) {
                return Response::notFound('Không tìm thấy món ăn');
            }
            
            return Response::success($menuItem);
            
        } catch (Exception $e) {
            error_log("Get menu item error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thông tin món ăn', 500);
        }
    }
    
    public function getCategories() {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, COUNT(m.id) as item_count
                FROM categories c 
                LEFT JOIN products m ON c.id = m.category_id 
                GROUP BY c.id 
                ORDER BY c.sort_order, c.name
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success($categories);
            
        } catch (Exception $e) {
            error_log("Get categories error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy danh mục', 500);
        }
    }
    
    public function getByCategory($category_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, c.name as category_name
                FROM products m 
                INNER JOIN categories c ON m.category_id = c.id 
                WHERE m.category_id = ?
                ORDER BY m.sort_order, m.name
            ");
            $stmt->execute([$category_id]);
            $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success($menuItems);
            
        } catch (Exception $e) {
            error_log("Get menu by category error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy món ăn theo danh mục', 500);
        }
    }
    
    public function updateAvailability($id) {
        try {
            $input = $this->getJsonInput();
            
            if (!isset($input['status'])) {
                return Response::validationError(['status' => 'Trạng thái món ăn là bắt buộc']);
            }
            
            $stmt = $this->db->prepare("
                UPDATE products 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $success = $stmt->execute([$input['status'], $id]);
            
            if ($success && $stmt->rowCount() > 0) {
                return Response::success(null, 'Cập nhật trạng thái món ăn thành công');
            }
            
            return Response::notFound('Không tìm thấy món ăn');
            
        } catch (Exception $e) {
            error_log("Update menu availability error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function search() {
        try {
            $query = $_GET['q'] ?? '';
            $category_id = $_GET['category_id'] ?? null;
            $available_only = $_GET['available_only'] ?? true;
            
            if (empty($query)) {
                return Response::validationError(['q' => 'Từ khóa tìm kiếm là bắt buộc']);
            }
            
            $whereClause = "WHERE (m.name LIKE ? OR m.description LIKE ?)";
            $params = ["%$query%", "%$query%"];
            
            if ($category_id) {
                $whereClause .= " AND m.category_id = ?";
                $params[] = $category_id;
            }
            
            if ($available_only) {
                $whereClause .= " AND m.status = 'active'";
            }
            
            $stmt = $this->db->prepare("
                SELECT m.*, c.name as category_name
                FROM products m 
                INNER JOIN categories c ON m.category_id = c.id 
                $whereClause
                ORDER BY m.name
            ");
            $stmt->execute($params);
            $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success([
                'query' => $query,
                'items' => $menuItems,
                'total' => count($menuItems)
            ]);
            
        } catch (Exception $e) {
            error_log("Search menu error: " . $e->getMessage());
            return Response::error('Lỗi khi tìm kiếm món ăn', 500);
        }
    }

    public function store() {
        try {
            $input = $this->getJsonInput();

            $name = trim($input['name'] ?? '');
            $categoryId = $input['category_id'] ?? null;
            $price = $input['price'] ?? null;
            $description = $input['description'] ?? null;
            $image = $input['image'] ?? null;
            $status = $input['status'] ?? 'active';
            $prepTime = $input['preparation_time'] ?? null;

            $errors = [];
            if ($name === '') { $errors['name'] = 'Tên món là bắt buộc'; }
            if (!$categoryId) { $errors['category_id'] = 'Danh mục là bắt buộc'; }
            if ($price === null || !is_numeric($price) || $price < 0) { $errors['price'] = 'Giá không hợp lệ'; }
            if (!empty($errors)) { return Response::validationError($errors); }

            $stmt = $this->db->prepare("INSERT INTO products (name, description, category_id, price, image, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())");
            $ok = $stmt->execute([
                $name,
                $description,
                (int)$categoryId,
                (float)$price,
                $image,
                $status
            ]);

            if ($ok) {
                $id = $this->db->lastInsertId();
                return Response::success(['id' => (int)$id], 'Thêm món ăn thành công');
            }

            return Response::error('Không thể thêm món ăn', 500);
        } catch (Exception $e) {
            error_log("Create menu item error: " . $e->getMessage());
            return Response::error('Lỗi khi thêm món ăn', 500);
        }
    }

    public function update($id) {
        try {
            $input = $this->getJsonInput();

            // Fetch existing to ensure it exists
            $existsStmt = $this->db->prepare("SELECT id FROM products WHERE id = ?");
            $existsStmt->execute([$id]);
            if (!$exists = $existsStmt->fetch(PDO::FETCH_ASSOC)) {
                return Response::notFound('Không tìm thấy món ăn');
            }

            $name = isset($input['name']) ? trim($input['name']) : null;
            $categoryId = $input['category_id'] ?? null;
            $price = $input['price'] ?? null;
            $description = $input['description'] ?? null;
            $image = $input['image'] ?? null;
            $status = $input['status'] ?? null;
            $prepTime = array_key_exists('preparation_time', $input) ? $input['preparation_time'] : null;

            $errors = [];
            if ($name !== null && $name === '') { $errors['name'] = 'Tên món không hợp lệ'; }
            if ($categoryId !== null && !$categoryId) { $errors['category_id'] = 'Danh mục không hợp lệ'; }
            if ($price !== null && (!is_numeric($price) || $price < 0)) { $errors['price'] = 'Giá không hợp lệ'; }
            if (!empty($errors)) { return Response::validationError($errors); }

            $stmt = $this->db->prepare("UPDATE products SET name = COALESCE(?, name), description = COALESCE(?, description), category_id = COALESCE(?, category_id), price = COALESCE(?, price), image = COALESCE(?, image), status = COALESCE(?, status), updated_at = NOW() WHERE id = ?");
            $ok = $stmt->execute([
                $name,
                $description,
                $categoryId !== null ? (int)$categoryId : null,
                $price !== null ? (float)$price : null,
                $image,
                $status,
                $id
            ]);

            if ($ok && $stmt->rowCount() >= 0) {
                return Response::success(null, 'Cập nhật món ăn thành công');
            }

            return Response::error('Không thể cập nhật món ăn', 500);
        } catch (Exception $e) {
            error_log("Update menu item error: " . $e->getMessage());
            return Response::error('Lỗi khi cập nhật món ăn', 500);
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
            $ok = $stmt->execute([$id]);
            if ($ok && $stmt->rowCount() > 0) {
                return Response::success(null, 'Xóa món ăn thành công');
            }
            return Response::notFound('Không tìm thấy món ăn');
        } catch (Exception $e) {
            error_log("Delete menu item error: " . $e->getMessage());
            return Response::error('Lỗi khi xóa món ăn', 500);
        }
    }
}
?>