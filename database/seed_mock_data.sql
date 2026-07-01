-- ============================================================
-- LUMINA - Dữ liệu mẫu (Mock Data) cho Báo cáo & Demo
-- Chạy script này để thêm Đơn kính, Đơn hàng, Đánh giá & Đổi trả mẫu.
-- ============================================================

USE lumina_db;
SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

-- Xóa dữ liệu mẫu cũ để tránh trùng lặp khi chạy lại
DELETE FROM return_requests;
DELETE FROM order_status_logs;
DELETE FROM order_items;
DELETE FROM orders;
DELETE FROM product_reviews;
DELETE FROM customer_prescriptions;

SET FOREIGN_KEY_CHECKS = 1;

-- Lấy hoặc tạo vai trò nếu chưa có
INSERT INTO roles (name, description)
SELECT 'admin', 'System administrator' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'admin');

INSERT INTO roles (name, description)
SELECT 'customer', 'Customer / buyer' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'customer');

-- Đảm bảo có tài khoản Khách hàng 1
INSERT INTO users (role_id, full_name, email, phone, password_hash, status, email_verified_at, gender, date_of_birth, address_line, district, province)
SELECT r.id, 'Phạm Thị Khách Hàng', 'customer1@lumina.vn', '0912345678',
       '$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS', -- Mật khẩu: 123456
       'active', NOW(), 'female', '1995-06-15', '123 Nguyễn Văn Cừ', 'Quận 5', 'TP. Hồ Chí Minh'
FROM roles r 
WHERE r.name = 'customer'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'customer1@lumina.vn');

-- Đảm bảo có tài khoản Khách hàng 2
INSERT INTO users (role_id, full_name, email, phone, password_hash, status, email_verified_at, gender, date_of_birth, address_line, district, province)
SELECT r.id, 'Nguyễn Văn Mua Sắm', 'customer2@lumina.vn', '0987654321',
       '$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS', -- Mật khẩu: 123456
       'active', NOW(), 'male', '1998-03-22', '456 Lê Lợi', 'Quận 1', 'TP. Hồ Chí Minh'
FROM roles r 
WHERE r.name = 'customer'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'customer2@lumina.vn');

-- Đảm bảo có tài khoản Admin
INSERT INTO users (role_id, full_name, email, phone, password_hash, status, email_verified_at)
SELECT r.id, 'Admin LUMINA', 'admin@lumina.vn', '0900000001',
       '$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',
       'active', NOW()
FROM roles r 
WHERE r.name = 'admin'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@lumina.vn');

-- Lấy ID của Khách hàng 1 và Khách hàng 2
SET @customer1_id = (SELECT id FROM users WHERE email = 'customer1@lumina.vn' LIMIT 1);
SET @customer2_id = (SELECT id FROM users WHERE email = 'customer2@lumina.vn' LIMIT 1);
SET @admin_id = (SELECT id FROM users WHERE email = 'admin@lumina.vn' LIMIT 1);

-- Lấy ID của các biến thể sản phẩm mẫu
SET @variant_oval_blk = (SELECT id FROM product_variants WHERE sku = 'LMAIR-OVAL-BLK-M' LIMIT 1);
SET @variant_oval_gld = (SELECT id FROM product_variants WHERE sku = 'LMAIR-OVAL-GLD-M' LIMIT 1);
SET @variant_cat_grace = (SELECT id FROM product_variants WHERE sku = 'LMCAT-GRACE-BRW-M' LIMIT 1);
SET @variant_sun_voyager = (SELECT id FROM product_variants WHERE sku = 'LMSUN-VOYAGER-BLK-L' LIMIT 1);

-- Lấy ID của các tròng kính
SET @lens_blue_cut = (SELECT id FROM lens_options WHERE slug = 'blue-cut-156' LIMIT 1);
SET @lens_photochromic = (SELECT id FROM lens_options WHERE slug = 'photochromic-160' LIMIT 1);

-- Lấy ID của sản phẩm
SET @product_oval = (SELECT id FROM products WHERE slug = 'lumina-air-oval' LIMIT 1);
SET @product_cat = (SELECT id FROM products WHERE slug = 'lumina-cat-eye-grace' LIMIT 1);
SET @product_sun = (SELECT id FROM products WHERE slug = 'lumina-sun-voyager' LIMIT 1);


