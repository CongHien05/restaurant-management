# 🍽️ Restaurant Management System - Setup Guide

## Tổng quan
Hệ thống quản lý nhà hàng bao gồm:
- **Admin Panel** (Web): Quản lý bàn, món ăn, nhân viên, doanh thu
- **Staff App** (Android): Nhân viên order món cho khách

---

## 📋 Yêu cầu hệ thống

### Backend (PHP API)
- PHP 7.4 hoặc cao hơn
- MySQL 5.7+
- Apache/Nginx với mod_rewrite
- Extensions: PDO, JSON, OpenSSL

### Frontend (Admin Web)
- Browser hiện đại (Chrome, Firefox, Edge)
- JavaScript enabled

### Mobile App (Android)
- Android 7.0 (API 24) trở lên
- Kết nối mạng (WiFi/4G)

---

## 🚀 Cài đặt Backend

### Bước 1: Import Database

1. Tạo database mới:
```sql
CREATE DATABASE restaurant_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import schema:
```bash
mysql -u root -p restaurant_db < restaurant_db\ \(3\).sql
```

### Bước 2: Cấu hình Database

Chỉnh file `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'restaurant_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Bước 3: Cấu hình CORS

File `config/cors.php` đã được cấu hình sẵn cho localhost và LAN.
Nếu deploy production, chỉnh `Access-Control-Allow-Origin`.

### Bước 4: Test API

Mở trình duyệt, truy cập:
```
http://localhost/pandabackend/api/
```

Response mong đợi:
```json
{
  "success": true,
  "message": "Restaurant Staff API is running"
}
```

---

## 🖥️ Sử dụng Admin Panel

### Đăng nhập

URL: `http://localhost/pandabackend/admin/login.php`

**Tài khoản mặc định:**
- Username: `admin`
- Password: `admin123`

### Các chức năng chính

1. **Dashboard**
   - Xem danh sách bàn (filter theo khu vực, trạng thái)
   - Thanh toán cho bàn
   - Xem KPI (tổng bàn, đang phục vụ, trống)

2. **Quản lý bàn** (`tables.php`)
   - Thêm/sửa/xóa bàn
   - Phân loại theo khu vực
   - Pagination 10 bàn/trang

3. **Quản lý món ăn** (`menu.php`)
   - CRUD món ăn
   - Phân loại theo danh mục
   - Pagination

4. **Quản lý nhân viên** (`users.php`)
   - Tạo tài khoản: admin, manager, waiter, kitchen
   - Chỉnh username, password, roles

5. **Bàn cần xác nhận** (`approvals.php`)
   - Duyệt món staff đã thêm
   - In phiếu bếp
   - Badge đếm số pending

6. **Doanh thu** (`revenue.php`)
   - Biểu đồ theo ngày/tháng/bàn/món
   - Chi tiết dữ liệu (4 items/trang)
   - Chốt doanh thu ngày (modal + print)
   - Lịch sử chốt (4 items/trang, click xem chi tiết)

---

## 📱 Cài đặt Staff App (Android)

### Bước 1: Build APK

```bash
cd restaurantstaff
./gradlew assembleDebug
```

APK output: `app/build/outputs/apk/debug/app-debug.apk`

### Bước 2: Cấu hình API URL

File: `restaurantstaff/app/src/main/java/com/restaurant/staff/data/remote/ApiClient.kt`

**Localhost (Android Emulator):**
```kotlin
private const val BASE_URL = "http://10.0.2.2:8081/pandabackend/api/"
```

**LAN (Thiết bị thật):**
```kotlin
private const val BASE_URL = "http://192.168.1.X:8081/pandabackend/api/"
```
*(Thay `192.168.1.X` bằng IP máy chạy backend)*

### Bước 3: Install APK

Copy APK vào điện thoại, bật "Unknown sources", cài đặt.

### Bước 4: Đăng nhập

**Tài khoản staff mặc định:**
- Username: `phamconghien123`
- Password: `admin123`

---

## 🔄 Workflow hoàn chỉnh

### Order Flow (Pending Approval)

