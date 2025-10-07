# ✅ HƯỚNG DẪN TEST FLOW THANH TOÁN

## 🎯 MỤC TIÊU
Đảm bảo sau thanh toán, món KHÔNG còn hiển thị ở cả Admin và App.

---

## 📋 CÁC THAY ĐỔI ĐÃ THỰC HIỆN

### 1. **Admin Web** (`admin/dashboard.php`)
**Vấn đề cũ:**
- Query `/orders?table_id=${tableId}` KHÔNG filter status → lấy cả order completed
- Sau thanh toán vẫn cache `CURRENT_ORDER` → hiển thị món cũ

**Đã fix:**
- ✅ Chỉ dùng `/admin/tables/${tableId}/details` - API này filter đúng
- ✅ Kiểm tra `tableDetails.order` null → không cho mở modal
- ✅ Xóa cache `CURRENT_ORDER` và `CURRENT_TABLE_ID` sau thanh toán
- ✅ Force reload `loadTables()` sau thanh toán
- ✅ Disable button để tránh double-click

### 2. **App Mobile** (`OrderRepository.kt`)
**Vấn đề cũ:**
- Chỉ update status nhưng KHÔNG xóa `order_items` khỏi cache
- Khi API trả `items = []`, app KHÔNG xóa cache cũ → vẫn hiển thị món cũ!
- Fallback lấy từ cache → hiển thị món đã thanh toán

**Đã fix:**
- ✅ **Khi sync order:** Nếu `items = []` hoặc `null` → XÓA cache ngay
- ✅ **Sau payment:** Xóa `order_items` ngay sau thanh toán thành công
- ✅ **Khi không có order:** Xóa toàn bộ cache của bàn
- ✅ Log chi tiết để debug
- ✅ App vẫn có polling 5s tự động refresh

### 3. **Backend API** (đã đúng từ đầu)
- ✅ `OrderController::updateStatus()` - Line 258: `DELETE FROM order_items`
- ✅ `AdminController::processTablePayment()` - Line 664: `DELETE FROM order_items`
- ✅ `TableController::getCurrentOrder()` - Line 334: Filter `NOT IN ('completed')`

---

## 🧪 KỊCH BẢN TEST

### **Test 1: Admin Web - Thanh toán bình thường**

**Bước 1: Chuẩn bị**
```
1. Tạo order mới cho Bàn 1
2. Thêm 3 món: Phở, Bún, Cơm
3. Admin duyệt order
4. Order chuyển sang status = 'served'
```

**Bước 2: Thanh toán**
```
1. Vào Dashboard
2. Click vào Bàn 1
3. Click "Thanh toán"
4. Modal hiển thị 3 món ✅
5. Click "Xác nhận thanh toán"
```

**Kết quả mong đợi:**
```
✅ Alert: "Thanh toán thành công! Bàn đã được giải phóng"
✅ Modal tự đóng
✅ Bàn 1 hiển thị status = "Trống"
✅ Bàn 1 không có số tiền pending
```

**Bước 3: Kiểm tra lại**
```
1. Click vào Bàn 1 (đang trống)
2. Click "Thanh toán"
```

**Kết quả mong đợi:**
```
✅ Alert: "Bàn này không có đơn hoạt động..."
✅ Modal KHÔNG mở
✅ KHÔNG thấy món cũ
```

---

### **Test 2: App Mobile - Thanh toán**

**Bước 1: Chuẩn bị**
```
1. Tạo order từ app cho Bàn 2
2. Thêm 2 món
3. Admin duyệt
```

**Bước 2: Xem trên app**
```
1. Mở app
2. Vào màn hình Orders
3. Thấy Bàn 2 có 2 món ✅
```

**Bước 3: Admin thanh toán**
```
1. Vào Dashboard
2. Thanh toán Bàn 2
```

**Bước 4: Kiểm tra app**
```
1. Đợi 5 giây (polling)
2. Hoặc pull-to-refresh
```

**Kết quả mong đợi:**
```
✅ Bàn 2 biến mất khỏi danh sách
✅ Hoặc hiển thị trống (nếu vẫn ở màn hình)
✅ KHÔNG thấy món cũ
```

---

### **Test 3: Database - Kiểm tra trực tiếp**

**Sau khi thanh toán Bàn 1 và Bàn 2:**

```sql
-- 1. Kiểm tra orders
SELECT id, order_number, table_id, status, total_amount 
FROM orders 
WHERE table_id IN (1, 2)
ORDER BY created_at DESC;
```

**Kết quả mong đợi:**
```
✅ Order của Bàn 1: status = 'completed'
✅ Order của Bàn 2: status = 'completed'
```

```sql
-- 2. Kiểm tra order_items (PHẢI RỖNG)
SELECT oi.*, o.status 
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
WHERE o.table_id IN (1, 2) AND o.status = 'completed';
```

**Kết quả mong đợi:**
```
✅ 0 rows (Không có món nào)
```

```sql
-- 3. Kiểm tra kitchen_orders (GIỮ LẠI cho báo cáo)
SELECT ko.id, ko.order_id, ko.status, COUNT(koi.id) as items_count
FROM kitchen_orders ko
LEFT JOIN kitchen_order_items koi ON ko.id = koi.kitchen_order_id
JOIN orders o ON ko.order_id = o.id
WHERE o.table_id IN (1, 2) AND o.status = 'completed'
GROUP BY ko.id;
```

**Kết quả mong đợi:**
```
✅ kitchen_orders.status = 'served'
✅ kitchen_order_items vẫn còn (cho báo cáo)
```

