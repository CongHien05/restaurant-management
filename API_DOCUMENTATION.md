# 📚 Restaurant Management System - API Documentation

## Base URL
```
http://localhost/pandabackend/api
```

---

## 🔐 Authentication

### Login
```http
POST /auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "admin123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "id": 1,
      "username": "admin",
      "role": "admin",
      "full_name": "Administrator"
    }
  }
}
```

### Get Current User
```http
GET /auth/me
Authorization: Bearer {token}
```

### Logout
```http
POST /auth/logout
Authorization: Bearer {token}
```

---

## 🏢 Areas

### Get All Areas
```http
GET /areas
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Khu vực Tầng trệt",
      "description": "Khu vực chính",
      "is_active": 1
    }
  ]
}
```

### Create Area (Admin/Manager only)
```http
POST /areas
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Khu vực VIP",
  "description": "Phòng riêng",
  "is_active": true
}
```

---

## 🪑 Tables

### Get All Tables
```http
GET /tables
Authorization: Bearer {token}
```

**Query params:**
- `status`: available, occupied, reserved, cleaning
- `area_id`: Filter by area

**Response:**
```json
{
  "success": true,
  "data": {
    "tables": [
      {
        "id": 1,
        "name": "A1",
        "area_id": 1,
        "area_name": "Tầng trệt",
        "capacity": 4,
        "status": "available",
        "current_order_id": null,
        "order_status": null
      }
    ]
  }
}
```

### Get Table Details (with order items)
```http
GET /tables/{id}/details
Authorization: Bearer {token}
Roles: admin, manager, waiter, kitchen
```

**Response:**
```json
{
  "success": true,
  "data": {
    "table": {...},
    "order": {
      "id": 10,
      "status": "served",
      "total_amount": 150000
    },
    "order_items": [
      {
        "id": 1,
        "item_name": "Phở bò",
        "quantity": 2,
        "unit_price": 55000
      }
    ],
    "pending_items": [
      {
        "id": 5,
        "item_name": "Chả giò",
        "quantity": 1
      }
    ]
  }
}
```

### Get Current Order for Table
```http
GET /tables/{id}/current-order
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "order": {
      "id": 10,
      "table_id": 1,
      "status": "served",
      "items": [...],
      "pending_items": [...]
    }
  }
}
```

### Update Table Status
```http
PUT /tables/{id}/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "available"
}
```

---

## 🍽️ Menu

### Get All Menu Items
```http
GET /menu
Authorization: Bearer {token}
```

**Query params:**
- `page`: 1
- `limit`: 20
- `category_id`: Filter by category
- `available_only`: true/false

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "name": "Phở bò",
        "price": "55000.00",
        "category_name": "Món chính",
        "status": "active"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total": 21,
      "total_pages": 2
    }
  }
}
```

### Create Menu Item (Admin/Manager)
```http
POST /menu
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Bún chả",
  "description": "Bún chả Hà Nội",
  "price": 45000,
  "category_id": 2,
  "status": "active"
}
```

---

## 📋 Orders

### Create Order
```http
POST /orders
Authorization: Bearer {token}
Content-Type: application/json

{
  "table_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "notes": "Không hành"
    }
  ]
}
```

### Add Item to Order
```http
POST /orders/{order_id}/items
Authorization: Bearer {token}
Content-Type: application/json

{
  "product_id": 5,
  "quantity": 1,
  "notes": "Ít cay"
}
```

**Note:** Items go to `kitchen_order_items` (pending approval), not directly to `order_items`.

### Update Order Item
```http
PUT /orders/{order_id}/items/{item_id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "quantity": 3
}
```

**Note:** If order is `served`, creates a delta in `kitchen_order_items` for approval.

### Delete Order Item
```http
DELETE /orders/{order_id}/items/{item_id}
Authorization: Bearer {token}
```

### Update Order Status
```http
PUT /orders/{order_id}/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "completed"
}
```

**Statuses:**
- `pending` → Vừa tạo
- `confirmed` → Admin đã xác nhận
- `preparing` → Bếp đang làm
- `ready` → Sẵn sàng phục vụ
- `served` → Đã phục vụ
- `completed` → Đã thanh toán
- `cancelled` → Đã hủy

---

## 🍴 Kitchen Orders (Approval System)

### Get Pending Approvals
```http
GET /admin/kitchen/orders?status=pending_approval
Authorization: Bearer {token}
Roles: admin, manager
```

**Response:**
```json
{
  "success": true,
  "data": {
    "kitchen_orders": [
      {
        "id": 5,
        "order_id": 10,
        "table_id": 1,
        "table_name": "A1",
        "status": "pending_approval",
        "created_at": "2025-10-06 20:30:00",
        "items": [
          {
            "id": 15,
            "product_id": 2,
            "item_name": "Chả giò",
            "quantity": 2
          }
        ]
      }
    ]
  }
}
```

### Approve Kitchen Order
```http
PUT /admin/kitchen/orders/{id}/approve
Authorization: Bearer {token}
Roles: admin, manager
Content-Type: application/json

