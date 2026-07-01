-- ============================================================
-- Script thêm 8 sản phẩm còn lại vào cơ sở dữ liệu lumina_db
-- ============================================================

USE lumina_db;
SET NAMES utf8mb4;

-- 3. LUMINA Titanium Lite (Gọng kính Titanium)
INSERT INTO products (category_id, name, slug, brand, short_description, description, frame_type, target_gender, material, shape, default_price, thumbnail, is_prescription_supported, status)
SELECT id, 'LUMINA Titanium Lite', 'lumina-titanium-lite', 'LUMINA', 'Gọng kính Titanium không viền cao cấp, siêu nhẹ và sang trọng.', 'Sử dụng chất liệu Titanium siêu nhẹ (chỉ nặng khoảng 8g), không gây dị ứng da và chống ăn mòn cực tốt. Thiết kế tối giản lịch lãm, phù hợp cho doanh nhân.', 'Rimless', 'unisex', 'Titanium', 'Rectangle', 1850000.00, '', 1, 'active'
FROM categories WHERE slug = 'gong-kinh-kim-loai' LIMIT 1;

SET @prod_id_3 = LAST_INSERT_ID();
INSERT INTO product_variants (product_id, sku, color, size_label, width_mm, bridge_mm, temple_length_mm, material, price, stock_quantity, reorder_level)
VALUES (@prod_id_3, 'LMTI-LITE-SIL-M', 'Bạc', 'M', 135.00, 18.00, 140.00, 'Titanium', 1850000.00, 15, 3);


-- 4. LUMINA Cat Eye Grace V2 (Gọng kính mắt mèo thời trang)
INSERT INTO products (category_id, name, slug, brand, short_description, description, frame_type, target_gender, material, shape, default_price, thumbnail, is_prescription_supported, status)
SELECT id, 'LUMINA Cat Eye Grace V2', 'lumina-cat-eye-grace-v2', 'LUMINA', 'Gọng mắt mèo thời trang tôn lên vẻ sang trọng cho phái nữ.', 'Sự kết hợp hoàn hảo giữa viền nhựa Acetate đen bóng và càng kính kim loại mạ vàng sang trọng. Thiết kế thanh thoát giúp khuôn mặt nữ giới trông thon gọn và cuốn hút hơn.', 'Cat Eye', 'female', 'Nhựa và Kim loại', 'Cat Eye', 950000.00, '', 1, 'active'
FROM categories WHERE slug = 'gong-kinh-mat-meo' LIMIT 1;

SET @prod_id_4 = LAST_INSERT_ID();
INSERT INTO product_variants (product_id, sku, color, size_label, width_mm, bridge_mm, temple_length_mm, material, price, stock_quantity, reorder_level)
VALUES (@prod_id_4, 'LMCAT-GRACE-BLK-M', 'Đen vàng', 'M', 136.00, 17.00, 142.00, 'Nhựa Acetate', 950000.00, 20, 5);


-- 5. LUMINA Aviator Classic (Kính mát phi công)
INSERT INTO products (category_id, name, slug, brand, short_description, description, frame_type, target_gender, material, shape, default_price, thumbnail, is_prescription_supported, status)
SELECT id, 'LUMINA Aviator Classic', 'lumina-aviator-classic', 'LUMINA', 'Kính mát dáng phi công kinh điển với tròng chống tia UV400.', 'Thiết kế gọng đôi độc đáo, mắt kính màu xanh rau muống chống chói cực tốt khi lái xe hoặc đi nắng. Bản lề chắc chắn, đệm mũi silicone êm ái có thể tự điều chỉnh.', 'Aviator', 'unisex', 'Hợp kim', 'Teardrop', 1250000.00, '', 0, 'active'
FROM categories WHERE slug = 'kinh-mat-nam' LIMIT 1;

SET @prod_id_5 = LAST_INSERT_ID();
INSERT INTO product_variants (product_id, sku, color, size_label, width_mm, bridge_mm, temple_length_mm, material, price, stock_quantity, reorder_level)
VALUES (@prod_id_5, 'LMAVI-CLASSIC-gld-L', 'Gọng vàng tròng xanh', 'L', 140.00, 14.00, 135.00, 'Hợp kim', 1250000.00, 10, 2);


-- 6. LUMINA Wayfarer Retro (Kính mát thể thao)
INSERT INTO products (category_id, name, slug, brand, short_description, description, frame_type, target_gender, material, shape, default_price, thumbnail, is_prescription_supported, status)
SELECT id, 'LUMINA Wayfarer Retro', 'lumina-wayfarer-retro', 'LUMINA', 'Kính mát thể thao gọng dẻo TR90 chống tia cực tím.', 'Sử dụng gọng nhựa dẻo TR90 chịu lực cực tốt, siêu nhẹ, không bị gãy gập khi vận động thể thao mạnh. Tròng phân cực Polarized chống lóa hoàn hảo khi di chuyển ngoài trời.', 'Wayfarer', 'male', 'Nhựa dẻo TR90', 'Square', 790000.00, '', 0, 'active'
FROM categories WHERE slug = 'kinh-mat-nam' LIMIT 1;

SET @prod_id_6 = LAST_INSERT_ID();
INSERT INTO product_variants (product_id, sku, color, size_label, width_mm, bridge_mm, temple_length_mm, material, price, stock_quantity, reorder_level)
VALUES (@prod_id_6, 'LMWAY-RETRO-BLK-M', 'Đen nhám', 'M', 142.00, 18.00, 140.00, 'Nhựa dẻo TR90', 790000.00, 30, 5);


