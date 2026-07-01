-- ============================================================
-- LUMINA - Dữ liệu test tài khoản người dùng
-- Mật khẩu tất cả tài khoản: 123456
-- Password hash được tạo bằng: password_hash('123456', PASSWORD_BCRYPT, ['cost'=>12])
-- ============================================================
USE lumina_db;
SET NAMES utf8mb4;

-- Tắt kiểm tra FK tạm thời để xóa được
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM users WHERE email IN (
    'admin@lumina.vn',
    'manager@lumina.vn',
    'sales@lumina.vn',
    'operations@lumina.vn',
    'customer1@lumina.vn',
    'customer2@lumina.vn',
    'Admin1@gmail.com',
    'Manager1@gmail.com',
    'Sale1@gmail.com',
    'Operation1@gmail.com',
    'Customer@gmail.com'
);

SET FOREIGN_KEY_CHECKS = 1;

-- Insert admin
INSERT INTO users (role_id, full_name, email, phone, password_hash, status, email_verified_at)
SELECT r.id, 'Admin LUMINA', 'admin@lumina.vn', '0900000001',
       '$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',
       'active', NOW()
FROM roles r WHERE r.name = 'admin';

-- Insert manager
INSERT INTO users (role_id, full_name, email, phone, password_hash, status, email_verified_at)
SELECT r.id, 'Nguyễn Văn Manager', 'manager@lumina.vn', '0900000002',
       '$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',
       'active', NOW()
FROM roles r WHERE r.name = 'manager';

-- Insert sales
INSERT INTO users (role_id, full_name, email, phone, password_hash, status, email_verified_at)
SELECT r.id, 'Trần Thị Sales', 'sales@lumina.vn', '0900000003',
       '$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',
       'active', NOW()
FROM roles r WHERE r.name = 'sales';

-- Insert operations
INSERT INTO users (role_id, full_name, email, phone, password_hash, status, email_verified_at)
SELECT r.id, 'Lê Văn Operations', 'operations@lumina.vn', '0900000004',
       '$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',
       'active', NOW()
FROM roles r WHERE r.name = 'operations';

-- Insert customer 1
INSERT INTO users (role_id, full_name, email, phone, password_hash, status, email_verified_at, gender, date_of_birth, address_line, district, province)
SELECT r.id, 'Phạm Thị Khách Hàng', 'customer1@lumina.vn', '0912345678',
       '$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',
       'active', NOW(), 'female', '1995-06-15', '123 Nguyễn Văn Cừ', 'Quận 5', 'TP. Hồ Chí Minh'
FROM roles r WHERE r.name = 'customer';

-- Insert customer 2
INSERT INTO users (role_id, full_name, email, phone, password_hash, status, email_verified_at, gender, date_of_birth, address_line, district, province)
SELECT r.id, 'Nguyễn Văn Mua Sắm', 'customer2@lumina.vn', '0987654321',
       '$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',
       'active', NOW(), 'male', '1998-03-22', '456 Lê Lợi', 'Quận 1', 'TP. Hồ Chí Minh'
FROM roles r WHERE r.name = 'customer';

