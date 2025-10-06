<?php
/**
 * Base Controller cho tất cả controllers
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class BaseController {
    protected $db;
    protected $currentUser = null;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    protected function getJsonInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }
    
    protected function validateRequired($data, $fields) {
        $errors = [];
        
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = "Field '{$field}' is required";
            }
        }
        
        return $errors;
    }
    
    protected function requireAuth($roles = []) {
        $authMiddleware = new AuthMiddleware($this->db);
        $this->currentUser = $authMiddleware->authenticate();
        
        if (!empty($roles) && !in_array($this->currentUser['role'], $roles)) {
            Response::forbidden('Insufficient permissions');
        }
        
        return $this->currentUser;
    }
    
    protected function getQueryParams() {
        return $_GET;
    }
    
    protected function getQueryParam($key, $default = null) {
        return $_GET[$key] ?? $default;
    }
    
    protected function getPaginationInfo($page, $limit, $total) {
        $totalPages = ceil($total / $limit);
        return [
            'current_page' => (int) $page,
            'per_page' => (int) $limit,
            'total' => (int) $total,
            'total_pages' => (int) $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ];
    }
    
    protected function paginate($query, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $query .= " LIMIT {$limit} OFFSET {$offset}";
        
        return $query;
    }
    
    protected function getAuthenticatedUser() {
        $authMiddleware = new AuthMiddleware($this->db);
        try {
            return $authMiddleware->authenticate();
        } catch (Exception $e) {
            return null;
        }
    }
}
?>
