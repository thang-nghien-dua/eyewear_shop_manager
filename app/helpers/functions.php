<?php

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to(string $path): void
    {
        $url = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : APP_URL . $path;
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('add_flash')) {
    function add_flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }
}

if (!function_exists('get_flash')) {
    function get_flash(string $key): ?string
    {
        if (!isset($_SESSION['flash'][$key])) {
            return null;
        }

        $message = (string) $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }
}

if (!function_exists('flash_set')) {
    function flash_set(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }
}

if (!function_exists('flash_get')) {
    function flash_get(string $key, mixed $default = null): mixed
    {
        if (!isset($_SESSION['_flash'][$key])) {
            return $default;
        }

        $value = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('set_old_input')) {
    function set_old_input(array $data): void
    {
        $_SESSION['_old'] = $data;
    }
}

if (!function_exists('clear_old_input')) {
    function clear_old_input(): void
    {
        unset($_SESSION['_old']);
    }
}

if (!function_exists('slugify')) {
    function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));

        $map = [
            'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a',
            'â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a',
            'ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
            'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e',
            'ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
            'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
            'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o',
            'ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o',
            'ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
            'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u',
            'ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
            'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',
            'đ'=>'d',
            'À'=>'a','Á'=>'a','Ạ'=>'a','Ả'=>'a','Ã'=>'a',
            'Â'=>'a','Ầ'=>'a','Ấ'=>'a','Ậ'=>'a','Ẩ'=>'a','Ẫ'=>'a',
            'Ă'=>'a','Ằ'=>'a','Ắ'=>'a','Ặ'=>'a','Ẳ'=>'a','Ẵ'=>'a',
            'È'=>'e','É'=>'e','Ẹ'=>'e','Ẻ'=>'e','Ẽ'=>'e',
            'Ê'=>'e','Ề'=>'e','Ế'=>'e','Ệ'=>'e','Ể'=>'e','Ễ'=>'e',
            'Ì'=>'i','Í'=>'i','Ị'=>'i','Ỉ'=>'i','Ĩ'=>'i',
            'Ò'=>'o','Ó'=>'o','Ọ'=>'o','Ỏ'=>'o','Õ'=>'o',
            'Ô'=>'o','Ồ'=>'o','Ố'=>'o','Ộ'=>'o','Ổ'=>'o','Ỗ'=>'o',
            'Ơ'=>'o','Ờ'=>'o','Ớ'=>'o','Ợ'=>'o','Ở'=>'o','Ỡ'=>'o',
            'Ù'=>'u','Ú'=>'u','Ụ'=>'u','Ủ'=>'u','Ũ'=>'u',
            'Ư'=>'u','Ừ'=>'u','Ứ'=>'u','Ự'=>'u','Ử'=>'u','Ữ'=>'u',
            'Ỳ'=>'y','Ý'=>'y','Ỵ'=>'y','Ỷ'=>'y','Ỹ'=>'y',
            'Đ'=>'d',
        ];

        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'san-pham';
    }
}

if (!function_exists('format_price')) {
    function format_price(float|int|string|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 0, ',', '.') . '₫';
    }
}

if (!function_exists('format_currency')) {
    function format_currency(float|int|string|null $amount): string
    {
        return format_price($amount);
    }
}

if (!function_exists('normalize_phone')) {
    function normalize_phone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }
}

if (!function_exists('ensure_cart_session')) {
    function ensure_cart_session(): void
    {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
            return;
        }
        // Dọn dẹp các item không hợp lệ (không phải array hoặc thiếu variant_id)
        // Chỉ xóa item lỗi, KHÔNG reset toàn bộ giỏ hàng
        foreach ($_SESSION['cart'] as $key => $item) {
            if (!is_array($item) || !isset($item['variant_id'])) {
                unset($_SESSION['cart'][$key]);
            }
        }
    }
}

if (!function_exists('cart_count')) {
    function cart_count(): int
    {
        ensure_cart_session();
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += (int) ($item['quantity'] ?? 0);
        }
        return $count;
    }
}

if (!function_exists('cart_total')) {
    function cart_total(): float
    {
        ensure_cart_session();
        $total = 0.0;
        foreach ($_SESSION['cart'] as $item) {
            $total += ((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 0));
        }
        return $total;
    }
}

if (!function_exists('cart_items')) {
    function cart_items(): array
    {
        ensure_cart_session();
        return array_values($_SESSION['cart']);
    }
}

if (!function_exists('session_cart_items')) {
    function session_cart_items(): array
    {
        return cart_items();
    }
}

