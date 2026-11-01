<?php
$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> | <?= e(store_name()) ?></title>
    <meta name="description" content="<?= e(store_name()) ?> — belanja mudah, nyaman, dan terpercaya.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <?php if (!empty($useLeaflet)): ?><link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet"><?php endif; ?>
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
    <?php if (!empty($extraCss)): ?><link href="<?= asset('css/' . $extraCss) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<?php require APP_ROOT . '/includes/navbar.php'; ?>
<main>
<?php foreach (pull_flashes() as $item): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3 mt-5 pt-4">
        <div class="toast show border-0 shadow" role="alert" data-bs-delay="5000">
            <div class="toast-header text-bg-<?= e($item['type']) ?> border-0">
                <i class="bi bi-bell me-2"></i><strong class="me-auto"><?= e(store_name()) ?></strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body"><?= e($item['message']) ?></div>
        </div>
    </div>
<?php endforeach; ?>