```
1. Staff mở app → Chọn bàn → Thêm món
   ├─> API: POST /orders/{id}/items
   └─> Backend: Tạo kitchen_order_items (status: pending_approval)

2. Admin vào "Bàn cần xác nhận"
   ├─> Badge hiển thị số pending
   ├─> Click "Duyệt + In"
   └─> Backend: kitchen_order_items → order_items, status = served

3. Staff app auto-refresh (polling 5s)
   └─> Thấy món đã được approve trong "Đã xác nhận"

4. Admin thanh toán
   ├─> Modal thanh toán chỉ hiện nếu status = served
   ├─> Click "Xác nhận thanh toán"
   └─> Backend: 
       - order.status = completed
       - Xóa order_items
       - Cancel pending kitchen_orders
       - table.status = available

5. Staff app polling
   └─> Thấy bàn về trống
```

### Revenue Closure Flow

```
1. Admin vào "Doanh thu"
   ├─> Chọn ngày: 01/10/2025 - 31/10/2025
   └─> Chọn loại: "Theo ngày"

2. "Chi tiết dữ liệu" hiển thị 4 ngày/trang
   └─> Click vào ngày → Modal chi tiết món bán

3. Click "Chốt doanh thu ngày"
   ├─> Modal hiển thị món + SL + tổng tiền
   ├─> "In tổng hợp" (print preview)
   └─> "Xác nhận chốt" → Lưu vào daily_revenue_closures

4. "Lịch sử chốt ngày" (4 items/trang)
   └─> Click vào ngày → Xem chi tiết
```

---

## 🐛 Troubleshooting

### 1. API 404 Not Found
- Kiểm tra `.htaccess` trong `/api`
- Enable `mod_rewrite` trong Apache

### 2. CORS Error
- Kiểm tra `config/cors.php`
- Allow origin cho IP của mobile device

### 3. Staff app không kết nối
- Ping IP backend từ mobile: `ping 192.168.1.X`
- Kiểm tra firewall
- Đảm bảo cùng mạng LAN

### 4. Login failed
- Kiểm tra database có user không
- Verify password hash (`$2y$10$...`)
- Check PHP session enabled

### 5. Món bị duplicate x2
- ✅ Đã fix: `OrderItemController` chỉ insert vào `kitchen_order_items`
- Clear app cache: Settings → Apps → Restaurant Staff → Clear Data

---

## 📊 Database Schema

**Bảng chính:**
- `users` - Nhân viên (admin, manager, waiter, kitchen)
- `areas` - Khu vực nhà hàng
- `tables` - Bàn ăn
- `categories` - Danh mục món
- `products` - Món ăn
- `orders` - Đơn hàng
- `order_items` - Món trong đơn (confirmed)
- `kitchen_orders` - Đơn bếp (tracking)
- `kitchen_order_items` - Món trong đơn bếp (pending/approved)
- `daily_revenue_closures` - Chốt doanh thu ngày
- `payments` - Lịch sử thanh toán

**Quan hệ:**
```
areas (1) ─── (N) tables
tables (1) ─── (N) orders
orders (1) ─── (N) order_items
orders (1) ─── (N) kitchen_orders
kitchen_orders (1) ─── (N) kitchen_order_items
products (1) ─── (N) order_items
products (1) ─── (N) kitchen_order_items
```

---

## 🔐 Security

1. **JWT Authentication**
   - Token expires: 24h
   - Stored in: PHP session (admin), SharedPreferences (app)

2. **Role-based Access**
   - Admin: Full access
   - Manager: Can approve, view reports
   - Waiter: Can create orders, view menu
   - Kitchen: Can view kitchen orders

3. **SQL Injection Prevention**
   - PDO prepared statements
   - Parameter binding

4. **Password Security**
   - bcrypt hashing (`password_hash()`)
   - Cost factor: 10

---

## 📞 Support

Nếu gặp vấn đề, kiểm tra:
1. PHP error log: `php_error.log`
2. Browser console (F12)
3. Android logcat: `adb logcat | grep RestaurantStaff`

---

**Version:** 1.0  
**Last Updated:** October 2025

