USE lumina_db;
SET NAMES utf8mb4;
INSERT INTO roles (name, description) VALUES
('customer', 'Customer / buyer'),
('sales', 'Sales and support staff'),
('operations', 'Operations staff'),
('manager', 'Business manager'),
('admin', 'System administrator');

-- Create admin/customer accounts later from your app or by using PHP password_hash().

INSERT INTO categories (parent_id, name, slug, category_type, sort_order) VALUES
(NULL, 'Gọng kính', 'gong-kinh', 'frame', 1),
(NULL, 'Kính mát', 'kinh-mat', 'sunglasses', 2),
(NULL, 'Tròng kính', 'trong-kinh', 'lens', 3);

INSERT INTO categories (parent_id, name, slug, category_type, sort_order)
SELECT c.id, 'Gọng kính kim loại', 'gong-kinh-kim-loai', 'frame', 1
FROM categories c WHERE c.slug = 'gong-kinh';

INSERT INTO categories (parent_id, name, slug, category_type, sort_order)
SELECT c.id, 'Gọng kính oval', 'gong-kinh-oval', 'frame', 2
FROM categories c WHERE c.slug = 'gong-kinh';

INSERT INTO categories (parent_id, name, slug, category_type, sort_order)
SELECT c.id, 'Gọng kính mắt mèo', 'gong-kinh-mat-meo', 'frame', 3
FROM categories c WHERE c.slug = 'gong-kinh';

INSERT INTO categories (parent_id, name, slug, category_type, sort_order)
SELECT c.id, 'Gọng kính nhựa', 'gong-kinh-nhua', 'frame', 4
FROM categories c WHERE c.slug = 'gong-kinh';

INSERT INTO categories (parent_id, name, slug, category_type, sort_order)
SELECT c.id, 'Kính mát nam', 'kinh-mat-nam', 'sunglasses', 1
FROM categories c WHERE c.slug = 'kinh-mat';

INSERT INTO categories (parent_id, name, slug, category_type, sort_order)
SELECT c.id, 'Kính mát nữ', 'kinh-mat-nu', 'sunglasses', 2
FROM categories c WHERE c.slug = 'kinh-mat';

INSERT INTO categories (parent_id, name, slug, category_type, sort_order)
SELECT c.id, 'Kính mát em bé', 'kinh-mat-em-be', 'sunglasses', 3
FROM categories c WHERE c.slug = 'kinh-mat';

INSERT INTO categories (parent_id, name, slug, category_type, sort_order)
SELECT c.id, 'Tròng chống ánh sáng xanh', 'trong-chong-anh-sang-xanh', 'lens', 1
FROM categories c WHERE c.slug = 'trong-kinh';

INSERT INTO categories (parent_id, name, slug, category_type, sort_order)
SELECT c.id, 'Tròng đổi màu', 'trong-doi-mau', 'lens', 2
FROM categories c WHERE c.slug = 'trong-kinh';

INSERT INTO lens_options (category_id, name, slug, description, lens_type, coating, refractive_index, price)
SELECT c.id, 'Blue Cut 1.56', 'blue-cut-156', 'Tròng chống ánh sáng xanh cơ bản', 'single_vision', 'Blue Cut', '1.56', 350000
FROM categories c WHERE c.slug = 'trong-chong-anh-sang-xanh';

INSERT INTO lens_options (category_id, name, slug, description, lens_type, coating, refractive_index, price)
SELECT c.id, 'Photochromic 1.60', 'photochromic-160', 'Tròng đổi màu khi ra nắng', 'single_vision', 'UV + đổi màu', '1.60', 650000
FROM categories c WHERE c.slug = 'trong-doi-mau';

INSERT INTO products (
    category_id, name, slug, brand, short_description, description,
    frame_type, target_gender, material, shape, default_price,
    thumbnail, is_prescription_supported, has_3d_model, status
)
SELECT c.id,
       'LUMINA Air Oval',
       'lumina-air-oval',
       'LUMINA',
       'Gọng kính oval tối giản cho dân văn phòng',
       'Mẫu gọng nhẹ, phù hợp cắt tròng cận và chống ánh sáng xanh.',
       'Oval', 'unisex', 'Kim loại', 'Oval', 890000,
       '/assets/images/products/air-oval.jpg', 1, 0, 'active'
FROM categories c WHERE c.slug = 'gong-kinh-oval';

