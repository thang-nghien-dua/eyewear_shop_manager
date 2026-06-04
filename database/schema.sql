-- LUMINA eyewear shop database schema
-- MySQL 8+

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS lumina_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lumina_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS return_requests;
DROP TABLE IF EXISTS order_status_logs;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS carts;
DROP TABLE IF EXISTS prescriptions;
DROP TABLE IF EXISTS product_lens_options;
DROP TABLE IF EXISTS lens_options;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS product_variants;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL,
    gender ENUM('male', 'female', 'other') NULL,
    date_of_birth DATE NULL,
    address_line VARCHAR(255) NULL,
    ward VARCHAR(120) NULL,
    district VARCHAR(120) NULL,
    province VARCHAR(120) NULL,
    postal_code VARCHAR(20) NULL,
    status ENUM('active', 'inactive', 'banned') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    INDEX idx_users_role_id (role_id),
    INDEX idx_users_full_name (full_name)
) ENGINE=InnoDB;

CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    category_type ENUM('frame', 'sunglasses', 'lens', 'service', 'other') NOT NULL DEFAULT 'other',
    description TEXT NULL,
    image_url VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_id) REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    UNIQUE KEY uq_categories_name_parent (name, parent_id),
    INDEX idx_categories_parent_id (parent_id),
    INDEX idx_categories_type_active (category_type, is_active)
) ENGINE=InnoDB;

CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    brand VARCHAR(120) NULL,
    short_description VARCHAR(255) NULL,
    description TEXT NULL,
    frame_type VARCHAR(100) NULL,
    target_gender ENUM('male', 'female', 'unisex', 'kids') NOT NULL DEFAULT 'unisex',
    material VARCHAR(100) NULL,
    shape VARCHAR(100) NULL,
    default_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    compare_at_price DECIMAL(12,2) NULL,
    thumbnail VARCHAR(255) NULL,
    is_prescription_supported TINYINT(1) NOT NULL DEFAULT 0,
    has_3d_model TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft', 'active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_products_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_products_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_products_category_id (category_id),
    INDEX idx_products_name (name),
    INDEX idx_products_brand (brand),
    INDEX idx_products_status (status)
) ENGINE=InnoDB;

CREATE TABLE product_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(80) NULL,
    size_label VARCHAR(50) NULL,
    width_mm DECIMAL(6,2) NULL,
    bridge_mm DECIMAL(6,2) NULL,
    temple_length_mm DECIMAL(6,2) NULL,
    material VARCHAR(100) NULL,
    price DECIMAL(12,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 0,
    is_preorder_allowed TINYINT(1) NOT NULL DEFAULT 0,
    estimated_arrival_date DATE NULL,
    weight_grams DECIMAL(8,2) NULL,
    image_override VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_variants_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    INDEX idx_variants_product_id (product_id),
    INDEX idx_variants_stock (stock_quantity),
    INDEX idx_variants_preorder (is_preorder_allowed)
) ENGINE=InnoDB;

CREATE TABLE product_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NULL,
    image_url VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_product_images_variant
        FOREIGN KEY (variant_id) REFERENCES product_variants(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_product_images_product (product_id),
    INDEX idx_product_images_variant (variant_id)
) ENGINE=InnoDB;

CREATE TABLE lens_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT NULL,
    lens_type ENUM('single_vision', 'progressive', 'bifocal', 'non_prescription', 'other') NOT NULL DEFAULT 'single_vision',
    coating VARCHAR(150) NULL,
    refractive_index VARCHAR(50) NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lens_options_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_lens_options_category (category_id),
    INDEX idx_lens_options_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE product_lens_options (
    product_id BIGINT UNSIGNED NOT NULL,
    lens_option_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, lens_option_id),
    CONSTRAINT fk_product_lens_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_product_lens_option
        FOREIGN KEY (lens_option_id) REFERENCES lens_options(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE prescriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    prescription_name VARCHAR(150) NULL,
    right_sphere DECIMAL(5,2) NULL,
    left_sphere DECIMAL(5,2) NULL,
    right_cylinder DECIMAL(5,2) NULL,
    left_cylinder DECIMAL(5,2) NULL,
    right_axis SMALLINT NULL,
    left_axis SMALLINT NULL,
    right_addition DECIMAL(5,2) NULL,
    left_addition DECIMAL(5,2) NULL,
    pd_distance DECIMAL(5,2) NULL,
    pd_near DECIMAL(5,2) NULL,
    prism VARCHAR(100) NULL,
    note TEXT NULL,
    attachment_path VARCHAR(255) NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NULL,
    verification_status ENUM('pending', 'approved', 'rejected', 'needs_clarification') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prescriptions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_prescriptions_verified_by
        FOREIGN KEY (verified_by) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_prescriptions_user (user_id),
    INDEX idx_prescriptions_status (verification_status)
) ENGINE=InnoDB;

