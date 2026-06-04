<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();

$orderId = (int) ($_POST['order_id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
$newStatus = trim((string) ($_POST['status'] ?? $_POST['new_status'] ?? ''));
$note = trim((string) ($_POST['note'] ?? $_POST['admin_note'] ?? $_POST['status_note'] ?? ''));

function redirect_order_detail(int $orderId, ?string $error = null): void
{
    if ($orderId <= 0) {
        redirect_to('/admin/orders/index.php');
    }

    $url = '/admin/orders/detail.php?id=' . $orderId;
    if ($error !== null && $error !== '') {
        $url .= '&error=' . urlencode($error);
    }

    redirect_to($url);
}

function column_is_integer(array $columns, string $column): bool
{
    if (!isset($columns[$column])) {
        return false;
    }

    return (bool) preg_match('/\b(tinyint|smallint|mediumint|int|bigint)\b/i', (string) $columns[$column]);
}

function add_if_column_exists(array $columns, array &$data, string $column, mixed $value): void
{
    if (array_key_exists($column, $columns)) {
        $data[$column] = $value;
    }
}

try {
    if ($orderId <= 0) {
        throw new RuntimeException('Thiếu mã đơn hàng cần cập nhật.');
    }

    if ($newStatus === 'canceled') {
        $newStatus = 'cancelled';
    }

    $allowedStatuses = [
        'pending',
        'awaiting_stock',
        'checking_prescription',
        'confirmed',
        'processing',
        'shipping',
        'completed',
        'cancelled',
    ];

    if (!in_array($newStatus, $allowedStatuses, true)) {
        throw new RuntimeException('Trạng thái đơn hàng không hợp lệ.');
    }

    $orderStmt = $db->prepare('SELECT id, order_code, status FROM orders WHERE id = :order_id LIMIT 1');
    $orderStmt->execute([
        ':order_id' => $orderId,
    ]);
    $order = $orderStmt->fetch();

    if (!$order) {
        throw new RuntimeException('Không tìm thấy đơn hàng cần cập nhật.');
    }

    $oldStatus = (string) ($order['status'] ?? '');

    if ($oldStatus === $newStatus) {
        add_flash('info', 'Đơn hàng đã ở trạng thái này.');
        redirect_order_detail($orderId);
    }

    $currentUser = auth_user();
    $actorId = (int) ($currentUser['id'] ?? 0);
    $actorName = (string) ($currentUser['full_name'] ?? $currentUser['email'] ?? 'Admin');

    $db->beginTransaction();

    $updateStmt = $db->prepare('
        UPDATE orders
        SET status = :new_status,
            updated_at = NOW()
        WHERE id = :order_id
        LIMIT 1
    ');
    $updateStmt->execute([
        ':new_status' => $newStatus,
        ':order_id' => $orderId,
    ]);

    // Ghi log trạng thái nếu bảng order_status_logs tồn tại.
    // Viết theo kiểu tự dò cột để khớp với nhiều phiên bản schema khác nhau.
    try {
        $columnRows = $db->query('SHOW COLUMNS FROM order_status_logs')->fetchAll();
        $columns = [];
        foreach ($columnRows as $columnRow) {
            $field = (string) ($columnRow['Field'] ?? '');
            if ($field !== '') {
                $columns[$field] = (string) ($columnRow['Type'] ?? '');
            }
        }

        if ($columns !== [] && array_key_exists('order_id', $columns)) {
            $logData = [];
            $rawValues = [];

            $logData['order_id'] = $orderId;

            add_if_column_exists($columns, $logData, 'old_status', $oldStatus);
            add_if_column_exists($columns, $logData, 'from_status', $oldStatus);
            add_if_column_exists($columns, $logData, 'previous_status', $oldStatus);

            add_if_column_exists($columns, $logData, 'new_status', $newStatus);
            add_if_column_exists($columns, $logData, 'to_status', $newStatus);
            add_if_column_exists($columns, $logData, 'status', $newStatus);

            add_if_column_exists($columns, $logData, 'note', $note);
            add_if_column_exists($columns, $logData, 'admin_note', $note);
            add_if_column_exists($columns, $logData, 'description', $note);
            add_if_column_exists($columns, $logData, 'message', $note);

            foreach (['changed_by', 'created_by', 'admin_id', 'user_id'] as $actorColumn) {
                if (array_key_exists($actorColumn, $columns)) {
                    $logData[$actorColumn] = column_is_integer($columns, $actorColumn) ? $actorId : $actorName;
                }
            }

            foreach (['created_at', 'changed_at'] as $timeColumn) {
                if (array_key_exists($timeColumn, $columns)) {
                    $rawValues[$timeColumn] = 'NOW()';
                }
            }

            $insertColumns = array_merge(array_keys($logData), array_keys($rawValues));
            $placeholders = [];
            $params = [];

            foreach ($logData as $column => $value) {
                $paramName = ':' . $column;
                $placeholders[] = $paramName;
                $params[$paramName] = $value;
            }

            foreach ($rawValues as $column => $rawSql) {
                $placeholders[] = $rawSql;
            }

            $quotedColumns = array_map(static fn ($column) => '`' . str_replace('`', '``', $column) . '`', $insertColumns);

            $logSql = 'INSERT INTO order_status_logs (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $logStmt = $db->prepare($logSql);
            $logStmt->execute($params);
        }
    } catch (Throwable $logException) {
        // Nếu lỗi log thì rollback để admin thấy lỗi rõ, tránh trạng thái đổi mà lịch sử không ghi.
        throw $logException;
    }

    $db->commit();

    add_flash('success', 'Đã cập nhật trạng thái đơn hàng.');
    redirect_order_detail($orderId);
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    redirect_order_detail($orderId, $exception->getMessage());
}
