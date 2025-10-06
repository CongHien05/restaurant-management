<?php
/**
 * Restaurant Staff API - Main Entry Point
 * Author: Restaurant Management System
 * Version: 1.0
 */

// Include CORS configuration
require_once __DIR__ . '/../config/cors.php';

// Include core files
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/BaseController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// Include all controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/AreaController.php';
require_once __DIR__ . '/controllers/TableController.php';
require_once __DIR__ . '/controllers/MenuController.php';
require_once __DIR__ . '/controllers/OrderController.php';
require_once __DIR__ . '/controllers/OrderItemController.php';
require_once __DIR__ . '/controllers/PaymentController.php';
require_once __DIR__ . '/controllers/KitchenController.php';
require_once __DIR__ . '/controllers/AdminController.php';

// Initialize Router
$router = new Router();

// ============ AUTHENTICATION ROUTES ============
$router->post('/auth/login', 'AuthController@login');
$router->get('/auth/login', function() {
    Response::success([
        'message' => 'Login endpoint is working',
        'method' => 'Use POST with username/password in JSON body',
        'example' => [
            'username' => 'admin',
            'password' => 'admin123'
        ]
    ], 'Login endpoint ready');
});
$router->post('/auth/logout', 'AuthController@logout', 'auth');
$router->get('/auth/me', 'AuthController@me', 'auth');
$router->post('/auth/refresh', 'AuthController@refresh', 'auth');

// ============ AREA ROUTES ============
$router->get('/areas', 'AreaController@index', 'auth');
$router->get('/areas/{id}', 'AreaController@show', 'auth');
$router->post('/areas', 'AreaController@store', 'auth:admin,manager');
$router->put('/areas/{id}', 'AreaController@update', 'auth:admin,manager');
$router->delete('/areas/{id}', 'AreaController@delete', 'auth:admin,manager');

// ============ TABLE ROUTES ============
$router->get('/tables', 'TableController@index', 'auth');
$router->get('/tables/{id}', 'TableController@show', 'auth');
$router->get('/tables/{id}/current-order', 'TableController@getCurrentOrder', 'auth');
$router->get('/areas/{area_id}/tables', 'TableController@getByArea', 'auth');
$router->post('/tables', 'TableController@store', 'auth:admin,manager');
$router->put('/tables/{id}', 'TableController@update', 'auth:admin,manager');
$router->put('/tables/{id}/status', 'TableController@updateStatus', 'auth');
$router->put('/tables/{id}/order-info', 'TableController@updateOrderInfo', 'auth');
$router->delete('/tables/{id}', 'TableController@delete', 'auth:admin,manager');

// ============ MENU ROUTES ============
$router->get('/menu', 'MenuController@index', 'auth');
$router->get('/menu/search', 'MenuController@search', 'auth');
$router->get('/menu/{id}', 'MenuController@show', 'auth');
$router->get('/categories', 'MenuController@getCategories', 'auth');
$router->get('/categories/{category_id}/items', 'MenuController@getByCategory', 'auth');
$router->post('/menu', 'MenuController@store', 'auth:admin,manager');
$router->put('/menu/{id}', 'MenuController@update', 'auth:admin,manager');
$router->put('/menu/{id}/availability', 'MenuController@updateAvailability', 'auth:admin,manager,kitchen');
$router->delete('/menu/{id}', 'MenuController@delete', 'auth:admin,manager');

// ============ ORDER ROUTES ============
$router->get('/orders', 'OrderController@index', 'auth');
$router->get('/orders/{id}', 'OrderController@show', 'auth');
$router->get('/tables/{table_id}/orders', 'OrderController@getByTable', 'auth');
$router->post('/orders', 'OrderController@store', 'auth');
$router->put('/orders/{id}', 'OrderController@update', 'auth');
$router->put('/orders/{id}/status', 'OrderController@updateStatus', 'auth');
$router->put('/orders/{id}/submit', 'OrderController@submit', 'auth');
$router->delete('/orders/{id}', 'OrderController@delete', 'auth');

// ============ ORDER ITEM ROUTES ============
$router->get('/orders/{order_id}/items', 'OrderItemController@getByOrder', 'auth');
$router->post('/orders/{order_id}/items', 'OrderItemController@store', 'auth');
$router->put('/orders/{order_id}/items/{id}', 'OrderItemController@update', 'auth');
$router->delete('/orders/{order_id}/items/{id}', 'OrderItemController@delete', 'auth');

// ============ PAYMENT ROUTES ============
$router->get('/payments', 'PaymentController@index', 'auth:admin,manager');
$router->get('/payments/{id}', 'PaymentController@show', 'auth');
$router->post('/payments', 'PaymentController@store', 'auth');
$router->get('/orders/{order_id}/payment', 'PaymentController@getByOrder', 'auth');