-- 7. LUMINA Oversized Lady (Kính mát nữ gọng tròn to)
INSERT INTO products (category_id, name, slug, brand, short_description, description, frame_type, target_gender, material, shape, default_price, thumbnail, is_prescription_supported, status)
SELECT id, 'LUMINA Oversized Lady', 'lumina-oversized-lady', 'LUMINA', 'Kính mát gọng to sang chảnh phong cách ngôi sao điện ảnh.', 'Tròng kính chuyển màu khói thời thượng chống tia UV tốt bảo vệ đôi mắt tối đa. Gọng nhựa Acetate bóng bẩy kết hợp họa tiết đồi mồi sang trọng, thời thượng.', 'Oversized', 'female', 'Nhựa Acetate', 'Round', 1450000.00, '', 0, 'active'
FROM categories WHERE slug = 'kinh-mat-nu' LIMIT 1;

SET @prod_id_7 = LAST_INSERT_ID();
INSERT INTO product_variants (product_id, sku, color, size_label, width_mm, bridge_mm, temple_length_mm, material, price, stock_quantity, reorder_level)
VALUES (@prod_id_7, 'LMOVER-LADY-DM-F', 'Đồi mồi', 'F', 145.00, 20.00, 145.00, 'Nhựa Acetate', 1450000.00, 8, 2);


-- 8. Tròng Essilor Crizal Rock 1.56 (Tròng kính chống trầy)
INSERT INTO products (category_id, name, slug, brand, short_description, description, frame_type, target_gender, material, shape, default_price, thumbnail, is_prescription_supported, status)
SELECT id, 'Tròng Essilor Crizal Rock 1.56', 'trong-essilor-crizal-rock-156', 'Essilor', 'Tròng kính siêu chống trầy xước và hạn chế bám bụi bẩn nước mưa.', 'Công nghệ phủ Crizal Rock giúp tăng cường độ cứng gấp 3 lần so với tròng thông thường. Hạn chế tối đa bám nước, bám bụi bẩn, dấu vân tay và chống chói khi lái xe đêm.', 'None', 'unisex', 'Nhựa CR39', 'Circular', 890000.00, '', 0, 'active'
FROM categories WHERE slug = 'trong-chong-anh-sang-xanh' LIMIT 1;

SET @prod_id_8 = LAST_INSERT_ID();
INSERT INTO product_variants (product_id, sku, color, size_label, price, stock_quantity, reorder_level)
VALUES (@prod_id_8, 'LENS-ESS-ROCK-156', 'Trong suốt', 'Standard', 890000.00, 100, 10);


-- 9. Tròng Zeiss BlueGuard 1.60 (Tròng chống ánh sáng xanh)
INSERT INTO products (category_id, name, slug, brand, short_description, description, frame_type, target_gender, material, shape, default_price, thumbnail, is_prescription_supported, status)
SELECT id, 'Tròng Zeiss BlueGuard 1.60', 'trong-zeiss-blueguard-160', 'Zeiss', 'Tròng kính bảo vệ mắt trước ánh sáng xanh có hại từ màn hình thiết bị điện tử.', 'Zeiss BlueGuard lọc ánh sáng xanh tích hợp sâu vào chất liệu tròng kính giúp ngăn cản tới 40% ánh sáng xanh có hại nhưng không bị ngả vàng gây mất thẩm mỹ. Độ trong suốt hoàn hảo giúp nhìn màu sắc trung thực nhất.', 'None', 'unisex', 'Nhựa 1.60', 'Circular', 1490000.00, '', 0, 'active'
FROM categories WHERE slug = 'trong-chong-anh-sang-xanh' LIMIT 1;

SET @prod_id_9 = LAST_INSERT_ID();
INSERT INTO product_variants (product_id, sku, color, size_label, price, stock_quantity, reorder_level)
VALUES (@prod_id_9, 'LENS-ZEI-BG-160', 'Trong suốt', 'Standard', 1490000.00, 50, 5);


-- 10. Tròng Chemi U2 1.67 (Tròng siêu mỏng)
INSERT INTO products (category_id, name, slug, brand, short_description, description, frame_type, target_gender, material, shape, default_price, thumbnail, is_prescription_supported, status)
SELECT id, 'Tròng Chemi U2 1.67', 'trong-chemi-u2-167', 'Chemi', 'Tròng kính chiết suất mỏng 1.67 lý tưởng cho người cận từ 4 độ trở lên.', 'Tròng kính mỏng và nhẹ hơn tròng thông thường tới 30-35%, giảm hiệu ứng rìa kính dày. Công nghệ lớp phủ Crystal U2 chống tĩnh điện, hạn chế bám bụi bẩn và vân tay cực tốt.', 'None', 'unisex', 'Nhựa 1.67', 'Circular', 680000.00, '', 0, 'active'
FROM categories WHERE slug = 'trong-doi-mau' LIMIT 1;

SET @prod_id_10 = LAST_INSERT_ID();
INSERT INTO product_variants (product_id, sku, color, size_label, price, stock_quantity, reorder_level)
VALUES (@prod_id_10, 'LENS-CHE-U2-167', 'Trong suốt', 'Standard', 680000.00, 80, 10);

SELECT 'Added remaining 8 products and variants successfully!' AS result;
