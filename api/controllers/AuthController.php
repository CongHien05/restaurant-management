<?php

// Database already loaded in BaseController

class AuthController extends BaseController {
    
    public function login() {
        try {
            $input = $this->getJsonInput();
            
            // Validate input
            $errors = $this->validateRequired($input, ['username', 'password']);
            if (!empty($errors)) {
                return Response::validationError($errors);
            }
            
            $username = $input['username'];
            $password = $input['password'];
            
            // Find user in database (users preferred)
            $stmt = $this->db->prepare("SELECT id, username, password, full_name, role, phone, email, status, created_at FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Fallback to staff table if not found in users
            if (!$user) {
                $staffStmt = $this->db->prepare("SELECT id, username, password, full_name, role, is_active, created_at, email, phone FROM staff WHERE username = ? LIMIT 1");
                $staffStmt->execute([$username]);
                $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
                if ($staff && !empty($staff['is_active'])) {
                    $user = [
                        'id' => $staff['id'],
                        'username' => $staff['username'],
                        'password' => $staff['password'],
                        'full_name' => $staff['full_name'] ?? $staff['username'],
                        'role' => $staff['role'] ?? 'waiter',
                        'phone' => $staff['phone'] ?? null,
                        'email' => $staff['email'] ?? null,
                        'status' => 'active',
                        'created_at' => $staff['created_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
            
            if (!$user || !password_verify($password, $user['password'])) {
                return Response::error('Tên đăng nhập hoặc mật khẩu không đúng', 401);
            }
            
            // Generate JWT token
            $payload = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'iat' => time(),
                'exp' => time() + (24 * 60 * 60) // 24 hours
            ];
            
            $token = $this->generateJWT($payload);
            
            // Remove password from response
            unset($user['password']);
            
            // Convert status to boolean for JSON
            $user['is_active'] = ($user['status'] === 'active');
            
            // Update last login (users table doesn't have last_login field, so we'll skip this)
            // $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            // $updateStmt->execute([$user['id']]);
            
            return Response::success([
                'token' => $token,
                'user' => $user
            ], 'Đăng nhập thành công');
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return Response::error('Lỗi server', 500);
        }
    }
    
    public function logout() {
        // In a real app, you might want to blacklist the token
        return Response::success(null, 'Đăng xuất thành công');
    }
    
    public function me() {
        $user = $this->getAuthenticatedUser();
        
        if (!$user) {
            return Response::unauthorized();
        }
        
        // Get fresh user data
        $stmt = $this->db->prepare("SELECT id, username, full_name, role, phone, email, status, created_at FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$user['user_id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            return Response::unauthorized();
        }
        
        // Convert status to boolean for JSON
        $userData['is_active'] = ($userData['status'] === 'active');
        
        return Response::success($userData);
    }
    
    public function refresh() {
        $user = $this->getAuthenticatedUser();
        
        if (!$user) {
            return Response::unauthorized();
        }
        
        // Generate new token
        $payload = [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ];
        
        $token = $this->generateJWT($payload);
        
        return Response::success([
            'token' => $token
        ], 'Token đã được làm mới');
    }
    
    private function generateJWT($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $headerEncoded = $this->base64urlEncode($header);
        $payloadEncoded = $this->base64urlEncode($payload);
        
        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, 'restaurant_order_secret_key_2024', true);
        $signatureEncoded = $this->base64urlEncode($signature);
        
        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }
    
    private function base64urlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
?>