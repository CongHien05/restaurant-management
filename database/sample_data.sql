-- Sample data cho ứng dụng Order nhà hàng
-- RESTful API + Android MVVM

-- Thêm khu vực
INSERT INTO areas (name, description, display_order, is_active) VALUES
('Tầng trệt', 'Khu vực chính tầng trệt, điều hòa mát mẻ', 1, TRUE),
('Sân thượng', 'Khu vực sân thượng thoáng mát, view đẹp', 2, TRUE),
('Phòng VIP', 'Phòng riêng cho khách VIP, yên tĩnh', 3, TRUE);

-- Thêm nhân viên (password: "123456")
INSERT INTO staff (staff_code, username, password, full_name, phone, role, is_active) VALUES
('ADM001', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quản lý', '0901234567', 'admin', TRUE),
('WAI001', 'waiter1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lê Văn Nam', '0903456789', 'waiter', TRUE),
('WAI002', 'waiter2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Phạm Thị Lan', '0904567890', 'waiter', TRUE);

-- Thêm bàn ăn
INSERT INTO tables (area_id, table_number, table_name, capacity, status, qr_code) VALUES
-- Tầng trệt
(1, 'T01', 'Bàn T01', 4, 'available', 'QR_T01_001'),
(1, 'T02', 'Bàn T02', 4, 'occupied', 'QR_T02_002'),
(1, 'T03', 'Bàn T03', 6, 'available', 'QR_T03_003'),
-- Sân thượng
(2, 'S01', 'Bàn S01', 4, 'available', 'QR_S01_004'),
(2, 'S02', 'Bàn S02', 6, 'occupied', 'QR_S02_005'),
-- VIP
(3, 'V01', 'Phòng VIP 1', 8, 'available', 'QR_V01_006');

-- Thêm danh mục món ăn
INSERT INTO categories (name, description, icon_url, color, display_order, is_active) VALUES
('Khai vị', 'Các món khai vị, salad, gỏi', 'icon-appetizer.png', '#FF6B6B', 1, TRUE),
('Món chính', 'Các món cơm, phở, bún, mì', 'icon-main.png', '#4ECDC4', 2, TRUE),
('Đồ uống', 'Nước ngọt, bia, nước ép', 'icon-drink.png', '#6C5CE7', 3, TRUE),
('Tráng miệng', 'Bánh ngọt, kem, chè', 'icon-dessert.png', '#FD79A8', 4, TRUE);

-- Thêm món ăn
INSERT INTO menu_items (category_id, item_code, name, description, price, cost_price, preparation_time, is_available, is_popular, spicy_level, display_order) VALUES
-- Khai vị
(1, 'KV001', 'Gỏi cuốn tôm thịt', 'Gỏi cuốn tươi với tôm và thịt', 45000, 25000, 10, TRUE, TRUE, 0, 1),
(1, 'KV002', 'Nem nướng', 'Nem nướng với bánh tráng', 55000, 30000, 15, TRUE, FALSE, 1, 2),
-- Món chính
(2, 'MC001', 'Phở bò tái', 'Phở bò truyền thống', 70000, 35000, 15, TRUE, TRUE, 0, 1),
(2, 'MC002', 'Cơm tấm sườn', 'Cơm tấm với sườn nướng', 75000, 40000, 25, TRUE, TRUE, 1, 2),
-- Đồ uống
(3, 'DU001', 'Bia Saigon', 'Bia Saigon lon 330ml', 25000, 15000, 2, TRUE, TRUE, 0, 1),
(3, 'DU002', 'Nước cam', 'Nước cam tươi vắt', 35000, 20000, 5, TRUE, FALSE, 0, 2),
-- Tráng miệng
(4, 'TM001', 'Chè ba màu', 'Chè ba màu truyền thống', 25000, 12000, 5, TRUE, TRUE, 0, 1);

-- Thêm đơn hàng mẫu
INSERT INTO orders (order_number, table_id, staff_id, customer_count, status, subtotal, total_amount, submitted_at) VALUES
('2401010001', 2, 2, 4, 'preparing', 170000, 187000, NOW() - INTERVAL 30 MINUTE),
('2401010002', 5, 3, 6, 'ready', 220000, 242000, NOW() - INTERVAL 15 MINUTE);

-- Chi tiết đơn hàng
INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, total_price, status) VALUES
-- Đơn 1
(1, 1, 2, 45000, 90000, 'preparing'),
(1, 3, 1, 70000, 70000, 'preparing'),
(1, 5, 2, 25000, 50000, 'ready'),
-- Đơn 2  
(2, 2, 1, 55000, 55000, 'ready'),
(2, 4, 1, 75000, 75000, 'ready'),
(2, 6, 2, 35000, 70000, 'ready'),
(2, 7, 1, 25000, 25000, 'ready');