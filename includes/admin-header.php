<?php
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> | Admin <?= e(store_name()) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <?php if (!empty($useLeaflet)): ?><link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet"><?php endif; ?>
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet"><link href="<?= asset('css/admin.css') ?>" rel="stylesheet">
</head>
<body class="admin-body">
<?php require APP_ROOT . '/includes/admin-sidebar.php'; ?>
<div class="admin-main">
    <header class="admin-topbar"><button class="btn d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button><div><small class="text-muted">Panel Pemilik Toko</small><h5 class="mb-0"><?= e($pageTitle) ?></h5></div><div class="ms-auto dropdown"><button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person-circle me-1"></i><?= e(current_user()['name']) ?></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="<?= url() ?>">Lihat Toko</a></li><li><form action="<?= url('logout.php') ?>" method="post"><?= csrf_field() ?><button class="dropdown-item text-danger">Keluar</button></form></li></ul></div></header>
    <div class="admin-content">
    <?php foreach (pull_flashes() as $item): ?><div class="alert alert-<?= e($item['type']) ?> alert-dismissible fade show"><i class="bi bi-info-circle me-2"></i><?= e($item['message']) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endforeach; ?>