-- ============================================================
-- 1. THÊM VÍ ĐƠN KÍNH THUỐC MẪU (customer_prescriptions)
-- ============================================================
INSERT INTO customer_prescriptions (
    user_id, profile_name, od_sphere, od_cylinder, od_axis, od_addition,
    os_sphere, os_cylinder, os_axis, os_addition, pd_right, pd_left, pd_distance, note, is_default
) VALUES
(
    @customer1_id, 'Đơn kính cận đi làm', 
    -2.50, -0.75, 175, NULL,
    -2.25, -0.50, 180, NULL,
    31.5, 31.0, 62.5, 'Kính dùng để làm việc máy tính nhiều, tròng chống ánh sáng xanh.', 1
),
(
    @customer1_id, 'Đơn kính đọc sách', 
    +1.50, NULL, NULL, 1.25,
    +1.25, NULL, NULL, 1.25,
    30.0, 30.0, 60.0, 'Đơn kính đọc sách, nhìn gần.', 0
),
(
    @customer2_id, 'Kính cận học tập', 
    -4.00, -1.00, 160, NULL,
    -3.75, -1.25, 165, NULL,
    32.0, 32.0, 64.0, 'Mắt cận lệch và loạn thị nhẹ.', 1
);

SET @presc_cust1_default = (SELECT id FROM customer_prescriptions WHERE user_id = @customer1_id AND is_default = 1 LIMIT 1);
SET @presc_cust2_default = (SELECT id FROM customer_prescriptions WHERE user_id = @customer2_id AND is_default = 1 LIMIT 1);


-- ============================================================
-- 2. THÊM ĐƠN HÀNG MẪU (orders)
-- ============================================================

-- ĐƠN 1: Đã hoàn thành (Completed) - Khách hàng 1 mua kính cận Air Oval
INSERT INTO orders (
    user_id, handled_by, order_code, order_type, status,
    customer_name, customer_email, customer_phone,
    shipping_address_line, shipping_ward, shipping_district, shipping_province,
    subtotal, lens_total, shipping_fee, discount_amount, total_amount,
    payment_method, payment_status, prescription_wallet_id,
    confirmed_at, shipped_at, completed_at, created_at
) VALUES (
    @customer1_id, @admin_id, 'LMN-ORD-001', 'prescription', 'completed',
    'Phạm Thị Khách Hàng', 'customer1@lumina.vn', '0912345678',
    '123 Nguyễn Văn Cừ', 'Phường 4', 'Quận 5', 'TP. Hồ Chí Minh',
    890000.00, 350000.00, 30000.00, 50000.00, 1220000.00,
    'cod', 'paid', @presc_cust1_default,
    DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)
);
SET @order1_id = (SELECT id FROM orders WHERE order_code = 'LMN-ORD-001' LIMIT 1);

INSERT INTO order_items (order_id, product_variant_id, lens_option_id, product_name, variant_sku, quantity, unit_price, lens_price, line_total)
VALUES (@order1_id, @variant_oval_blk, @lens_blue_cut, 'LUMINA Air Oval', 'LMAIR-OVAL-BLK-M', 1, 890000.00, 350000.00, 1240000.00);


-- ĐƠN 2: Đã hoàn thành (Completed) - Khách 2 mua kính mát Sun Voyager thanh toán online VNPAY
INSERT INTO orders (
    user_id, handled_by, order_code, order_type, status,
    customer_name, customer_email, customer_phone,
    shipping_address_line, shipping_ward, shipping_district, shipping_province,
    subtotal, lens_total, shipping_fee, discount_amount, total_amount,
    payment_method, payment_status,
    confirmed_at, shipped_at, completed_at, created_at
) VALUES (
    @customer2_id, @admin_id, 'LMN-ORD-002', 'available', 'completed',
    'Nguyễn Văn Mua Sắm', 'customer2@lumina.vn', '0987654321',
    '456 Lê Lợi', 'Phường Bến Thành', 'Quận 1', 'TP. Hồ Chí Minh',
    1980000.00, 0.00, 0.00, 100000.00, 1880000.00,
    'vnpay', 'paid',
    DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)
);
SET @order2_id = (SELECT id FROM orders WHERE order_code = 'LMN-ORD-002' LIMIT 1);