// ============ KITCHEN ROUTES ============
$router->get('/kitchen/orders', 'KitchenController@getPendingOrders', 'auth:kitchen,admin,manager');
$router->put('/kitchen/orders/{id}/confirm', 'KitchenController@confirmOrder', 'auth:kitchen,admin,manager');
$router->put('/kitchen/orders/{id}/ready', 'KitchenController@markOrderReady', 'auth:kitchen,admin,manager');
$router->get('/kitchen/stats', 'KitchenController@getStats', 'auth:kitchen,admin,manager');

// ============ ADMIN ROUTES ============
$router->get('/admin/staff', 'AdminController@getStaff', 'auth:admin,manager');
$router->post('/admin/staff', 'AdminController@createStaff', 'auth:admin,manager');
$router->put('/admin/staff/{id}', 'AdminController@updateStaff', 'auth:admin,manager');
$router->delete('/admin/staff/{id}', 'AdminController@deleteStaff', 'auth:admin');

// ============ USER MANAGEMENT ROUTES ============
$router->get('/users', 'AdminController@getStaff', 'auth:admin,manager');
$router->post('/users', 'AdminController@createStaff', 'auth:admin,manager');
$router->put('/users/{id}', 'AdminController@updateStaff', 'auth:admin,manager');
$router->delete('/users/{id}', 'AdminController@deleteStaff', 'auth:admin');
$router->get('/admin/reports/daily', 'AdminController@getDailyReport', 'auth:admin,manager');
$router->get('/admin/reports/sales', 'AdminController@getSalesReport', 'auth:admin,manager');
$router->get('/admin/stats', 'AdminController@getStats', 'auth:admin,manager');
$router->get('/revenue', 'AdminController@getRevenueReport', 'auth:admin,manager');
// Revenue closure routes
$router->post('/admin/revenue/close-day', 'AdminController@closeRevenueDay', 'auth:admin');
$router->get('/admin/revenue/closures', 'AdminController@getRevenueClosures', 'auth:admin,manager');

// ============ ADMIN TABLE MANAGEMENT ROUTES ============
$router->get('/admin/tables/{id}/details', 'AdminController@getTableDetails', 'auth:admin,manager');
// Staff-facing alias to view confirmed and pending items on a table
$router->get('/tables/{id}/details', 'AdminController@getTableDetails', 'auth:admin,manager,waiter,kitchen');
$router->post('/admin/tables/{id}/add-item', 'AdminController@addTableItem', 'auth:admin,manager');
$router->post('/admin/tables/{id}/process-payment', 'AdminController@processTablePayment', 'auth:admin,manager');

// ============ ADMIN NOTIFICATION ROUTES ============
$router->get('/admin/notifications', 'AdminController@getNotifications', 'auth:admin,manager');
$router->put('/admin/notifications/{id}/read', 'AdminController@markNotificationAsRead', 'auth:admin,manager');
$router->put('/admin/notifications/mark-all-read', 'AdminController@markAllNotificationsAsRead', 'auth:admin,manager');

// ============ ADMIN KITCHEN ROUTES ============
$router->get('/admin/kitchen/orders', 'AdminController@getKitchenOrders', 'auth:admin,manager');
$router->get('/admin/kitchen/orders/{id}', 'AdminController@getKitchenOrder', 'auth:admin,manager');
$router->put('/admin/kitchen/orders/{id}/approve', 'AdminController@approveKitchenOrder', 'auth:admin,manager');
$router->put('/admin/kitchen/orders/{id}/status', 'AdminController@updateKitchenOrderStatus', 'auth:admin,manager');
$router->get('/admin/kitchen/pending-approval', 'AdminController@getPendingApprovalOrders', 'auth:admin,manager');
$router->put('/admin/kitchen/orders/{id}/status', 'AdminController@updateKitchenOrderStatus', 'auth:admin,manager');
$router->put('/admin/kitchen/orders/{id}/approve', 'AdminController@approveKitchenOrder', 'auth:admin,manager');
$router->post('/admin/tables/{table_id}/orders/{order_id}/add-item', 'AdminController@addItemToExistingOrder', 'auth:admin,manager');

// ============ HEALTH CHECK ============
$router->get('/health', function() {
    Response::success([
        'status' => 'OK',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0.0'
    ], 'API is running');
});

// ============ ROOT ROUTE ============
$router->get('/', function() {
    Response::success([
        'success' => true,
        'message' => 'Restaurant Staff API',
        'version' => '1.0.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'request_uri' => $_SERVER['REQUEST_URI'],
        'script_name' => $_SERVER['SCRIPT_NAME'],
        'endpoints' => [
            'health' => '/health',
            'login' => '/auth/login',
            'areas' => '/areas',
            'tables' => '/tables',
            'menu' => '/menu',
            'orders' => '/orders'
        ],
        'documentation' => 'See API_DOCUMENTATION.md for full endpoint list'
    ], 'API is running');
});

// Dispatch the request
$router->dispatch();
?>