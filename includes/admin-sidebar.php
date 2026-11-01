<?php $current = basename($_SERVER['PHP_SELF'] ?? ''); ?>
<aside class="admin-sidebar" id="adminSidebar">
    <a class="brand-mark text-white" href="<?= url('admin/index.php') ?>"><span>EC</span> <?= e(store_name()) ?></a>
    <nav class="admin-nav">
        <?php
        $links = [
            ['index.php','speedometer2','Dashboard'], ['products.php','box-seam','Produk'],
            ['categories.php','grid','Kategori'], ['stock.php','boxes','Manajemen Stok'],
            ['orders.php','bag-check','Pesanan'], ['payments.php','credit-card','Pembayaran'],
            ['customers.php','people','Pelanggan'], ['reports.php','bar-chart','Laporan Penjualan'],
            ['settings.php','gear','Pengaturan Toko'],
        ];
        foreach ($links as [$file,$icon,$label]): ?>
            <a class="<?= $current === $file ? 'active' : '' ?>" href="<?= url('admin/' . $file) ?>"><i class="bi bi-<?= $icon ?>"></i><span><?= $label ?></span></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer"><a href="<?= url() ?>"><i class="bi bi-shop"></i> Lihat Toko</a><form action="<?= url('logout.php') ?>" method="post"><?= csrf_field() ?><button><i class="bi bi-box-arrow-left"></i> Keluar</button></form></div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