CREATE TABLE carts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_carts_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cart_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    lens_option_id BIGINT UNSIGNED NULL,
    prescription_id BIGINT UNSIGNED NULL,
    order_type ENUM('available', 'preorder', 'prescription') NOT NULL DEFAULT 'available',
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_items_cart
        FOREIGN KEY (cart_id) REFERENCES carts(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_cart_items_lens
        FOREIGN KEY (lens_option_id) REFERENCES lens_options(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_cart_items_prescription
        FOREIGN KEY (prescription_id) REFERENCES prescriptions(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_cart_items_cart_id (cart_id),
    INDEX idx_cart_items_order_type (order_type)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    handled_by BIGINT UNSIGNED NULL,
    order_code VARCHAR(30) NOT NULL UNIQUE,
    order_type ENUM('available', 'preorder', 'prescription') NOT NULL DEFAULT 'available',
    status ENUM(
        'pending',
        'awaiting_stock',
        'checking_prescription',
        'confirmed',
        'processing',
        'lens_processing',
        'shipping',
        'completed',
        'cancelled',
        'refunded'
    ) NOT NULL DEFAULT 'pending',
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    shipping_address_line VARCHAR(255) NOT NULL,
    shipping_ward VARCHAR(120) NULL,
    shipping_district VARCHAR(120) NULL,
    shipping_province VARCHAR(120) NULL,
    postal_code VARCHAR(20) NULL,
    note TEXT NULL,
    internal_note TEXT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    lens_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cod', 'bank_transfer', 'momo', 'vnpay', 'other') NOT NULL DEFAULT 'cod',
    payment_status ENUM('unpaid', 'paid', 'partially_paid', 'failed', 'refunded') NOT NULL DEFAULT 'unpaid',
    prescription_id BIGINT UNSIGNED NULL,
    confirmed_at DATETIME NULL,
    shipped_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_orders_handled_by
        FOREIGN KEY (handled_by) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_orders_prescription
        FOREIGN KEY (prescription_id) REFERENCES prescriptions(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_orders_user_id (user_id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_type (order_type),
    INDEX idx_orders_created_at (created_at),
    INDEX idx_orders_payment_status (payment_status)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    lens_option_id BIGINT UNSIGNED NULL,
    product_name VARCHAR(180) NOT NULL,
    variant_sku VARCHAR(100) NOT NULL,
    variant_snapshot JSON NULL,
    lens_snapshot JSON NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    lens_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_order_items_lens
        FOREIGN KEY (lens_option_id) REFERENCES lens_options(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_order_items_order_id (order_id),
    INDEX idx_order_items_variant_id (product_variant_id)
) ENGINE=InnoDB;

CREATE TABLE order_status_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    changed_by BIGINT UNSIGNED NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_status_logs_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_order_status_logs_changed_by
        FOREIGN KEY (changed_by) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_order_status_logs_order_id (order_id),
    INDEX idx_order_status_logs_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE return_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    handled_by BIGINT UNSIGNED NULL,
    reason TEXT NOT NULL,
    request_type ENUM('return', 'exchange', 'warranty', 'refund') NOT NULL DEFAULT 'return',
    status ENUM('pending', 'approved', 'rejected', 'received', 'resolved') NOT NULL DEFAULT 'pending',
    resolution_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_return_requests_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_return_requests_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_return_requests_handled_by
        FOREIGN KEY (handled_by) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_return_requests_order_id (order_id),
    INDEX idx_return_requests_user_id (user_id),
    INDEX idx_return_requests_status (status)
) ENGINE=InnoDB;
