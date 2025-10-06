-- Schema cho ứng dụng Order nhà hàng
-- RESTful API Backend + Android MVVM
-- Created: $(date)

-- Drop existing tables (for development)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS daily_revenue_closures;
DROP TABLE IF EXISTS staff_sessions;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS tables;
DROP TABLE IF EXISTS areas;
DROP TABLE IF EXISTS staff;
SET FOREIGN_KEY_CHECKS = 1;

-- Bảng khu vực nhà hàng
CREATE TABLE areas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bảng nhân viên
CREATE TABLE staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_code VARCHAR(20) UNIQUE NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- bcrypt hashed
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    email VARCHAR(100),
    role ENUM('waiter', 'manager', 'admin', 'kitchen') DEFAULT 'waiter',
    is_active BOOLEAN DEFAULT TRUE,
    avatar_url VARCHAR(500),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_staff_code (staff_code),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Bảng bàn ăn
CREATE TABLE tables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    area_id INT NOT NULL,
    table_number VARCHAR(20) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    capacity INT DEFAULT 4,
    status ENUM('available', 'occupied', 'reserved', 'cleaning', 'maintenance') DEFAULT 'available',
    qr_code VARCHAR(100), -- QR code cho scan
    position_x INT DEFAULT 0, -- tọa độ x cho layout
    position_y INT DEFAULT 0, -- tọa độ y cho layout
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_table_area (area_id, table_number),
    INDEX idx_area_id (area_id),
    INDEX idx_status (status),
    INDEX idx_table_number (table_number)
);

-- Bảng danh mục món ăn
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon_url VARCHAR(500),
    color VARCHAR(7) DEFAULT '#000000', -- hex color
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_display_order (display_order),
    INDEX idx_active (is_active)
);

-- Bảng món ăn
CREATE TABLE menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    item_code VARCHAR(20) UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2) DEFAULT 0, -- giá vốn
    image_url VARCHAR(500),
    preparation_time INT DEFAULT 15, -- phút
    is_available BOOLEAN DEFAULT TRUE,
    is_popular BOOLEAN DEFAULT FALSE, -- món hot
    ingredients TEXT, -- nguyên liệu
    allergens VARCHAR(500), -- dị ứng
    calories INT DEFAULT 0,
    spicy_level TINYINT DEFAULT 0, -- 0-5
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category_id (category_id),
    INDEX idx_available (is_available),
    INDEX idx_popular (is_popular),
    INDEX idx_price (price),
    INDEX idx_item_code (item_code),
    FULLTEXT idx_search (name, description)
);

-- Bảng đơn hàng
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    table_id INT NOT NULL,
    staff_id INT NOT NULL,
    customer_count INT DEFAULT 1,
    status ENUM('draft', 'submitted', 'confirmed', 'preparing', 'ready', 'served', 'paid', 'cancelled') DEFAULT 'draft',
    subtotal DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    service_charge DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    special_requests TEXT,
    estimated_ready_time TIMESTAMP NULL,
    kitchen_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_table_id (table_id),
    INDEX idx_staff_id (staff_id),
    INDEX idx_status (status),
    INDEX idx_order_number (order_number),
    INDEX idx_created_at (created_at),
    INDEX idx_submitted_at (submitted_at)
);

-- Bảng chi tiết đơn hàng
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    special_instructions TEXT,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'served', 'cancelled') DEFAULT 'pending',
    preparation_started_at TIMESTAMP NULL,
    ready_at TIMESTAMP NULL,
    served_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_menu_item_id (menu_item_id),
    INDEX idx_status (status)
);

-- Bảng thanh toán
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    payment_method ENUM('cash', 'card', 'bank_transfer', 'e_wallet', 'voucher') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    received_amount DECIMAL(10,2) DEFAULT 0, -- tiền nhận được
    change_amount DECIMAL(10,2) DEFAULT 0, -- tiền thừa
    reference_number VARCHAR(100), -- mã giao dịch
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    processed_by INT, -- staff xử lý
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_order_id (order_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_payment_method (payment_method),
    INDEX idx_created_at (created_at)
);

-- Bảng chốt doanh thu ngày
CREATE TABLE daily_revenue_closures (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    total_orders INT NOT NULL DEFAULT 0,
    total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
    closed_by INT NULL,
    notes TEXT,
    closed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (closed_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_closed_at (closed_at),
    INDEX idx_date (date)
);

-- Bảng session đăng nhập (cho JWT alternative)
CREATE TABLE staff_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    device_info VARCHAR(500),
    ip_address VARCHAR(45),
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_staff_id (staff_id),
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at)
);

-- Triggers để tự động tạo order_number
DELIMITER $$
CREATE TRIGGER generate_order_number 
BEFORE INSERT ON orders 
FOR EACH ROW 
BEGIN 
    DECLARE next_order_num INT;
    DECLARE today_prefix VARCHAR(10);
    
    SET today_prefix = DATE_FORMAT(NOW(), '%y%m%d');
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(order_number, 7) AS UNSIGNED)), 0) + 1 
    INTO next_order_num 
    FROM orders 
    WHERE order_number LIKE CONCAT(today_prefix, '%');
    
    SET NEW.order_number = CONCAT(today_prefix, LPAD(next_order_num, 3, '0'));
