<?php

require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';

if (is_logged_in()) {
    redirect_to('/profile.php');
}

$db = Database::connect();
$errors = [];
$form = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    // 'address' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) {
        $form[$key] = trim($_POST[$key] ?? '');
    }
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($form['full_name'] === '') {
        $errors[] = 'Vui lòng nhập họ tên.';
    }
    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }
    if ($password === '' || strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải từ 6 ký tự.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }

    if (!$errors) {
        $exists = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $exists->execute(['email' => $form['email']]);

        if ($exists->fetch()) {
            $errors[] = 'Email này đã được sử dụng.';
        }
    }

    if (!$errors) {
    $roleStmt = $db->query("SELECT id, name FROM roles WHERE name = 'customer' LIMIT 1");
    $role = $roleStmt->fetch();

    if (!$role) {
        $db->exec("INSERT INTO roles (name) VALUES ('customer')");
        $role = ['id' => (int) $db->lastInsertId(), 'name' => 'customer'];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("
        INSERT INTO users (role_id, full_name, email, phone, password_hash, created_at, updated_at)
        VALUES (:role_id, :full_name, :email, :phone, :password_hash, NOW(), NOW())
    ");

    $stmt->execute([
        ':role_id' => (int) $role['id'],
        ':full_name' => $form['full_name'],
        ':email' => $form['email'],
        ':phone' => $form['phone'],
        ':password_hash' => $passwordHash,
    ]);

    $userId = (int) $db->lastInsertId();

    login_user([
        'id' => $userId,
        'role_id' => (int) $role['id'],
        'role_name' => 'customer',
        'full_name' => $form['full_name'],
        'email' => $form['email'],
        'phone' => $form['phone'],
    ]);

    add_flash('success', 'Tạo tài khoản thành công.');
    redirect_to('/profile.php');
}
}

$pageTitle = 'Đăng ký';
require_once BASE_PATH . '/app/views/partials/head.php';
require_once BASE_PATH . '/app/views/partials/header.php';
?>
<main class="page-section simple-page">
    <div class="container">
        <section class="auth-wrap auth-wide">
            <div class="section-heading-row compact">
                <div>
                    <h1>Đăng ký khách hàng</h1>
                    <p>Tạo tài khoản để theo dõi đơn hàng và quản lý thông tin cá nhân.</p>
                </div>
            </div>

            <?php if ($errors): ?>
                <div class="alert warning">
                    <ul class="form-error-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="form-grid two-cols register-form">
                <div class="form-field">
                    <label for="full_name">Họ tên</label>
                    <input id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" required>
                </div>

                <div class="form-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" required>
                </div>

                <div class="form-field">
                    <label for="phone">Số điện thoại</label>
                    <input id="phone" name="phone" value="<?= e($form['phone']) ?>">
                </div>

                <!-- <div class="form-field">
                    <label for="address">Địa chỉ</label>
                    <input id="address" name="address" value="<?= e($form['address']) ?>">
                </div> -->

                <div class="form-field">
                    <label for="password">Mật khẩu</label>
                    <input id="password" name="password" type="password" required>
                </div>

                <div class="form-field">
                    <label for="password_confirm">Nhập lại mật khẩu</label>
                    <input id="password_confirm" name="password_confirm" type="password" required>
                </div>

                <div class="form-field full-width">
                    <button type="submit" class="btn-primary btn-block">Tạo tài khoản</button>
                </div>
            </form>

            <div class="auth-footnote">
                <p>Đã có tài khoản? <a href="<?= e(APP_URL) ?>/login.php">Đăng nhập ngay</a></p>
            </div>
        </section>
    </div>
</main>
<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>
