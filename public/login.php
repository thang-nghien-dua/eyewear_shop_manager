<?php

require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';

// echo 'logout ok'; exit;

if (is_logged_in()) {
    $target = is_admin_user() ? '/admin/' : '/';
    redirect_to($target);
}

$db = Database::connect();
$errors = [];
$form = [
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['email'] = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($form['email'] === '') {
        $errors[] = 'Vui lòng nhập email.';
    }

    if ($password === '') {
        $errors[] = 'Vui lòng nhập mật khẩu.';
    }

    if (!$errors) {
        $stmt = $db->prepare(
            'SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $form['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Email hoặc mật khẩu không đúng.';
        } elseif ($user['status'] !== 'active') {
            $errors[] = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ admin@gmail.com để được hỗ trợ.';
        } else {
            login_user($user);
            add_flash('success', 'Đăng nhập thành công.');

            $fallback = is_admin_role($user['role_name']) ? '/admin/' : '/profile.php';
            $path = intended_redirect_path($fallback);

            // chặn customer vào URL admin nếu không phải admin
            if (!is_admin_role($user['role_name']) && str_starts_with($path, '/admin')) {
                $path = '/profile.php';
            }

            redirect_to($path);
        }
    }
}

$pageTitle = 'Đăng nhập';
require_once BASE_PATH . '/app/views/partials/head.php';
require_once BASE_PATH . '/app/views/partials/header.php';
?>
<main class="page-section simple-page">
    <div class="container">
        <?php if ($message = get_flash('warning')): ?>
            <div class="alert warning"><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($message = get_flash('success')): ?>
            <div class="alert success"><?= e($message) ?></div>
        <?php endif; ?>

        <section class="auth-wrap">
            <div class="section-heading-row compact">
                <div>
                    <h1>Đăng nhập</h1>
                    <p>Dùng cho cả khách hàng và tài khoản quản trị.</p>
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

            <form method="post" class="auth-form">
                <div class="form-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" required>
                </div>

                <div class="form-field">
                    <label for="password">Mật khẩu</label>
                    <input id="password" name="password" type="password" required>
                </div>

                <button type="submit">Đăng nhập</button>
            </form>

            <div class="auth-footnote">
                <p>Chưa có tài khoản? <a href="<?= e(APP_URL) ?>/register.php">Đăng ký khách hàng</a></p>
                <p class="muted-text">Admin demo: <strong>Admin1@gmail.com</strong> / <strong>123456</strong></p>
                <p class="muted-text">Manager demo: <strong>Manager1@gmail.com</strong> / <strong>123456</strong></p>
                <p class="muted-text">Operation demo: <strong>Operation1@gmail.com</strong> / <strong>123456</strong></p>
                <p class="muted-text">Sale demo: <strong>Sale1@gmail.com</strong> / <strong>123456</strong></p>
                <p class="muted-text">customer demo: <strong>Customer@gmail.com</strong> / <strong>123456</strong></p>
            </div>
        </section>
    </div>
</main>
<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>