END$$
DELIMITER ;

-- Trigger tự động cập nhật tổng tiền order
DELIMITER $$
CREATE TRIGGER update_order_total_after_insert
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders 
    SET subtotal = (
        SELECT COALESCE(SUM(total_price), 0) 
        FROM order_items 
        WHERE order_id = NEW.order_id
    ),
    total_amount = subtotal - discount_amount + tax_amount + service_charge
    WHERE id = NEW.order_id;
END$$

CREATE TRIGGER update_order_total_after_update
AFTER UPDATE ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders 
    SET subtotal = (
        SELECT COALESCE(SUM(total_price), 0) 
        FROM order_items 
        WHERE order_id = NEW.order_id
    ),
    total_amount = subtotal - discount_amount + tax_amount + service_charge
    WHERE id = NEW.order_id;
END$$

CREATE TRIGGER update_order_total_after_delete
AFTER DELETE ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders 
    SET subtotal = (
        SELECT COALESCE(SUM(total_price), 0) 
        FROM order_items 
        WHERE order_id = OLD.order_id
    ),
    total_amount = subtotal - discount_amount + tax_amount + service_charge
    WHERE id = OLD.order_id;
END$$

-- Trigger cập nhật bàn khi order thay đổi status
CREATE TRIGGER update_table_status_on_order_change
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    -- Nếu order mới được tạo (submitted)
    IF OLD.status = 'draft' AND NEW.status IN ('submitted', 'confirmed') THEN
        UPDATE tables SET status = 'occupied' WHERE id = NEW.table_id;
    END IF;
    
    -- Nếu order được thanh toán
    IF NEW.status = 'paid' THEN
        -- Check nếu không còn order active nào cho bàn này
        IF NOT EXISTS (
            SELECT 1 FROM orders 
            WHERE table_id = NEW.table_id 
            AND status NOT IN ('paid', 'cancelled') 
            AND id != NEW.id
        ) THEN
            UPDATE tables SET status = 'cleaning' WHERE id = NEW.table_id;
        END IF;
    END IF;
END$$
DELIMITER ;

-- Views hỗ trợ cho API responses

-- View tổng hợp thông tin order
CREATE VIEW order_summary AS
SELECT 
    o.id,
    o.order_number,
    o.table_id,
    CONCAT(a.name, ' - ', t.table_name) as table_info,
    o.staff_id,
    s.full_name as staff_name,
    o.customer_count,
    o.status,
    o.subtotal,
    o.discount_amount,
    o.tax_amount,
    o.service_charge,
    o.total_amount,
    o.special_requests,
    o.created_at,
    o.submitted_at,
    o.completed_at,
    COUNT(oi.id) as item_count,
    SUM(oi.quantity) as total_quantity
FROM orders o
JOIN tables t ON o.table_id = t.id
JOIN areas a ON t.area_id = a.id
JOIN staff s ON o.staff_id = s.id
LEFT JOIN order_items oi ON o.id = oi.order_id
GROUP BY o.id;

-- View menu với category info
CREATE VIEW menu_with_category AS
SELECT 
    mi.id,
    mi.item_code,
    mi.name,
    mi.description,
    mi.price,
    mi.image_url,
    mi.preparation_time,
    mi.is_available,
    mi.is_popular,
    mi.spicy_level,
    mi.calories,
    mi.display_order,
    c.id as category_id,
    c.name as category_name,
    c.icon_url as category_icon,
    c.color as category_color
FROM menu_items mi
JOIN categories c ON mi.category_id = c.id
WHERE mi.is_available = TRUE AND c.is_active = TRUE;

-- View thống kê bàn
CREATE VIEW table_status_summary AS
SELECT 
    a.id as area_id,
    a.name as area_name,
    COUNT(t.id) as total_tables,
    SUM(CASE WHEN t.status = 'available' THEN 1 ELSE 0 END) as available_tables,
    SUM(CASE WHEN t.status = 'occupied' THEN 1 ELSE 0 END) as occupied_tables,
    SUM(CASE WHEN t.status = 'reserved' THEN 1 ELSE 0 END) as reserved_tables,
    SUM(CASE WHEN t.status = 'cleaning' THEN 1 ELSE 0 END) as cleaning_tables,
    SUM(CASE WHEN t.status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_tables
FROM areas a
LEFT JOIN tables t ON a.id = t.area_id
WHERE a.is_active = TRUE
GROUP BY a.id, a.name;

-- View tổng tiền đang chờ thanh toán cho mỗi bàn
CREATE VIEW table_pending_amount AS
SELECT 
    t.id as table_id,
    t.table_number,
    t.table_name,
    t.capacity,
    t.status,
    t.area_id,
    a.name as area_name,
    COALESCE(SUM(o.total_amount), 0) as pending_amount,
    COUNT(o.id) as active_orders,
    MAX(o.updated_at) as last_order_update
FROM tables t
JOIN areas a ON t.area_id = a.id
LEFT JOIN orders o ON t.id = o.table_id 
    AND o.status NOT IN ('paid', 'cancelled')
WHERE t.status != 'maintenance'
GROUP BY t.id, t.table_number, t.table_name, t.capacity, t.status, t.area_id, a.name;