if (!function_exists('generate_order_code')) {
    function generate_order_code(): string
    {
        return 'LM' . date('ymdHis') . random_int(100, 999);
    }
}

if (!function_exists('remember_recent_order_code')) {
    function remember_recent_order_code(string $orderCode): void
    {
        if (!isset($_SESSION['recent_order_codes']) || !is_array($_SESSION['recent_order_codes'])) {
            $_SESSION['recent_order_codes'] = [];
        }

        $_SESSION['recent_order_codes'] = array_values(array_unique(array_merge([$orderCode], $_SESSION['recent_order_codes'])));
        $_SESSION['recent_order_codes'] = array_slice($_SESSION['recent_order_codes'], 0, 10);
    }
}

if (!function_exists('recent_order_codes')) {
    function recent_order_codes(): array
    {
        $codes = $_SESSION['recent_order_codes'] ?? [];
        return is_array($codes) ? array_values(array_filter(array_map('strval', $codes))) : [];
    }
}

if (!function_exists('grant_order_access')) {
    function grant_order_access(string $orderCode): void
    {
        if (!isset($_SESSION['order_access_codes']) || !is_array($_SESSION['order_access_codes'])) {
            $_SESSION['order_access_codes'] = [];
        }
        $_SESSION['order_access_codes'][$orderCode] = time();
    }
}

if (!function_exists('customer_can_access_order')) {
    function customer_can_access_order(string $orderCode): bool
    {
        return in_array($orderCode, recent_order_codes(), true) || isset($_SESSION['order_access_codes'][$orderCode]);
    }
}

if (!function_exists('user_role_label')) {
    function user_role_label(?string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'manager' => 'Manager',
            'sales' => 'Sales',
            'operations' => 'Operations',
            default => 'Khách hàng',
        };
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return $_SESSION['auth_user'] ?? null;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return auth_user() !== null;
    }
}

if (!function_exists('is_admin_role')) {
    function is_admin_role(?string $role): bool
    {
        return in_array($role, ['admin', 'manager', 'sales', 'operations'], true);
    }
}

if (!function_exists('is_admin_user')) {
    function is_admin_user(): bool
    {
        return is_admin_role(auth_user()['role_name'] ?? null);
    }
}

if (!function_exists('login_user')) {
    function login_user(array $user): void
    {
        $_SESSION['auth_user'] = [
            'id' => (int) $user['id'],
            'role_id' => (int) $user['role_id'],
            'role_name' => (string) ($user['role_name'] ?? ''),
            'full_name' => (string) ($user['full_name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'phone' => (string) ($user['phone'] ?? ''),
        ];

        session_regenerate_id(true);
    }
}

if (!function_exists('logout_user')) {
    function logout_user(): void
    {
        unset($_SESSION['auth_user']);
        unset($_SESSION['user']);
        unset($_SESSION['recent_order_codes']);
        unset($_SESSION['order_access']);
        unset($_SESSION['order_access_codes']);
        unset($_SESSION['last_order_id']);
        unset($_SESSION['last_order_code']);
        unset($_SESSION['intended_url']);

        session_regenerate_id(true);
    }
}

if (!function_exists('require_login')) {
    function require_login(string $redirectPath = '/login.php'): void
    {
        if (is_logged_in()) {
            return;
        }
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '/';
        add_flash('warning', 'Bạn cần đăng nhập để tiếp tục.');
        redirect_to($redirectPath);
    }
}

if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        if (is_admin_user()) {
            return;
        }
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
        add_flash('warning', 'Bạn cần đăng nhập bằng tài khoản quản trị.');
        redirect_to('/login.php');
    }
}

if (!function_exists('intended_redirect_path')) {
    function intended_redirect_path(string $fallback = '/'): string
    {
        $path = $_SESSION['intended_url'] ?? $fallback;
        unset($_SESSION['intended_url']);
        if (!is_string($path) || $path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $fallback;
        }
        return $path;
    }
}

if (!function_exists('order_status_label')) {
    function order_status_label(?string $status): string
    {
        return match ($status) {
            'pending' => 'Chờ xác nhận',
            'awaiting_stock' => 'Chờ nhập hàng',
            'checking_prescription' => 'Kiểm tra prescription',
            'confirmed' => 'Đã xác nhận',
            'processing' => 'Đang xử lý',
            'lens_processing' => 'Đang làm tròng',
            'shipping' => 'Đang giao',
            'completed' => 'Hoàn tất',
            'cancelled' => 'Đã hủy',
            'refunded' => 'Đã hoàn tiền',
            default => 'Không xác định',
        };
    }
}

