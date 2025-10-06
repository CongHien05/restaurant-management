<?php
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'API is working',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_NAME'],
    'request_uri' => $_SERVER['REQUEST_URI']
]);
?>



