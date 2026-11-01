<?php
require_once __DIR__ . '/config/app.php';
require_customer();

$cartId = get_or_create_cart((int) current_user()['id']);
$stmt = db()->prepare('SELECT ci.quantity,p.*,(ci.quantity*p.price) line_total FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.cart_id=? AND p.is_active=1');
$stmt->execute([$cartId]);
$items = $stmt->fetchAll();
if (!$items) {
    flash('warning', 'Keranjang Anda kosong.');
    redirect('cart.php');
}

$subtotal = array_sum(array_column($items, 'line_total'));
$shipping = max(0, (float) setting('shipping_cost', '0'));
$total = $subtotal + $shipping;

$addressStmt = db()->prepare('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC,id DESC LIMIT 1');
$addressStmt->execute([current_user()['id']]);
$address = $addressStmt->fetch() ?: [];
$banks = db()->query('SELECT * FROM bank_accounts WHERE is_active=1 ORDER BY bank_name')->fetchAll();

$storeLatitudeValue = setting('store_latitude', '');
$storeLongitudeValue = setting('store_longitude', '');
$storeLocationReady = is_numeric($storeLatitudeValue)
    && is_numeric($storeLongitudeValue)
    && valid_coordinate((float) $storeLatitudeValue, (float) $storeLongitudeValue);
$deliveryRadius = 10.0;