{
  "approved_by": 1
}
```

**Process:**
1. Get all `kitchen_order_items` for this `kitchen_order`
2. For each item:
   - If `special_instructions = 'REMOVE'` → Delete from `order_items`
   - If quantity is negative → Decrease `order_items.quantity`
   - If quantity is positive → Upsert into `order_items` (add or increase)
3. Recalculate `orders.total_amount`
4. Set `orders.status = 'served'`
5. Set `tables.status = 'occupied'`
6. Set `kitchen_orders.status = 'approved'`

### Update Kitchen Order Status
```http
PUT /admin/kitchen/orders/{id}/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "printed"
}
```

**Statuses:**
- `pending_approval` → Chờ admin duyệt
- `approved` → Đã duyệt
- `printed` → Đã in phiếu bếp
- `served` → Đã phục vụ
- `cancelled` → Đã hủy

---

## 💰 Payments

### Process Table Payment (Admin)
```http
POST /admin/tables/{table_id}/payment
Authorization: Bearer {token}
Roles: admin, manager
Content-Type: application/json

{
  "payment_method": "cash",
  "received_amount": 200000
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "payment_details": {
      "order_number": "ORD202510060005",
      "total_amount": 150000,
      "received_amount": 200000,
      "change_amount": 50000
    }
  }
}
```

**Process:**
1. Verify `order.status` is `served`
2. Update `order.status = 'completed'`
3. Delete `order_items`
4. Cancel pending `kitchen_orders`
5. Set `table.status = 'available'`
6. Create `payments` record
7. Create notification for staff

---

## 📊 Revenue & Reports

### Get Revenue Report
```http
GET /revenue?from={date}&to={date}&type={type}&table_id={id}
Authorization: Bearer {token}
Roles: admin, manager
```

**Query params:**
- `from`: 2025-10-01
- `to`: 2025-10-31
- `type`: daily, monthly, table, menu
- `table_id`: (optional) Filter by table

**Response:**
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_revenue": 8500000,
      "total_orders": 120,
      "avg_order_value": 70833,
      "top_item": "Phở bò"
    },
    "chartData": [
      {
        "label": "2025-10-01",
        "value": 350000,
        "orders": 5
      }
    ],
    "data": [...]
  }
}
```

**Type examples:**

**daily:** Doanh thu theo ngày
```json
{
  "label": "2025-10-06",
  "value": 355000,
  "orders": 5
}
```

**monthly:** Doanh thu theo tháng
```json
{
  "label": "2025-10",
  "value": 8500000,
  "orders": 120
}
```

**table:** Doanh thu theo bàn
```json
{
  "label": "Bàn A1",
  "table_id": 1,
  "value": 1200000,
  "orders": 15
}
```

**menu:** Doanh thu theo món
```json
{
  "label": "Phở bò",
  "value": 2475000,
  "quantity": 45
}
```

### Close Revenue Day
```http
POST /admin/revenue/close-day
Authorization: Bearer {token}
Roles: admin
Content-Type: application/json

{
  "date": "2025-10-06",
  "notes": "Chốt cuối ngày"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "date": "2025-10-06",
    "total_orders": 5,
    "total_revenue": 355000
  }
}
```

### Get Revenue Closures History
```http
GET /admin/revenue/closures?from={date}&to={date}
Authorization: Bearer {token}
Roles: admin, manager
```

**Response:**
```json
{
  "success": true,
  "data": {
    "closures": [
      {
        "id": 1,
        "date": "2025-10-06",
        "total_orders": 5,
        "total_revenue": 355000,
        "closed_by": 1,
        "closed_at": "2025-10-06 23:30:00"
      }
    ]
  }
}
```

---

## 👥 Staff Management (Admin)

### Get All Staff
```http
GET /admin/users
Authorization: Bearer {token}
Roles: admin, manager
```

### Create Staff
```http
POST /admin/users
Authorization: Bearer {token}
Roles: admin
Content-Type: application/json

{
  "username": "waiter1",
  "password": "123456",
  "full_name": "Nguyễn Văn A",
  "email": "waiter1@example.com",
  "role": "waiter"
}
```

**Roles:**
- `admin` - Full access
- `manager` - Can approve, view reports
- `waiter` - Can create orders
- `kitchen` - Can view kitchen orders

### Update Staff
```http
PUT /admin/users/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "full_name": "Nguyễn Văn B",
  "role": "manager"
}
```

---

## ⚠️ Error Responses

### 400 Bad Request
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "username": "Username is required",
    "password": "Password must be at least 6 characters"
  }
}
```

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "You do not have permission to access this resource"
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Resource not found"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Internal server error"
}
```

---

## 🔑 Rate Limiting

Currently no rate limiting implemented.  
**Recommendation for production:** Implement rate limiting middleware.

---

## 📝 Notes

1. All timestamps in `Y-m-d H:i:s` format (MySQL DATETIME)
2. Prices stored as DECIMAL(10,2)
3. JWT token expires after 24 hours
4. All API responses include `timestamp` field

---

**Version:** 1.0  
**Last Updated:** October 2025

