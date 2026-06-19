-- ============================================================
-- LUMINA Migration: Prescription Wallet + Product Reviews
-- Run this on an existing lumina_db to add the new features
-- ============================================================

USE lumina_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- TABLE: customer_prescriptions
-- Ví lưu trữ đơn kính cá nhân của khách hàng
-- ============================================================
CREATE TABLE IF NOT EXISTS customer_prescriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    profile_name VARCHAR(150) NOT NULL DEFAULT 'Đơn kính của tôi',
    -- Mắt phải (OD - Oculus Dexter)
    od_sphere DECIMAL(5,2) NULL COMMENT 'Cầu (SPH) mắt phải, từ -20 đến +20',
    od_cylinder DECIMAL(5,2) NULL COMMENT 'Trụ (CYL) mắt phải',
    od_axis SMALLINT UNSIGNED NULL COMMENT 'Trục (AXIS) mắt phải 0-180',
    od_addition DECIMAL(5,2) NULL COMMENT 'Cộng thêm (ADD) mắt phải, cho kính lão',
    -- Mắt trái (OS - Oculus Sinister)
    os_sphere DECIMAL(5,2) NULL COMMENT 'Cầu (SPH) mắt trái',
    os_cylinder DECIMAL(5,2) NULL COMMENT 'Trụ (CYL) mắt trái',
    os_axis SMALLINT UNSIGNED NULL COMMENT 'Trục (AXIS) mắt trái 0-180',
    os_addition DECIMAL(5,2) NULL COMMENT 'Cộng thêm (ADD) mắt trái',
    -- Khoảng cách đồng tử PD
    pd_right DECIMAL(5,2) NULL COMMENT 'PD mắt phải (mm)',
    pd_left DECIMAL(5,2) NULL COMMENT 'PD mắt trái (mm)',
    pd_distance DECIMAL(5,2) NULL COMMENT 'PD tổng khi nhìn xa (mm)',
    pd_near DECIMAL(5,2) NULL COMMENT 'PD tổng khi nhìn gần (mm)',
    -- Metadata
    note TEXT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cust_presc_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_cust_presc_user (user_id),
    INDEX idx_cust_presc_default (user_id, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: product_reviews
-- Đánh giá & phản hồi sản phẩm (chỉ khách đã mua)
-- ============================================================
CREATE TABLE IF NOT EXISTS product_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL COMMENT 'Đơn hàng đã completed cho phép đánh giá',
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1-5 sao',
    title VARCHAR(200) NULL,
    body TEXT NULL,
    images JSON NULL COMMENT 'Mảng tối đa 3 đường dẫn ảnh',
    status ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(255) NULL,
    helpful_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Mỗi user chỉ đánh giá 1 lần cho mỗi sản phẩm
    UNIQUE KEY uq_review_user_product (user_id, product_id),
    CONSTRAINT fk_review_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_review_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_review_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_review_product (product_id),
    INDEX idx_review_status (status),
    INDEX idx_review_rating (rating),
    INDEX idx_review_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Thêm cột prescription_wallet_id vào bảng orders (nếu chưa có)
DROP PROCEDURE IF EXISTS AddWalletCol;
DELIMITER $$
CREATE PROCEDURE AddWalletCol()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'prescription_wallet_id'
  ) THEN
    ALTER TABLE orders
      ADD COLUMN prescription_wallet_id BIGINT UNSIGNED NULL AFTER prescription_id,
      ADD CONSTRAINT fk_orders_wallet_prescription
        FOREIGN KEY (prescription_wallet_id) REFERENCES customer_prescriptions(id)
        ON UPDATE CASCADE ON DELETE SET NULL;
  END IF;
END$$
DELIMITER ;
CALL AddWalletCol();
DROP PROCEDURE IF EXISTS AddWalletCol;

SELECT 'Migration completed successfully!' AS status;
