-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 02, 2025 lúc 04:06 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `restaurant_db`
--

DELIMITER $$
--
-- Thủ tục
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CompleteOrderPayment` (IN `p_order_id` INT, IN `p_payment_method` ENUM('cash','card','transfer'))   BEGIN
    DECLARE v_table_id INT;
    
    -- Get table ID
    SELECT table_id INTO v_table_id FROM orders WHERE id = p_order_id;
    
    -- Update order
    UPDATE orders 
    SET payment_status = 'paid', 
        payment_method = p_payment_method,
        status = 'completed',
        updated_at = NOW()
    WHERE id = p_order_id;
    
    -- Update table status
    UPDATE tables SET status = 'available' WHERE id = v_table_id;
    
    -- Create notification
    INSERT INTO notifications (title, message, type) 
    VALUES ('Thanh toán hoàn tất', CONCAT('Đơn hàng #', (SELECT order_number FROM orders WHERE id = p_order_id), ' đã được thanh toán'), 'payment');
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CreateOrder` (IN `p_table_id` INT, IN `p_user_id` INT, IN `p_notes` TEXT)   BEGIN
    DECLARE v_order_number VARCHAR(50);
    DECLARE v_order_id INT;
    
    -- Generate order number
    SET v_order_number = CONCAT('ORD', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD((SELECT COUNT(*) + 1 FROM orders WHERE DATE(created_at) = CURDATE()), 3, '0'));
    
    -- Create order
    INSERT INTO orders (table_id, user_id, order_number, notes) 
    VALUES (p_table_id, p_user_id, v_order_number, p_notes);
    
    SET v_order_id = LAST_INSERT_ID();
    
    -- Update table status
    UPDATE tables SET status = 'occupied' WHERE id = p_table_id;
    
    -- Return order info
    SELECT v_order_id as order_id, v_order_number as order_number;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `areas`
--

INSERT INTO `areas` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Khu vực Tầng trệt', 'Khu vực chính - Tầng 1', 'active', '2025-09-05 16:50:37', '2025-09-05 18:14:52'),
(2, 'Khu vực Ven sông', 'Khu vực VIP - Tầng 1', 'active', '2025-09-05 16:50:37', '2025-09-05 18:15:09'),
(3, 'Khu vực Vip', 'Khu vực ngoài trời', 'active', '2025-09-05 16:50:37', '2025-09-05 18:15:15'),
(4, 'Khu vực D', 'Khu vực tầng 2', 'active', '2025-09-05 16:50:37', '2025-09-05 16:50:37');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `image`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Khai vị', 'Các món khai vị', NULL, 'active', 1, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(2, 'Món chính', 'Các món chính', NULL, 'active', 2, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(3, 'Món canh', 'Các món canh', NULL, 'active', 3, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(4, 'Tráng miệng', 'Các món tráng miệng', NULL, 'active', 4, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(5, 'Đồ uống', 'Các loại đồ uống', NULL, 'active', 5, '2025-09-05 16:50:37', '2025-09-05 16:50:37');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `daily_revenue_closures`
--

CREATE TABLE `daily_revenue_closures` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_orders` int(11) NOT NULL DEFAULT 0,
  `total_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `closed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `closed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `daily_revenue_closures`
--

INSERT INTO `daily_revenue_closures` (`id`, `date`, `total_orders`, `total_revenue`, `closed_by`, `notes`, `closed_at`) VALUES
(1, '2025-09-22', 2, 80000.00, 1, NULL, '2025-09-22 16:15:55');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `kitchen_orders`
--

