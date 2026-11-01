</main>
<?php $footerAddress = setting('store_address', ''); $footerPhone = setting('store_phone', ''); ?>
<footer class="site-footer mt-5">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-5"><div class="brand-mark text-white mb-3"><span>EC</span> <?= e(store_name()) ?></div><p class="text-white-50 mb-0"><?= e(setting('store_description', 'Belanja kebutuhan pilihan dengan proses mudah, aman, dan pelayanan terpercaya.')) ?></p></div>
            <div class="col-6 col-lg-2"><h6>Navigasi</h6><a href="<?= url() ?>">Beranda</a><a href="<?= url('shop.php') ?>">Belanja</a><a href="<?= url('orders.php') ?>">Pesanan</a></div>
            <div class="col-6 col-lg-2"><h6>Bantuan</h6><a href="<?= url('index.php#about') ?>">Tentang</a><a href="<?= url('cart.php') ?>">Keranjang</a><a href="<?= url('profile.php') ?>">Akun</a></div>
            <div class="col-lg-3"><h6>Hubungi Kami</h6><?php if ($footerAddress): ?><p class="text-white-50 small mb-1"><i class="bi bi-geo-alt me-2"></i><?= e($footerAddress) ?></p><?php endif; ?><?php if ($footerPhone): ?><p class="text-white-50 small"><i class="bi bi-telephone me-2"></i><?= e($footerPhone) ?></p><?php endif; ?><?php if (!$footerAddress && !$footerPhone): ?><p class="text-white-50 small">Informasi kontak dapat diisi melalui panel admin.</p><?php endif; ?></div>
        </div>
    </div>
    <div class="footer-bottom text-center py-3">&copy; <?= date('Y') ?> <?= e(store_name()) ?>. Semua hak dilindungi.</div>
</footer>
<?php if ($whatsappUrl = whatsapp_url()): ?><a class="whatsapp-float" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" aria-label="Tanya melalui WhatsApp"><i class="bi bi-whatsapp"></i><span>Tanya toko</span></a><?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($useLeaflet)): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><?php endif; ?>
<script src="<?= asset('js/main.js') ?>"></script>
<?php if (!empty($extraJs)): ?><script src="<?= asset('js/' . $extraJs) ?>"></script><?php endif; ?>
</body>
</html>