if (!function_exists('order_status_class')) {
    function order_status_class(?string $status): string
    {
        return match ($status) {
            'pending' => 'status-pending',
            'awaiting_stock' => 'status-awaiting-stock',
            'checking_prescription' => 'status-checking-prescription',
            'confirmed' => 'status-confirmed',
            'processing', 'lens_processing' => 'status-processing',
            'shipping' => 'status-shipping',
            'completed' => 'status-completed',
            'cancelled', 'refunded' => 'status-cancelled',
            default => 'status-default',
        };
    }
}

if (!function_exists('payment_status_label')) {
    function payment_status_label(?string $status): string
    {
        return match ($status) {
            'unpaid' => 'Chưa thanh toán',
            'paid' => 'Đã thanh toán',
            'partially_paid' => 'Thanh toán một phần',
            'failed' => 'Thanh toán lỗi',
            'refunded' => 'Đã hoàn tiền',
            default => 'Không xác định',
        };
    }
}

if (!function_exists('payment_status_class')) {
    function payment_status_class(?string $status): string
    {
        return match ($status) {
            'paid' => 'status-completed',
            'partially_paid' => 'status-processing',
            'failed', 'refunded' => 'status-cancelled',
            'unpaid' => 'status-pending',
            default => 'status-default',
        };
    }
}

if (!function_exists('order_type_label')) {
    function order_type_label(?string $type): string
    {
        return match ($type) {
            'available' => 'Có sẵn',
            'preorder' => 'Pre-order',
            'prescription' => 'Prescription',
            'return_order' => 'Đơn hoàn',
            default => 'Không xác định',
        };
    }
}

if (!function_exists('payment_method_label')) {
    function payment_method_label(?string $method): string
    {
        return match ($method) {
            'cod' => 'Thanh toán khi nhận hàng',
            'bank_transfer' => 'Chuyển khoản ngân hàng',
            'momo' => 'Ví MoMo',
            'vnpay' => 'VNPay',
            'other' => 'Khác',
            default => 'Không xác định',
        };
    }
}

if (!function_exists('format_order_address')) {
    function format_order_address(array $order): string
    {
        $parts = [
            trim((string) ($order['shipping_address_line'] ?? '')),
            trim((string) ($order['shipping_ward'] ?? '')),
            trim((string) ($order['shipping_district'] ?? '')),
            trim((string) ($order['shipping_province'] ?? '')),
        ];
        $parts = array_values(array_filter($parts, static fn ($value) => $value !== ''));
        return implode(', ', $parts);
    }
}

if (!function_exists('first_staff_user_id')) {
    function first_staff_user_id(PDO $db): ?int
    {
        $sql = "SELECT u.id
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE r.name IN ('admin', 'manager', 'sales', 'operations')
                ORDER BY FIELD(r.name, 'admin', 'manager', 'sales', 'operations'), u.id ASC
                LIMIT 1";
        $stmt = $db->query($sql);
        $id = $stmt ? (int) $stmt->fetchColumn() : 0;
        return $id > 0 ? $id : null;
    }
}

if (!function_exists('product_status_label')) {
    function product_status_label(?string $status): string
    {
        return match ($status) {
            'active' => 'Đang bán',
            'inactive' => 'Tạm ẩn',
            'draft' => 'Nháp',
            default => 'Không xác định',
        };
    }
}

if (!function_exists('product_status_class')) {
    function product_status_class(?string $status): string
    {
        return match ($status) {
            'active' => 'status-completed',
            'inactive' => 'status-cancelled',
            'draft' => 'status-default',
            default => 'status-default',
        };
    }
}

if (!function_exists('gender_label')) {
    function gender_label(?string $gender): string
    {
        return match ($gender) {
            'male' => 'Nam',
            'female' => 'Nữ',
            'kids' => 'Trẻ em',
            'unisex' => 'Unisex',
            default => '—',
        };
    }
}

if (!function_exists('bool_label')) {
    function bool_label(mixed $value, string $trueLabel = 'Có', string $falseLabel = 'Không'): string
    {
        return (int) $value === 1 ? $trueLabel : $falseLabel;
    }
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }
}

if (!function_exists('is_active_nav')) {
    function is_active_nav(string $path, bool $prefix = false): bool
    {
        $current = current_path();
        return $prefix ? str_starts_with($current, $path) : $current === $path;
    }
}

if (!function_exists('admin_product_current_path')) {
    function admin_product_current_path(): string
    {
        return parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    }
}
