<nav class="navbar navbar-expand-lg navbar-light sticky-top store-navbar">
    <div class="container">
        <a class="navbar-brand brand-mark" href="<?= url() ?>"><span>EC</span> <?= e(store_name()) ?></a>
        <div class="d-flex align-items-center gap-2 order-lg-3">
            <a class="nav-icon position-relative" href="<?= url('cart.php') ?>" aria-label="Keranjang">
                <i class="bi bi-bag"></i>
                <?php if (cart_count() > 0): ?><span class="cart-badge"><?= cart_count() ?></span><?php endif; ?>
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
        </div>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= url() ?>">Beranda</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= url('shop.php') ?>">Belanja</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= url('shop.php#categories') ?>">Kategori</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= url('index.php#about') ?>">Tentang Kami</a></li>
            </ul>
            <div class="d-flex align-items-lg-center gap-2 pb-3 pb-lg-0 me-lg-3">
                <?php if (!is_logged_in()): ?>
                    <a class="btn btn-light-green" href="<?= url('login.php') ?>">Masuk</a>
                    <a class="btn btn-primary" href="<?= url('register.php') ?>">Daftar</a>
                <?php else: ?>
                    <div class="dropdown">
                        <button class="btn btn-light-green dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person-circle me-1"></i><?= e(current_user()['name']) ?></button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <?php if (is_admin()): ?><li><a class="dropdown-item" href="<?= url('admin/index.php') ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard Admin</a></li><?php else: ?>
                            <li><a class="dropdown-item" href="<?= url('orders.php') ?>"><i class="bi bi-receipt me-2"></i>Pesanan Saya</a></li>
                            <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="bi bi-person me-2"></i>Profil</a></li><?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><form action="<?= url('logout.php') ?>" method="post"><?= csrf_field() ?><button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button></form></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
