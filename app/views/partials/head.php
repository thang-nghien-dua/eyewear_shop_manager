<?php
$pageTitle = $pageTitle ?? APP_NAME;
$pageDescription = $pageDescription ?? 'LUMINA Eyewear Store';
$stylePath = PUBLIC_PATH . '/assets/css/style.css';
$styleVersion = is_file($stylePath) ? (string) filemtime($stylePath) : (string) time();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/vendor/flaticon-uicons/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/style.css?v=<?= e($styleVersion) ?>">
</head>
<body>