$token = bin2hex(random_bytes(24));
$_SESSION['checkout_token'] = $token;
$pageTitle = 'Checkout';
$extraJs = 'checkout.js';
$useLeaflet = true;
require APP_ROOT . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <div class="section-kicker">Checkout aman</div>
        <h1>Selesaikan pesanan</h1>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <form action="<?= url('actions/checkout-process.php') ?>" method="post" id="checkoutForm" data-store-lat="<?= $storeLocationReady ? e($storeLatitudeValue) : '' ?>" data-store-lng="<?= $storeLocationReady ? e($storeLongitudeValue) : '' ?>" data-radius-km="<?= e((string) $deliveryRadius) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="idempotency_key" value="<?= e($token) ?>">
            <input type="hidden" name="delivery_latitude" id="deliveryLatitude" value="<?= e($address['latitude'] ?? '') ?>">
            <input type="hidden" name="delivery_longitude" id="deliveryLongitude" value="<?= e($address['longitude'] ?? '') ?>">
            <input type="hidden" name="fulfillment_method" id="fulfillmentMethod" value="">

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="content-card mb-4">
                        <h4 class="fw-bold mb-4"><span class="badge rounded-pill bg-primary me-2">1</span>Alamat pengiriman</h4>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Nama penerima</label><input class="form-control" name="recipient_name" value="<?= e($address['recipient_name'] ?? current_user()['name']) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Nomor HP</label><input class="form-control" name="phone" value="<?= e($address['phone'] ?? current_user()['phone']) ?>" required></div>
                            <div class="col-12"><label class="form-label">Alamat lengkap</label><textarea class="form-control" name="address" rows="3" required><?= e($address['address'] ?? '') ?></textarea></div>
                            <div class="col-md-6"><label class="form-label">Kecamatan</label><input class="form-control" name="district" value="<?= e($address['district'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Kota/Kabupaten</label><input class="form-control" name="city" value="<?= e($address['city'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Provinsi</label><input class="form-control" name="province" value="<?= e($address['province'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Kode pos</label><input class="form-control" name="postal_code" value="<?= e($address['postal_code'] ?? '') ?>" maxlength="10" required></div>
                            <div class="col-12"><label class="form-label">Catatan pesanan <span class="text-muted">(opsional)</span></label><textarea class="form-control" name="notes" rows="2"></textarea></div>
                        </div>
                    </div>

                    <div class="content-card mb-4">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <div><h4 class="fw-bold mb-1"><span class="badge rounded-pill bg-primary me-2">2</span>Titik pembeli</h4><p class="text-muted mb-0 small">Dalam radius 10 km tersedia COD antar dan transfer antar. Di luar radius hanya tersedia COD di toko.</p></div>
                            <button class="btn btn-light-green ms-md-auto" type="button" id="useCurrentLocation"><i class="bi bi-crosshair me-1"></i>Gunakan lokasi saya</button>
                        </div>
                        <?php if (!$storeLocationReady): ?>
                            <div class="alert alert-warning mb-0">Titik lokasi toko belum diatur oleh pemilik. Checkout sementara tidak dapat dilakukan.</div>
                        <?php else: ?>
                            <div id="deliveryMap" class="location-map"></div>
                            <div id="locationStatus" class="location-status bg-light mt-3">Klik peta atau gunakan lokasi perangkat untuk menentukan layanan pesanan.</div>
                        <?php endif; ?>
                    </div>

                    <div class="content-card">
                        <h4 class="fw-bold mb-4"><span class="badge rounded-pill bg-primary me-2">3</span>Metode pembayaran</h4>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="payment-option h-100"><div class="d-flex gap-3"><input class="form-check-input" id="codPayment" type="radio" name="payment_method" value="cod" checked><div><strong id="codTitle"><i class="bi bi-cash-stack me-1"></i> COD Antar</strong><p class="small text-muted mb-0 mt-1" id="codDescription">Bayar saat pesanan diantar ke alamat.</p></div></div></label></div>
                            <div class="col-md-6"><label class="payment-option h-100"><div class="d-flex gap-3"><input class="form-check-input" id="transferPayment" type="radio" name="payment_method" value="transfer" data-bank-ready="<?= $banks ? '1' : '0' ?>" <?= !$banks ? 'disabled' : '' ?>><div><strong><i class="bi bi-bank me-1"></i> Transfer Antar</strong><p class="small text-muted mb-0 mt-1" id="transferDescription">Transfer manual, lalu pesanan diantar setelah diverifikasi.</p></div></div></label></div>
                        </div>
                        <div id="transferFields" class="d-none mt-4">
                            <label class="form-label fw-semibold">Pilih rekening tujuan</label>
                            <?php if (!$banks): ?>
                                <div class="alert alert-warning">Belum ada rekening transfer aktif.</div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($banks as $index => $bank): ?>
                                        <div class="col-md-6"><label class="payment-option"><div class="d-flex gap-2"><input class="form-check-input" type="radio" name="bank_account_id" value="<?= (int) $bank['id'] ?>" <?= $index === 0 ? 'checked' : '' ?>><div><strong><?= e($bank['bank_name']) ?></strong><div><?= e($bank['account_number']) ?></div><small class="text-muted">a.n. <?= e($bank['account_holder']) ?></small></div></div></label></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="summary-card">
                        <h5 class="fw-bold mb-3">Ringkasan pesanan</h5>
                        <div class="vstack gap-3 mb-3">
                            <?php foreach ($items as $item): ?>
                                <div class="d-flex gap-3"><img src="<?= e(product_image($item['image'])) ?>" class="rounded-3" style="width:54px;height:54px;object-fit:cover" alt=""><div class="flex-grow-1"><div class="fw-semibold small"><?= e($item['name']) ?></div><small class="text-muted"><?= (int) $item['quantity'] ?> × <?= rupiah($item['price']) ?></small></div><strong class="small"><?= rupiah($item['line_total']) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><span><?= rupiah($subtotal) ?></span></div>
                        <div class="d-flex justify-content-between mb-3"><span class="text-muted">Ongkir</span><span><?= $shipping > 0 ? rupiah($shipping) : 'Gratis' ?></span></div>
                        <div class="d-flex justify-content-between fs-5 pt-3 border-top"><strong>Total</strong><strong class="text-primary"><?= rupiah($total) ?></strong></div>
                        <button class="btn btn-primary w-100 py-3 mt-4" id="checkoutButton" <?= !$storeLocationReady ? 'disabled' : '' ?>>Buat Pesanan</button>
                        <small class="text-muted d-block text-center mt-3"><i class="bi bi-shield-lock me-1"></i>Harga, stok, jarak, dan jenis layanan diperiksa ulang oleh sistem.</small>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
