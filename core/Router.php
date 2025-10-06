<?php
/**
 * Simple Router for RESTful API
 */

// Include necessary files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class Router {
    private $routes = [];
    
    public function get($path, $handler, $middleware = null) {
        $this->addRoute('GET', $path, $handler, $middleware);
    }
    
    public function post($path, $handler, $middleware = null) {
        $this->addRoute('POST', $path, $handler, $middleware);
    }
    
    public function put($path, $handler, $middleware = null) {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }
    
    public function delete($path, $handler, $middleware = null) {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }
    
    private function addRoute($method, $path, $handler, $middleware = null) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }
    
    public function dispatch() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = $_SERVER['REQUEST_URI'];
        
        // Remove query string and get path
        $requestUri = strtok($requestUri, '?');
        
        // Debug logging (disabled for performance)
        // error_log("Router DEBUG - Original URI: " . $requestUri);
        // error_log("Router DEBUG - Method: " . $requestMethod);
        
        // Remove base path for XAMPP htdocs deployment
        // Handle both /pandabackend/api/... and direct /api/... patterns
        if (preg_match('#^/pandabackend/api(.*)$#', $requestUri, $matches)) {
            $requestUri = $matches[1]; // Get everything after /pandabackend/api
        } elseif (preg_match('#^/api(.*)$#', $requestUri, $matches)) {
            $requestUri = $matches[1]; // Get everything after /api
        }
        
        if (empty($requestUri)) {
            $requestUri = '/';
        }
        
        // error_log("Router DEBUG - Final URI: " . $requestUri);
        // error_log("Router DEBUG - Total routes: " . count($this->routes));
        
        // Handle preflight OPTIONS request
        if ($requestMethod === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        foreach ($this->routes as $route) {
            // error_log("Router DEBUG - Checking route: {$route['method']} {$route['path']}");
            
            if ($route['method'] !== $requestMethod) {
                // error_log("Router DEBUG - Method mismatch");
                continue;
            }
            
            $pattern = $this->convertPathToRegex($route['path']);
            // error_log("Router DEBUG - Pattern: " . $pattern);
            
            if (preg_match($pattern, $requestUri, $matches)) {
                // error_log("Router DEBUG - MATCH FOUND!");
                // Extract parameters
                $params = array_slice($matches, 1);
                
                // Check middleware if exists
                if ($route['middleware']) {
                    $authResult = $this->checkAuth($route['middleware']);
                    if (!$authResult) {
                        return;
                    }
                }
                
                $this->handleRequest($route['handler'], $params);
                return;
            } else {
                // error_log("Router DEBUG - No match for pattern");
            }
        }
        
        // Route not found
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Route not found',
            'path' => $requestUri,
            'method' => $requestMethod
        ]);
    }
    
    // Keep old method for backward compatibility
    public function route() {
        $this->dispatch();
    }
    
    private function convertPathToRegex($path) {
        // Convert {id} to regex capture groups
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    private function handleRequest($handler, $params) {
        try {
            if (is_callable($handler)) {
                // Anonymous function
                call_user_func_array($handler, $params);
                return;
            }
            
            list($controllerName, $method) = explode('@', $handler);
            
            if (!class_exists($controllerName)) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => "Controller not found: {$controllerName}"
                ]);
                return;
            }
            
            $controller = new $controllerName();
            
            if (!method_exists($controller, $method)) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => "Method not found: {$method}"
                ]);
                return;
            }
            
            // Call controller method with parameters
            call_user_func_array([$controller, $method], $params);
            
        } catch (Exception $e) {
            error_log("Router error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function checkAuth($middleware) {
        if (!$middleware) {
            return true;
        }
        
        if (strpos($middleware, 'auth') === 0) {
            // Use AuthMiddleware for proper JWT validation
            try {
                $db = (new Database())->getConnection();
                $authMiddleware = new AuthMiddleware($db);
                $user = $authMiddleware->authenticate();
                
                // Store user in global scope for controllers to use
                global $currentUser;
                $currentUser = $user;
                
                // For role-based auth like 'auth:admin,manager'
                if (strpos($middleware, ':') !== false) {
                    $parts = explode(':', $middleware);
                    $allowedRoles = explode(',', $parts[1]);
                    
                    if (!in_array($user['role'], $allowedRoles)) {
                        http_response_code(403);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Forbidden - Insufficient permissions'
                        ]);
                        return false;
                    }
                }
                
                return true;
            } catch (Exception $e) {
                error_log("Auth error: " . $e->getMessage());
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized - ' . $e->getMessage()
                ]);
                return false;
            }
        }
        
        return true;
    }
}
?>
