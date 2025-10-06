<?php
/**
 * Simple API Test - Direct test without full router
 */

// Test health endpoint
if ($_GET['test'] === 'health') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'API is working!',
        'timestamp' => date('Y-m-d H:i:s'),
        'server' => 'XAMPP'
    ]);
    exit;
}

// Test login endpoint
if ($_GET['test'] === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input['username'] === 'admin' && $input['password'] === '123456') {
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'token' => 'fake-jwt-token-for-testing',
            'user' => [
                'id' => 1,
                'username' => 'admin',
                'full_name' => 'Administrator',
                'role' => 'admin'
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials'
        ]);
    }
    exit;
}

// Default response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Simple API Test',
    'available_tests' => [
        'health' => '?test=health',
        'login' => '?test=login (POST with username/password)'
    ]
]);
?>