INSERT INTO order_items (order_id, product_variant_id, lens_option_id, product_name, variant_sku, quantity, unit_price, lens_price, line_total)
VALUES (@order2_id, @variant_sun_voyager, NULL, 'LUMINA Sun Voyager', 'LMSUN-VOYAGER-BLK-L', 2, 990000.00, 0.00, 1980000.00);


-- ĐƠN 3: Đang giao hàng (Shipping) - Khách 1 mua kính mắt mèo Cat Eye Grace
INSERT INTO orders (
    user_id, handled_by, order_code, order_type, status,
    customer_name, customer_email, customer_phone,
    shipping_address_line, shipping_ward, shipping_district, shipping_province,
    subtotal, lens_total, shipping_fee, discount_amount, total_amount,
    payment_method, payment_status, prescription_wallet_id,
    confirmed_at, shipped_at, created_at
) VALUES (
    @customer1_id, @admin_id, 'LMN-ORD-003', 'prescription', 'shipping',
    'Phạm Thị Khách Hàng', 'customer1@lumina.vn', '0912345678',
    '123 Nguyễn Văn Cừ', 'Phường 4', 'Quận 5', 'TP. Hồ Chí Minh',
    1190000.00, 650000.00, 30000.00, 0.00, 1870000.00,
    'bank_transfer', 'paid', @presc_cust1_default,
    DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)
);
SET @order3_id = (SELECT id FROM orders WHERE order_code = 'LMN-ORD-003' LIMIT 1);

INSERT INTO order_items (order_id, product_variant_id, lens_option_id, product_name, variant_sku, quantity, unit_price, lens_price, line_total)
VALUES (@order3_id, @variant_cat_grace, @lens_photochromic, 'LUMINA Cat Eye Grace', 'LMCAT-GRACE-BRW-M', 1, 1190000.00, 650000.00, 1840000.00);


-- ĐƠN 4: Chờ xử lý (Pending) - Khách 2 đặt mua kính mát
INSERT INTO orders (
    user_id, order_code, order_type, status,
    customer_name, customer_email, customer_phone,
    shipping_address_line, shipping_ward, shipping_district, shipping_province,
    subtotal, lens_total, shipping_fee, discount_amount, total_amount,
    payment_method, payment_status, created_at
) VALUES (
    @customer2_id, 'LMN-ORD-004', 'available', 'pending',
    'Nguyễn Văn Mua Sắm', 'customer2@lumina.vn', '0987654321',
    '456 Lê Lợi', 'Phường Bến Thành', 'Quận 1', 'TP. Hồ Chí Minh',
    990000.00, 0.00, 30000.00, 0.00, 1020000.00,
    'cod', 'unpaid', DATE_SUB(NOW(), INTERVAL 4 HOUR)
);
SET @order4_id = (SELECT id FROM orders WHERE order_code = 'LMN-ORD-004' LIMIT 1);

INSERT INTO order_items (order_id, product_variant_id, lens_option_id, product_name, variant_sku, quantity, unit_price, lens_price, line_total)
VALUES (@order4_id, @variant_sun_voyager, NULL, 'LUMINA Sun Voyager', 'LMSUN-VOYAGER-BLK-L', 1, 990000.00, 0.00, 990000.00);


-- ĐƠN 5: Đã hủy (Cancelled)
INSERT INTO orders (
    user_id, order_code, order_type, status,
    customer_name, customer_email, customer_phone,
    shipping_address_line, shipping_ward, shipping_district, shipping_province,
    subtotal, lens_total, shipping_fee, discount_amount, total_amount,
    payment_method, payment_status, cancelled_at, created_at
) VALUES (
    @customer2_id, 'LMN-ORD-005', 'available', 'cancelled',
    'Nguyễn Văn Mua Sắm', 'customer2@lumina.vn', '0987654321',
    '456 Lê Lợi', 'Phường Bến Thành', 'Quận 1', 'TP. Hồ Chí Minh',
    890000.00, 0.00, 30000.00, 0.00, 920000.00,
    'cod', 'unpaid', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)
);
SET @order5_id = (SELECT id FROM orders WHERE order_code = 'LMN-ORD-005' LIMIT 1);