CREATE TABLE `kitchen_orders` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `order_number` varchar(50) DEFAULT NULL,
  `staff_name` varchar(100) DEFAULT NULL,
  `status` enum('pending_approval','approved','printed','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending_approval',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `printed_by` int(11) DEFAULT NULL,
  `printed_at` timestamp NULL DEFAULT NULL,
  `print_count` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `kitchen_order_items`
--

CREATE TABLE `kitchen_order_items` (
  `id` int(11) NOT NULL,
  `kitchen_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `special_instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('order','payment','system','kitchen') NOT NULL DEFAULT 'system',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `read_at`, `created_at`) VALUES
(1, 1, 'Hệ thống khởi động', 'Hệ thống quản lý nhà hàng đã được khởi động thành công', 'system', 1, NULL, '2025-09-05 16:50:37'),
(2, 1, 'Đơn hàng mới', 'Có đơn hàng mới từ bàn A1', 'order', 1, '2025-09-22 15:02:28', '2025-09-05 16:50:37'),
(3, 3, 'Đơn hàng cần xử lý', 'Đơn hàng #ORD001 cần được xác nhận', 'order', 1, '2025-09-22 15:02:28', '2025-09-05 16:50:37'),
(4, NULL, 'Đơn hàng mới - Bàn 11', 'Nhân viên vừa thêm món \'Gỏi cuốn\' x1 cho bàn 11', 'order', 1, '2025-09-22 15:02:28', '2025-09-22 14:49:35'),
(5, NULL, 'Đơn hàng mới - Bàn 1', 'Nhân viên vừa thêm món \'Chả giò\' x1 cho bàn 1', 'order', 0, NULL, '2025-09-22 15:16:22'),
(6, NULL, 'Đơn hàng mới - Bàn 1', 'Nhân viên vừa thêm món \'Gà nướng lá chanh\' x1 cho bàn 1', 'order', 0, NULL, '2025-09-22 15:16:26'),
(7, 1, 'Đơn hàng mới - Bàn 1', 'Nhân viên vừa thêm món \'Chả giò\' x1 cho bàn 1', 'order', 0, NULL, '2025-09-22 15:46:43'),
(8, 1, 'Đơn hàng mới - Bàn 13', 'Nhân viên vừa thêm món \'Gỏi cuốn\' x1 cho bàn 13', 'order', 0, NULL, '2025-09-22 15:55:19'),
(9, 1, 'Đơn hàng mới - Bàn 11', 'Nhân viên vừa thêm món \'Chả giò\' x1 cho bàn 11', 'order', 0, NULL, '2025-10-02 12:56:20'),
(10, 1, 'Đơn hàng mới - Bàn 11', 'Nhân viên vừa thêm món \'Gỏi cuốn\' x1 cho bàn 11', 'order', 0, NULL, '2025-10-02 13:50:24');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `status` enum('pending','confirmed','preparing','ready','served','completed','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  `payment_method` enum('cash','card','transfer') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `orders`
--

INSERT INTO `orders` (`id`, `table_id`, `user_id`, `order_number`, `status`, `total_amount`, `payment_status`, `payment_method`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 3, 'ORD202509050001', '', 0.00, 'pending', NULL, NULL, '2025-09-05 18:17:35', '2025-09-05 18:17:35'),
(2, 2, 3, 'ORD202509050002', '', 0.00, 'pending', NULL, NULL, '2025-09-05 18:19:06', '2025-09-08 15:55:54'),
(3, 1, 3, 'ORD202509080001', 'completed', 155000.00, 'pending', NULL, NULL, '2025-09-08 15:39:08', '2025-09-22 15:44:43'),
(4, 11, 3, 'ORD202509080002', 'completed', 90000.00, 'pending', NULL, NULL, '2025-09-08 15:43:24', '2025-09-22 15:44:22'),
(12, 1, 1, 'ORD20250922174643827', 'completed', 35000.00, 'pending', NULL, NULL, '2025-09-22 15:46:43', '2025-09-22 15:47:02'),
(13, 13, 1, 'ORD20250922175519771', 'completed', 45000.00, 'pending', NULL, NULL, '2025-09-22 15:55:19', '2025-09-22 15:55:35'),
(14, 11, 1, 'ORD20251002145620596', 'completed', 35000.00, 'pending', NULL, NULL, '2025-10-02 12:56:20', '2025-10-02 12:57:28'),
(15, 11, 1, 'ORD20251002155024372', 'completed', 225000.00, 'pending', NULL, NULL, '2025-10-02 13:50:24', '2025-10-02 13:51:56');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','preparing','ready','served') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(12, 4, 1, 2, 45000.00, 90000.00, '', 'pending', '2025-09-22 14:49:35', '2025-09-22 15:12:46'),
(13, 3, 2, 1, 35000.00, 35000.00, '', 'pending', '2025-09-22 15:16:22', '2025-09-22 15:16:22'),
(14, 3, 3, 1, 120000.00, 120000.00, '', 'pending', '2025-09-22 15:16:26', '2025-09-22 15:16:26'),
(15, 12, 2, 1, 35000.00, 35000.00, '', 'pending', '2025-09-22 15:46:43', '2025-09-22 15:46:43'),
(16, 13, 1, 1, 45000.00, 45000.00, '', 'pending', '2025-09-22 15:55:19', '2025-09-22 15:55:19'),
(17, 14, 2, 1, 35000.00, 35000.00, '', 'pending', '2025-10-02 12:56:20', '2025-10-02 12:56:20'),
(18, 15, 1, 1, 45000.00, 45000.00, '', 'pending', '2025-10-02 13:50:24', '2025-10-02 13:50:24'),
(19, 15, 3, 1, 120000.00, 120000.00, NULL, 'pending', '2025-10-02 13:51:01', '2025-10-02 13:51:01'),
(20, 15, 20, 4, 15000.00, 60000.00, NULL, 'pending', '2025-10-02 13:51:09', '2025-10-02 13:51:09');

--
-- Bẫy `order_items`
--
DELIMITER $$
CREATE TRIGGER `update_order_total` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
    UPDATE orders 
    SET total_amount = (
        SELECT SUM(total_price) 
        FROM order_items 
        WHERE order_id = NEW.order_id
    ),
    updated_at = NOW()
    WHERE id = NEW.order_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_order_total_delete` AFTER DELETE ON `order_items` FOR EACH ROW BEGIN
    UPDATE orders 
    SET total_amount = (
        SELECT COALESCE(SUM(total_price), 0) 
        FROM order_items 
        WHERE order_id = OLD.order_id
    ),
    updated_at = NOW()
    WHERE id = OLD.order_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_order_total_update` AFTER UPDATE ON `order_items` FOR EACH ROW BEGIN
    UPDATE orders 
    SET total_amount = (
        SELECT SUM(total_price) 
        FROM order_items 
        WHERE order_id = NEW.order_id
    ),
    updated_at = NOW()
    WHERE id = NEW.order_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `order_summary_view`
-- (See below for the actual view)
--
CREATE TABLE `order_summary_view` (
`id` int(11)
,`order_number` varchar(50)
,`table_id` int(11)
,`table_name` varchar(50)
,`area_name` varchar(100)
,`user_id` int(11)
,`waiter_name` varchar(100)
,`status` enum('pending','confirmed','preparing','ready','served','completed','cancelled')
,`payment_status` enum('pending','paid','refunded')
,`total_amount` decimal(10,2)
,`created_at` timestamp
,`total_items` bigint(21)
);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category_id` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','out_of_stock') NOT NULL DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `category_id`, `image`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Gỏi cuốn', 'Gỏi cuốn tôm thịt', 45000.00, 1, NULL, 'active', 1, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(2, 'Chả giò', 'Chả giò truyền thống', 35000.00, 1, NULL, 'active', 2, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(3, 'Gà nướng lá chanh', 'Gà nướng lá chanh', 120000.00, 1, NULL, 'active', 3, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(4, 'Cơm tấm sườn nướng', 'Cơm tấm sườn nướng', 65000.00, 2, NULL, 'active', 1, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(5, 'Phở bò', 'Phở bò truyền thống', 55000.00, 2, NULL, 'active', 2, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(6, 'Bún chả', 'Bún chả Hà Nội', 45000.00, 2, NULL, 'active', 3, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(7, 'Cá lóc nướng', 'Cá lóc nướng trui', 180000.00, 2, NULL, 'active', 4, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(8, 'Tôm rang me', 'Tôm rang me chua ngọt', 220000.00, 2, NULL, 'active', 5, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(9, 'Canh chua cá lóc', 'Canh chua cá lóc', 85000.00, 3, NULL, 'active', 1, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(10, 'Canh bí đỏ', 'Canh bí đỏ thịt bằm', 45000.00, 3, NULL, 'active', 2, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(11, 'Canh rau cải', 'Canh rau cải thịt bằm', 40000.00, 3, NULL, 'active', 3, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(12, 'Chè ba màu', 'Chè ba màu', 25000.00, 4, NULL, 'active', 1, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(13, 'Bánh flan', 'Bánh flan truyền thống', 30000.00, 4, NULL, 'active', 2, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(14, 'Kem dừa', 'Kem dừa mát lạnh', 35000.00, 4, NULL, 'active', 3, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(15, 'Nước mía', 'Nước mía tươi', 15000.00, 5, NULL, 'active', 1, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(16, 'Nước cam', 'Nước cam ép', 25000.00, 5, NULL, 'active', 2, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(17, 'Cà phê sữa đá', 'Cà phê sữa đá', 20000.00, 5, NULL, 'active', 3, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(18, 'Trà đá', 'Trà đá', 10000.00, 5, NULL, 'active', 4, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(19, 'Bia Tiger', 'Bia Tiger', 35000.00, 5, NULL, 'active', 5, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(20, 'Coca cola', '', 15000.00, 5, '', 'active', 0, '2025-10-02 13:43:48', '2025-10-02 13:43:48'),
(21, 'Bia Heneiken', '', 30000.00, 5, '', 'active', 0, '2025-10-02 13:44:11', '2025-10-02 13:45:11');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tables`
--

CREATE TABLE `tables` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `area_id` int(11) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 4,
  `status` enum('available','occupied','reserved','maintenance') NOT NULL DEFAULT 'available',
  `position_x` int(11) DEFAULT 0,
  `position_y` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tables`
--

INSERT INTO `tables` (`id`, `name`, `area_id`, `capacity`, `status`, `position_x`, `position_y`, `created_at`, `updated_at`) VALUES
(1, 'A1', 1, 4, 'available', 100, 100, '2025-09-05 16:50:37', '2025-09-22 15:47:02'),
(2, 'A2', 1, 4, 'available', 200, 100, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(3, 'A3', 1, 6, 'available', 300, 100, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(4, 'A4', 1, 4, 'available', 100, 200, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(5, 'A5', 1, 4, 'available', 200, 200, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(6, 'B1', 2, 8, 'available', 100, 100, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(7, 'B2', 2, 6, 'available', 200, 100, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(8, 'B3', 2, 4, 'available', 300, 100, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(9, 'C1', 3, 4, 'available', 100, 100, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(10, 'C2', 3, 6, 'available', 200, 100, '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(11, 'D1', 4, 5, 'available', 100, 100, '2025-09-05 16:50:37', '2025-10-02 13:51:56'),
(13, 'D3', 4, 6, 'available', 300, 100, '2025-09-05 16:50:37', '2025-09-22 15:55:35'),
(14, 'D4', 4, 8, 'available', 0, 0, '2025-09-10 16:43:55', '2025-09-10 16:43:55'),
(15, 'A6', 1, 4, 'available', 0, 0, '2025-09-22 13:41:32', '2025-09-22 13:41:32'),
(16, 'B4', 2, 6, 'available', 0, 0, '2025-09-22 13:41:44', '2025-09-22 13:41:44'),
(17, 'D2', 4, 4, 'available', 0, 0, '2025-09-22 13:42:01', '2025-09-22 13:42:01');

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `table_status_view`
-- (See below for the actual view)
--
CREATE TABLE `table_status_view` (
`id` int(11)
,`name` varchar(50)
,`area_id` int(11)
,`area_name` varchar(100)
,`capacity` int(11)
,`status` enum('available','occupied','reserved','maintenance')
,`position_x` int(11)
,`position_y` int(11)
,`pending_amount` decimal(32,2)
,`active_orders` bigint(21)
);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','waiter','kitchen') NOT NULL DEFAULT 'waiter',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@restaurant.com', '0123456789', 'admin', 'active', '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(2, 'manager1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager One', 'manager@restaurant.com', '0123456790', 'manager', 'active', '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(3, 'waiter1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Waiter One', 'waiter1@restaurant.com', '0123456791', 'waiter', 'active', '2025-09-05 16:50:37', '2025-09-05 16:50:37'),
(4, 'waiter2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Waiter Two', 'waiter2@restaurant.com', '0123456792', 'waiter', 'inactive', '2025-09-05 16:50:37', '2025-10-02 13:49:40'),
(5, 'kitchen', '$2y$10$RvdVG8zeVkJ5Io6PhMnFAuiIsLgINxfHS.PMfuKndZtHZILsmpyA.', 'Kitchen', 'kitchen123@restaurant.com', '0123456793', 'kitchen', 'active', '2025-09-05 16:50:37', '2025-10-02 13:48:18'),
(6, 'phamconghien123', '$2y$10$YeoNgUhLMA/nXAp/wq9bnuBoT5TZwwVp.q5yknfP99A69zQPa.xbe', 'Phạm Công Hiền', 'phamconghien13@gmail.com', '1234565432', 'waiter', 'active', '2025-10-02 13:44:47', '2025-10-02 13:48:24');

-- --------------------------------------------------------

--
-- Cấu trúc cho view `order_summary_view`
--
DROP TABLE IF EXISTS `order_summary_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `order_summary_view`  AS SELECT `o`.`id` AS `id`, `o`.`order_number` AS `order_number`, `o`.`table_id` AS `table_id`, `t`.`name` AS `table_name`, `a`.`name` AS `area_name`, `o`.`user_id` AS `user_id`, `u`.`full_name` AS `waiter_name`, `o`.`status` AS `status`, `o`.`payment_status` AS `payment_status`, `o`.`total_amount` AS `total_amount`, `o`.`created_at` AS `created_at`, count(`oi`.`id`) AS `total_items` FROM ((((`orders` `o` left join `tables` `t` on(`o`.`table_id` = `t`.`id`)) left join `areas` `a` on(`t`.`area_id` = `a`.`id`)) left join `users` `u` on(`o`.`user_id` = `u`.`id`)) left join `order_items` `oi` on(`o`.`id` = `oi`.`order_id`)) GROUP BY `o`.`id`, `o`.`order_number`, `o`.`table_id`, `t`.`name`, `a`.`name`, `o`.`user_id`, `u`.`full_name`, `o`.`status`, `o`.`payment_status`, `o`.`total_amount`, `o`.`created_at` ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `table_status_view`
--
DROP TABLE IF EXISTS `table_status_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `table_status_view`  AS SELECT `t`.`id` AS `id`, `t`.`name` AS `name`, `t`.`area_id` AS `area_id`, `a`.`name` AS `area_name`, `t`.`capacity` AS `capacity`, `t`.`status` AS `status`, `t`.`position_x` AS `position_x`, `t`.`position_y` AS `position_y`, coalesce(sum(case when `o`.`payment_status` = 'pending' then `o`.`total_amount` else 0 end),0) AS `pending_amount`, count(case when `o`.`status` in ('pending','confirmed','preparing','ready') then `o`.`id` end) AS `active_orders` FROM ((`tables` `t` left join `areas` `a` on(`t`.`area_id` = `a`.`id`)) left join `orders` `o` on(`t`.`id` = `o`.`table_id`)) GROUP BY `t`.`id`, `t`.`name`, `t`.`area_id`, `a`.`name`, `t`.`capacity`, `t`.`status`, `t`.`position_x`, `t`.`position_y` ;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `daily_revenue_closures`
--
ALTER TABLE `daily_revenue_closures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`),
  ADD KEY `fk_drc_user` (`closed_by`),
  ADD KEY `idx_closed_at` (`closed_at`);

--
-- Chỉ mục cho bảng `kitchen_orders`
--
ALTER TABLE `kitchen_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kitchen_orders_status` (`status`),
  ADD KEY `idx_kitchen_orders_table` (`table_id`),
  ADD KEY `idx_kitchen_orders_order` (`order_id`),
  ADD KEY `idx_kitchen_orders_approved_at` (`approved_at`),
  ADD KEY `idx_kitchen_orders_printed_at` (`printed_at`),
  ADD KEY `fk_kitchen_orders_approved_by` (`approved_by`),
  ADD KEY `fk_kitchen_orders_printed_by` (`printed_by`);

--
-- Chỉ mục cho bảng `kitchen_order_items`
--
ALTER TABLE `kitchen_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_koi_kitchen_order` (`kitchen_order_id`),
  ADD KEY `idx_koi_product` (`product_id`);

--
-- Chỉ mục cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_notifications_is_read` (`is_read`),
  ADD KEY `idx_notifications_created_at` (`created_at`);

--
-- Chỉ mục cho bảng `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `table_id` (`table_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_payment_status` (`payment_status`),
  ADD KEY `idx_orders_created_at` (`created_at`);

--
-- Chỉ mục cho bảng `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_order_items_status` (`status`);

--
-- Chỉ mục cho bảng `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_products_category_status` (`category_id`,`status`);

--
-- Chỉ mục cho bảng `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `idx_tables_area_status` (`area_id`,`status`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `daily_revenue_closures`
--
ALTER TABLE `daily_revenue_closures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `kitchen_orders`
--
ALTER TABLE `kitchen_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `kitchen_order_items`
--
ALTER TABLE `kitchen_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT cho bảng `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT cho bảng `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT cho bảng `tables`
--
ALTER TABLE `tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `daily_revenue_closures`
--
ALTER TABLE `daily_revenue_closures`
  ADD CONSTRAINT `fk_drc_user` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `kitchen_orders`
--
ALTER TABLE `kitchen_orders`
  ADD CONSTRAINT `fk_kitchen_orders_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_kitchen_orders_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_kitchen_orders_printed_by` FOREIGN KEY (`printed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_kitchen_orders_table` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `kitchen_order_items`
--
ALTER TABLE `kitchen_order_items`
  ADD CONSTRAINT `fk_koi_kitchen_order` FOREIGN KEY (`kitchen_order_id`) REFERENCES `kitchen_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_koi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Các ràng buộc cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `tables`
--
ALTER TABLE `tables`
  ADD CONSTRAINT `tables_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