```sql
-- 4. Kiểm tra tables
SELECT id, name, status FROM tables WHERE id IN (1, 2);
```

**Kết quả mong đợi:**
```
✅ Bàn 1: status = 'available'
✅ Bàn 2: status = 'available'
```

---

### **Test 4: Edge Cases - App Cache**

#### Test 4.1: App hiển thị món cũ (QUAN TRỌNG!)
```
Tình huống: App có cache cũ trước khi fix

Bước 1: Tạo order cho Bàn 6 từ app
Bước 2: Thêm 2 món
Bước 3: Admin thanh toán
Bước 4: App vẫn mở, vào lại Bàn 6
```

**Kết quả mong đợi:**
```
✅ App hiển thị: "Không có order"
✅ KHÔNG thấy 2 món cũ
✅ Nếu thấy món cũ → cần REINSTALL app hoặc clear cache
```

**Cách clear cache app (nếu vẫn lỗi):**
```
1. Settings → Apps → RestaurantStaff
2. Storage → Clear Data
3. Hoặc: Uninstall → Reinstall
```

#### Test 4.2: Thanh toán 2 lần
```
1. Thanh toán Bàn 3
2. Không refresh trang
3. Thử click "Thanh toán" lại vào Bàn 3
```

**Kết quả mong đợi:**
```
✅ Alert: "Bàn này không có đơn hoạt động..."
✅ KHÔNG cho thanh toán lại
```

#### Test 4.3: Refresh trình duyệt
```
1. Thanh toán Bàn 4
2. Hard refresh (Ctrl + Shift + R)
3. Xem Bàn 4
```

**Kết quả mong đợi:**
```
✅ Bàn 4 vẫn trống
✅ KHÔNG có món cũ
```

#### Test 4.4: Mở nhiều tab
```
1. Mở Dashboard ở 2 tab
2. Tab 1: Thanh toán Bàn 5
3. Tab 2: Click vào Bàn 5
```

**Kết quả mong đợi:**
```
✅ Tab 2: "Bàn này không có đơn hoạt động..."
```

---

## 🐛 NẾU VẪN LỖI

### Lỗi: Vẫn thấy món sau thanh toán

**Kiểm tra:**

1. **Clear cache trình duyệt**
   ```
   Ctrl + Shift + Delete → Clear cache
   Hoặc: Mở Incognito mode
   ```

2. **Kiểm tra database**
   ```sql
   -- Xem order_items có bị xóa không
   SELECT COUNT(*) FROM order_items oi
   JOIN orders o ON oi.order_id = o.id
   WHERE o.status = 'completed';
   ```
   → Phải = 0

3. **Kiểm tra log**
   - Browser console (F12)
   - Backend error log
   - App logcat

4. **Chạy script cleanup** (nếu có dữ liệu cũ)
   ```sql
   -- Xóa order_items của orders đã completed
   DELETE oi FROM order_items oi
   JOIN orders o ON oi.order_id = o.id
   WHERE o.status IN ('completed', 'paid');
   
   -- Set lại bàn về available
   UPDATE tables t
   SET status = 'available'
   WHERE NOT EXISTS (
       SELECT 1 FROM orders o 
       WHERE o.table_id = t.id 
       AND o.status NOT IN ('completed', 'cancelled')
   );
   ```

---

## 📊 LOGIC HOẠT ĐỘNG

```
┌─────────────────────────────────────────────┐
│ TRƯỚC THANH TOÁN                            │
├─────────────────────────────────────────────┤
│ orders.status = 'served'                    │
│ order_items = [món A, món B, món C]         │
│ kitchen_orders.status = 'approved'/'served' │
│ tables.status = 'occupied'                  │
│                                             │
│ ✅ UI hiển thị: Có món                      │
└─────────────────────────────────────────────┘

                    ↓
            [THANH TOÁN]
                    ↓

┌─────────────────────────────────────────────┐
│ SAU THANH TOÁN                              │
├─────────────────────────────────────────────┤
│ orders.status = 'completed' ← Không query   │
│ order_items = [] ← ĐÃ XÓA                   │
│ kitchen_orders.status = 'served' ← GIỮ LẠI  │
│ tables.status = 'available'                 │
│                                             │
│ ✅ UI hiển thị: BÀN TRỐNG                   │
└─────────────────────────────────────────────┘

                    ↓
          [BÁO CÁO DOANH THU]
                    ↓

┌─────────────────────────────────────────────┐
│ LẤY TỪ kitchen_order_items                  │
├─────────────────────────────────────────────┤
│ WHERE ko.status = 'served'                  │
│ WHERE o.status IN ('completed','paid')      │
│                                             │
│ ✅ Vẫn tính được doanh thu                  │
└─────────────────────────────────────────────┘
```

---

## ✅ CHECKLIST

- [ ] Test 1: Admin thanh toán → Bàn trống ✓
- [ ] Test 2: App polling → Món biến mất ✓
- [ ] Test 3: Database → order_items = 0 ✓
- [ ] Test 4.1: Không thanh toán 2 lần ✓
- [ ] Test 4.2: Refresh vẫn OK ✓
- [ ] Test 4.3: Multi-tab OK ✓
- [ ] Báo cáo doanh thu vẫn đúng ✓

---

**Người test:** _____________  
**Ngày test:** _____________  
**Kết quả:** ⬜ PASS  ⬜ FAIL

---

✅ **ĐÃ FIX XONG - SẴN SÀNG TEST!**