INSERT INTO order_items (order_id, product_variant_id, lens_option_id, product_name, variant_sku, quantity, unit_price, lens_price, line_total)
VALUES (@order5_id, @variant_oval_blk, NULL, 'LUMINA Air Oval', 'LMAIR-OVAL-BLK-M', 1, 890000.00, 0.00, 890000.00);


-- ============================================================
-- 3. THÊM LOG TRẠNG THÁI ĐƠN HÀNG (order_status_logs)
-- ============================================================
INSERT INTO order_status_logs (order_id, changed_by, old_status, new_status, note) VALUES
(@order1_id, @admin_id, 'pending', 'confirmed', 'Admin xác nhận thông tin đơn hàng.'),
(@order1_id, @admin_id, 'confirmed', 'processing', 'Đang lắp tròng kính cận cho khách.'),
(@order1_id, @admin_id, 'processing', 'shipping', 'Đơn hàng được giao cho đơn vị vận chuyển.'),
(@order1_id, @admin_id, 'shipping', 'completed', 'Khách hàng đã nhận được hàng và thanh toán COD.'),

(@order2_id, NULL, 'pending', 'confirmed', 'Hệ thống tự động xác nhận đơn hàng đã thanh toán qua VNPAY.'),
(@order2_id, @admin_id, 'confirmed', 'shipping', 'Đóng gói sản phẩm và bàn giao vận chuyển.'),
(@order2_id, @admin_id, 'shipping', 'completed', 'Giao hàng thành công.'),

(@order3_id, @admin_id, 'pending', 'confirmed', 'Xác nhận chuyển khoản thành công.'),
(@order3_id, @admin_id, 'confirmed', 'processing', 'Đang chuyển giao kỹ thuật gia công tròng đổi màu.'),
(@order3_id, @admin_id, 'processing', 'shipping', 'Giao hàng qua bưu điện.');


-- ============================================================
-- 4. THÊM ĐÁNH GIÁ SẢN PHẨM MẪU (product_reviews)
-- ============================================================
INSERT INTO product_reviews (user_id, product_id, order_id, rating, title, body, images, status, helpful_count, created_at) VALUES
(
    @customer1_id, @product_oval, @order1_id, 5, 
    'Gọng kính rất nhẹ và đẹp!', 
    'Kính đeo rất êm tai, không bị đau hay cấn mút thái dương. Tròng chống ánh sáng xanh đi kèm cắt chuẩn độ cận, đeo làm việc máy tính cả ngày không mỏi mắt. Cực kì hài lòng, sẽ ủng hộ Lumina tiếp!', 
    '["/assets/images/reviews/oval-review-1.jpg"]', 'approved', 4, DATE_SUB(NOW(), INTERVAL 2 DAY)
),
(
    @customer2_id, @product_sun, @order2_id, 4, 
    'Kính mát xịn xò, đóng gói cẩn thận', 
    'Chất lượng nhựa TR90 của kính khá cứng cáp, mang đi đường xa chống chói cực tốt. Đóng gói hộp rất sang trọng có kèm khăn lau và bao da. Trừ 1 sao vì bưu tá giao hàng hơi trễ tí.', 
    '[]', 'approved', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)
);


-- ============================================================
-- 5. THÊM YÊU CẦU ĐỔI TRẢ MẪU (return_requests)
-- ============================================================
-- Khách hàng 1 có đơn hàng đã hoàn thành và muốn đổi sang size/màu khác do đeo rộng
INSERT INTO return_requests (order_id, user_id, reason, request_type, status, images, created_at) VALUES
(
    @order1_id, @customer1_id, 
    'Tôi muốn đổi sang gọng màu Vàng cùng loại vì đeo thử thấy màu đen không hợp với khuôn mặt lắm.', 
    'exchange', 'pending', 
    '["/assets/images/returns/return-request-1.jpg"]', 
    DATE_SUB(NOW(), INTERVAL 12 HOUR)
);

SELECT 'Mock data seeded successfully for Lumina Eyeglass Shop!' AS result;
