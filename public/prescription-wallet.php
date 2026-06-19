<?php
/**
 * prescription-wallet.php
 * CRUD endpoint cho ví đơn kính cá nhân của khách hàng
 * POST actions: add, update, delete, set_default
 */

require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

auth_only();
require_login();

$user = auth_user();
$db   = Database::connect();

$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));

// ──────────────────────────────────────────────────────────────
// Helper: validate & sanitize prescription fields
// ──────────────────────────────────────────────────────────────
function parse_rx_field(mixed $val, float $min, float $max): ?float
{
    if ($val === null || $val === '') return null;
    $f = (float) $val;
    return ($f >= $min && $f <= $max) ? $f : null;
}

function parse_rx_int(mixed $val, int $min, int $max): ?int
{
    if ($val === null || $val === '') return null;
    $i = (int) $val;
    return ($i >= $min && $i <= $max) ? $i : null;
}

function json_ok(mixed $data = null): void
{
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function json_err(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// ──────────────────────────────────────────────────────────────
// GET: list prescriptions
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare(
        'SELECT * FROM customer_prescriptions WHERE user_id = :uid ORDER BY is_default DESC, id DESC'
    );
    $stmt->execute(['uid' => $user['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    json_ok($rows);
}

// ──────────────────────────────────────────────────────────────
// POST actions
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_err('Method not allowed', 405);
}

// ── ADD ──────────────────────────────────────────────────────
if ($action === 'add') {
    $profileName = trim((string) ($_POST['profile_name'] ?? ''));
    if ($profileName === '') json_err('Tên hồ sơ không được trống.');

    // Giới hạn 10 hồ sơ / user
    $countStmt = $db->prepare('SELECT COUNT(*) FROM customer_prescriptions WHERE user_id = :uid');
    $countStmt->execute(['uid' => $user['id']]);
    if ((int) $countStmt->fetchColumn() >= 10) {
        json_err('Bạn đã lưu tối đa 10 hồ sơ đơn kính.');
    }

    $stmt = $db->prepare('
        INSERT INTO customer_prescriptions
            (user_id, profile_name,
             od_sphere, od_cylinder, od_axis, od_addition,
             os_sphere, os_cylinder, os_axis, os_addition,
             pd_right, pd_left, pd_distance, pd_near, note, is_default)
        VALUES
            (:uid, :pname,
             :od_sph, :od_cyl, :od_axis, :od_add,
             :os_sph, :os_cyl, :os_axis, :os_add,
             :pd_r, :pd_l, :pd_d, :pd_n, :note, 0)
    ');
    $stmt->execute([
        'uid'    => $user['id'],
        'pname'  => $profileName,
        'od_sph' => parse_rx_field($_POST['od_sphere'] ?? null, -25, 25),
        'od_cyl' => parse_rx_field($_POST['od_cylinder'] ?? null, -10, 10),
        'od_axis'=> parse_rx_int($_POST['od_axis'] ?? null, 0, 180),
        'od_add' => parse_rx_field($_POST['od_addition'] ?? null, 0, 4),
        'os_sph' => parse_rx_field($_POST['os_sphere'] ?? null, -25, 25),
        'os_cyl' => parse_rx_field($_POST['os_cylinder'] ?? null, -10, 10),
        'os_axis'=> parse_rx_int($_POST['os_axis'] ?? null, 0, 180),
        'os_add' => parse_rx_field($_POST['os_addition'] ?? null, 0, 4),
        'pd_r'   => parse_rx_field($_POST['pd_right'] ?? null, 20, 40),
        'pd_l'   => parse_rx_field($_POST['pd_left'] ?? null, 20, 40),
        'pd_d'   => parse_rx_field($_POST['pd_distance'] ?? null, 40, 80),
        'pd_n'   => parse_rx_field($_POST['pd_near'] ?? null, 40, 80),
        'note'   => trim((string) ($_POST['note'] ?? '')),
    ]);
    $newId = (int) $db->lastInsertId();

    // Fetch back the new row
    $row = $db->prepare('SELECT * FROM customer_prescriptions WHERE id = :id')->execute(['id' => $newId]);
    $row = $db->prepare('SELECT * FROM customer_prescriptions WHERE id = :id');
    $row->execute(['id' => $newId]);
    json_ok($row->fetch(PDO::FETCH_ASSOC));
}

// ── UPDATE ───────────────────────────────────────────────────
if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) json_err('ID không hợp lệ.');

    // Verify ownership
    $checkStmt = $db->prepare('SELECT id FROM customer_prescriptions WHERE id = :id AND user_id = :uid');
    $checkStmt->execute(['id' => $id, 'uid' => $user['id']]);
    if (!$checkStmt->fetchColumn()) json_err('Không tìm thấy hồ sơ.', 404);

    $profileName = trim((string) ($_POST['profile_name'] ?? ''));
    if ($profileName === '') json_err('Tên hồ sơ không được trống.');

    $stmt = $db->prepare('
        UPDATE customer_prescriptions SET
            profile_name = :pname,
            od_sphere = :od_sph, od_cylinder = :od_cyl, od_axis = :od_axis, od_addition = :od_add,
            os_sphere = :os_sph, os_cylinder = :os_cyl, os_axis = :os_axis, os_addition = :os_add,
            pd_right = :pd_r, pd_left = :pd_l, pd_distance = :pd_d, pd_near = :pd_n,
            note = :note
        WHERE id = :id AND user_id = :uid
    ');
    $stmt->execute([
        'id'     => $id,
        'uid'    => $user['id'],
        'pname'  => $profileName,
        'od_sph' => parse_rx_field($_POST['od_sphere'] ?? null, -25, 25),
        'od_cyl' => parse_rx_field($_POST['od_cylinder'] ?? null, -10, 10),
        'od_axis'=> parse_rx_int($_POST['od_axis'] ?? null, 0, 180),
        'od_add' => parse_rx_field($_POST['od_addition'] ?? null, 0, 4),
        'os_sph' => parse_rx_field($_POST['os_sphere'] ?? null, -25, 25),
        'os_cyl' => parse_rx_field($_POST['os_cylinder'] ?? null, -10, 10),
        'os_axis'=> parse_rx_int($_POST['os_axis'] ?? null, 0, 180),
        'os_add' => parse_rx_field($_POST['os_addition'] ?? null, 0, 4),
        'pd_r'   => parse_rx_field($_POST['pd_right'] ?? null, 20, 40),
        'pd_l'   => parse_rx_field($_POST['pd_left'] ?? null, 20, 40),
        'pd_d'   => parse_rx_field($_POST['pd_distance'] ?? null, 40, 80),
        'pd_n'   => parse_rx_field($_POST['pd_near'] ?? null, 40, 80),
        'note'   => trim((string) ($_POST['note'] ?? '')),
    ]);

    $row = $db->prepare('SELECT * FROM customer_prescriptions WHERE id = :id');
    $row->execute(['id' => $id]);
    json_ok($row->fetch(PDO::FETCH_ASSOC));
}

// ── DELETE ───────────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) json_err('ID không hợp lệ.');

    $stmt = $db->prepare('DELETE FROM customer_prescriptions WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $user['id']]);
    if ($stmt->rowCount() === 0) json_err('Không tìm thấy hồ sơ.', 404);
    json_ok(['deleted_id' => $id]);
}

// ── SET DEFAULT ──────────────────────────────────────────────
if ($action === 'set_default') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) json_err('ID không hợp lệ.');

    $db->prepare('UPDATE customer_prescriptions SET is_default = 0 WHERE user_id = :uid')
       ->execute(['uid' => $user['id']]);
    $db->prepare('UPDATE customer_prescriptions SET is_default = 1 WHERE id = :id AND user_id = :uid')
       ->execute(['id' => $id, 'uid' => $user['id']]);
    json_ok(['default_id' => $id]);
}

json_err('Action không hợp lệ.');