INSERT INTO products (
    category_id, name, slug, brand, short_description, description,
    frame_type, target_gender, material, shape, default_price,
    thumbnail, is_prescription_supported, has_3d_model, status
)
SELECT c.id,
       'LUMINA Cat Eye Grace',
       'lumina-cat-eye-grace',
       'LUMINA',
       'Gọng mắt mèo thanh lịch dành cho nữ',
       'Dòng gọng nhẹ, thời trang, phù hợp lắp nhiều loại tròng.',
       'Cat Eye', 'female', 'Nhựa acetate', 'Cat Eye', 1190000,
       '/assets/images/products/cat-eye-grace.jpg', 1, 0, 'active'
FROM categories c WHERE c.slug = 'gong-kinh-mat-meo';

INSERT INTO products (
    category_id, name, slug, brand, short_description, description,
    frame_type, target_gender, material, shape, default_price,
    thumbnail, is_prescription_supported, has_3d_model, status
)
SELECT c.id,
       'LUMINA Sun Voyager',
       'lumina-sun-voyager',
       'LUMINA',
       'Kính mát unisex phong cách du lịch',
       'Mẫu kính mát phù hợp đi đường và du lịch.',
       'Sunglasses', 'unisex', 'TR90', 'Square', 990000,
       '/assets/images/products/sun-voyager.jpg', 0, 0, 'active'
FROM categories c WHERE c.slug = 'kinh-mat-nam';

INSERT INTO product_variants (
    product_id, sku, color, size_label, width_mm, bridge_mm, temple_length_mm,
    material, price, stock_quantity, reorder_level, is_preorder_allowed, estimated_arrival_date
)
SELECT p.id, 'LMAIR-OVAL-BLK-M', 'Đen', 'M', 134, 18, 140, 'Kim loại', 890000, 10, 3, 1, DATE_ADD(CURDATE(), INTERVAL 10 DAY)
FROM products p WHERE p.slug = 'lumina-air-oval';

INSERT INTO product_variants (
    product_id, sku, color, size_label, width_mm, bridge_mm, temple_length_mm,
    material, price, stock_quantity, reorder_level, is_preorder_allowed, estimated_arrival_date
)
SELECT p.id, 'LMAIR-OVAL-GLD-M', 'Vàng', 'M', 134, 18, 140, 'Kim loại', 920000, 0, 3, 1, DATE_ADD(CURDATE(), INTERVAL 14 DAY)
FROM products p WHERE p.slug = 'lumina-air-oval';

INSERT INTO product_variants (
    product_id, sku, color, size_label, width_mm, bridge_mm, temple_length_mm,
    material, price, stock_quantity, reorder_level, is_preorder_allowed
)
SELECT p.id, 'LMCAT-GRACE-BRW-M', 'Nâu', 'M', 136, 17, 142, 'Nhựa acetate', 1190000, 6, 2, 0
FROM products p WHERE p.slug = 'lumina-cat-eye-grace';

INSERT INTO product_variants (
    product_id, sku, color, size_label, width_mm, bridge_mm, temple_length_mm,
    material, price, stock_quantity, reorder_level, is_preorder_allowed
)
SELECT p.id, 'LMSUN-VOYAGER-BLK-L', 'Đen', 'L', 142, 20, 145, 'TR90', 990000, 12, 4, 0
FROM products p WHERE p.slug = 'lumina-sun-voyager';

INSERT INTO product_images (product_id, variant_id, image_url, alt_text, is_primary, sort_order)
SELECT p.id, NULL, '/assets/images/products/air-oval-1.jpg', 'LUMINA Air Oval', 1, 1
FROM products p WHERE p.slug = 'lumina-air-oval';

INSERT INTO product_images (product_id, variant_id, image_url, alt_text, is_primary, sort_order)
SELECT p.id, NULL, '/assets/images/products/cat-eye-grace-1.jpg', 'LUMINA Cat Eye Grace', 1, 1
FROM products p WHERE p.slug = 'lumina-cat-eye-grace';

INSERT INTO product_images (product_id, variant_id, image_url, alt_text, is_primary, sort_order)
SELECT p.id, NULL, '/assets/images/products/sun-voyager-1.jpg', 'LUMINA Sun Voyager', 1, 1
FROM products p WHERE p.slug = 'lumina-sun-voyager';

INSERT INTO product_lens_options (product_id, lens_option_id)
SELECT p.id, l.id
FROM products p
CROSS JOIN lens_options l
WHERE p.slug IN ('lumina-air-oval', 'lumina-cat-eye-grace')
  AND l.slug IN ('blue-cut-156', 'photochromic-160');
