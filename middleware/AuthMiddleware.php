<?php
/**
 * Authentication Middleware
 * JWT Token validation for API requests
 */

class AuthMiddleware {
    private $db;
    private $secretKey = 'restaurant_order_secret_key_2024'; // Production: use env variable
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function authenticate() {
        $headers = $this->getAuthHeaders();
        
        if (!$headers) {
            Response::unauthorized('Authorization header missing');
        }
        
        $token = $this->extractToken($headers);
        
        if (!$token) {
            Response::unauthorized('Token missing');
        }
        
        $decoded = $this->validateToken($token);
        
        if (!$decoded) {
            Response::unauthorized('Invalid token');
        }
        
        // Get user info from database
        $user = $this->getUserById($decoded->user_id);
        
        if (!$user || !$user['is_active']) {
            Response::unauthorized('User not found or inactive');
        }
        
        return $user;
    }
    
    private function getAuthHeaders() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } else if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) { // common with Apache + PHP-FPM/CGI
            $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } else if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Normalize keys to lower-case for case-insensitive lookup
            $normalized = [];
            foreach ($requestHeaders as $k => $v) {
                $normalized[strtolower($k)] = $v;
            }
            if (isset($normalized['authorization'])) {
                $headers = trim($normalized['authorization']);
            }
        }
        
        return $headers;
    }
    
    private function extractToken($headers) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function validateToken($token) {
        try {
            $decoded = $this->decodeJWT($token, $this->secretKey);
            
            // Check if token is expired
            if ($decoded->exp < time()) {
                return false;
            }
            
            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function getUserById($userId) {
        $stmt = $this->db->prepare("
            SELECT id, username, full_name, phone, email, role, status
            FROM users 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        // Convert status to is_active for compatibility
        if ($user) {
            $user['is_active'] = ($user['status'] === 'active');
        }
        
        return $user;
    }
    
    public function generateToken($user) {
        $payload = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ];
        
        return $this->encodeJWT($payload, $this->secretKey);
    }
    
    // Simple JWT implementation (Production: use firebase/jwt library)
    private function encodeJWT($payload, $key) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $key, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    private function decodeJWT($jwt, $key) {
        $parts = explode('.', $jwt);
        
        if (count($parts) != 3) {
            throw new Exception('Invalid token format');
        }
        
        list($base64Header, $base64Payload, $base64Signature) = $parts;
        
        $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Header)), true);
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);
        
        // Verify signature
        $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Signature));
        $expectedSignature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $key, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Invalid signature');
        }
        
        return (object) $payload;
    }
}
?>
