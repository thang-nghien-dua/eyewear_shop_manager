<?php
$pageTitle = $pageTitle ?? ('Admin - ' . APP_NAME);
$pageDescription = $pageDescription ?? 'Bảng điều khiển quản trị ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/vendor/flaticon-uicons/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/style.css">
</head>
<body class="admin-body">
