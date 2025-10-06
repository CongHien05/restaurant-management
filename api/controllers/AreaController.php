<?php

// Database already loaded in BaseController

class AreaController extends BaseController {
    
    public function index() {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       COUNT(t.id) as table_count,
                       COUNT(CASE WHEN t.status = 'available' THEN 1 END) as available_tables
                FROM areas a 
                LEFT JOIN tables t ON a.id = t.area_id 
                GROUP BY a.id 
                ORDER BY a.name
            ");
            $stmt->execute();
            $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return Response::success($areas);
            
        } catch (Exception $e) {
            error_log("Get areas error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy danh sách khu vực', 500);
        }
    }
    
    public function show($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       COUNT(t.id) as table_count,
                       COUNT(CASE WHEN t.status = 'available' THEN 1 END) as available_tables
                FROM areas a 
                LEFT JOIN tables t ON a.id = t.area_id 
                WHERE a.id = ?
                GROUP BY a.id
            ");
            $stmt->execute([$id]);
            $area = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$area) {
                return Response::notFound('Không tìm thấy khu vực');
            }
            
            return Response::success($area);
            
        } catch (Exception $e) {
            error_log("Get area error: " . $e->getMessage());
            return Response::error('Lỗi khi lấy thông tin khu vực', 500);
        }
    }
    
    public function store() {
        try {
            $input = $this->getJsonInput();
            
            $errors = $this->validateRequired($input, ['name']);
            if (!empty($errors)) {
                return Response::validationError($errors);
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO areas (name, description) 
                VALUES (?, ?)
            ");
            
            $success = $stmt->execute([
                $input['name'],
                $input['description'] ?? null
            ]);
            
            if ($success) {
                $areaId = $this->db->lastInsertId();
                return Response::success(['id' => $areaId], 'Tạo khu vực thành công', 201);
            }
            
            return Response::error('Lỗi khi tạo khu vực', 500);
            
        } catch (Exception $e) {
            error_log("Create area error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function update($id) {
        try {
            $input = $this->getJsonInput();
            
            $updateFields = [];
            $params = [];
            
            if (isset($input['name'])) {
                $updateFields[] = "name = ?";
                $params[] = $input['name'];
            }
            
            if (isset($input['description'])) {
                $updateFields[] = "description = ?";
                $params[] = $input['description'];
            }
            
            if (empty($updateFields)) {
                return Response::error('Không có dữ liệu để cập nhật', 400);
            }
            
            $params[] = $id;
            $sql = "UPDATE areas SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($params);
            
            if ($success && $stmt->rowCount() > 0) {
                return Response::success(null, 'Cập nhật khu vực thành công');
            }
            
            return Response::notFound('Không tìm thấy khu vực');
            
        } catch (Exception $e) {
            error_log("Update area error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function delete($id) {
        try {
            // Check if area has any tables
            $checkStmt = $this->db->prepare("SELECT COUNT(*) as count FROM tables WHERE area_id = ?");
            $checkStmt->execute([$id]);
            $tableCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($tableCount > 0) {
                return Response::error('Không thể xóa khu vực đã có bàn', 400);
            }
            
            $stmt = $this->db->prepare("DELETE FROM areas WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success && $stmt->rowCount() > 0) {
                return Response::success(null, 'Xóa khu vực thành công');
            }
            
            return Response::notFound('Không tìm thấy khu vực');
            
        } catch (Exception $e) {
            error_log("Delete area error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
}
?>