<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_user'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'error' => 'Unauthorized']);
	exit;
}

// Prefer the session token already issued at login
$token = $_SESSION['admin_token'] ?? null;

if (!$token) {
	// No token available in session
	http_response_code(401);
	echo json_encode(['success' => false, 'error' => 'Missing token']);
	exit;
}

echo json_encode([
	'success' => true,
	'token' => $token,
	'user' => is_array($_SESSION['admin_user']) ? $_SESSION['admin_user'] : ['username' => (string)$_SESSION['admin_user']]
]);
?>
