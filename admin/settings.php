<?php
require_once __DIR__ . '/../config/app.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'admin_account') {
            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');

            $stmt = db()->prepare("SELECT password FROM users WHERE id=? AND role='admin'");
            $stmt->execute([current_user()['id']]);
            $passwordHash = $stmt->fetchColumn();
            if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) {
                throw new RuntimeException('Password saat ini tidak sesuai.');
            }
            if (mb_strlen($name) < 3) {
                throw new RuntimeException('Nama pemilik minimal 3 karakter.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Alamat email login tidak valid.');
            }
            if ($newPassword !== '' && strlen($newPassword) < 8) {
                throw new RuntimeException('Password baru minimal 8 karakter.');
            }

            $emailCheck = db()->prepare('SELECT id FROM users WHERE email=? AND id<>?');
            $emailCheck->execute([$email, current_user()['id']]);
            if ($emailCheck->fetch()) {
                throw new RuntimeException('Email sudah digunakan akun lain.');
            }

            if ($newPassword !== '') {
                $stmt = db()->prepare('UPDATE users SET name=?,email=?,password=? WHERE id=?');
                $stmt->execute([$name, $email, password_hash($newPassword, PASSWORD_DEFAULT), current_user()['id']]);
            } else {
                $stmt = db()->prepare('UPDATE users SET name=?,email=? WHERE id=?');
                $stmt->execute([$name, $email, current_user()['id']]);
            }
            flash('success', 'Akun pemilik berhasil diperbarui.');
        } elseif ($action === 'store') {
            $name = trim($_POST['store_name'] ?? '');
            $storeEmail = strtolower(trim($_POST['store_email'] ?? ''));
            $latitude = trim($_POST['store_latitude'] ?? '');
            $longitude = trim($_POST['store_longitude'] ?? '');
            $appUrl = rtrim(trim($_POST['app_url'] ?? ''), '/');

            if (mb_strlen($name) < 2) {
                throw new RuntimeException('Nama toko minimal 2 karakter.');
            }
            if ($storeEmail !== '' && !filter_var($storeEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Email notifikasi toko tidak valid.');
            }
            if (!is_numeric($latitude) || !is_numeric($longitude) || !valid_coordinate((float) $latitude, (float) $longitude)) {
                throw new RuntimeException('Pilih titik lokasi toko pada peta.');
            }
            if ($appUrl !== '' && (!filter_var($appUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $appUrl))) {
                throw new RuntimeException('URL website harus diawali http:// atau https://.');
            }

            $values = [
                'store_name' => $name,
                'store_description' => trim($_POST['store_description'] ?? ''),
                'store_address' => trim($_POST['store_address'] ?? ''),
                'store_phone' => trim($_POST['store_phone'] ?? ''),
                'store_email' => $storeEmail,
                'whatsapp_number' => trim($_POST['whatsapp_number'] ?? ''),
                'shipping_cost' => (string) max(0, (float) ($_POST['shipping_cost'] ?? 0)),
                'store_latitude' => (string) (float) $latitude,
                'store_longitude' => (string) (float) $longitude,
                'delivery_radius_km' => '10',
                'app_url' => $appUrl,
            ];
            $stmt = db()->prepare('INSERT INTO store_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            foreach ($values as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            flash('success', 'Informasi dan titik lokasi toko berhasil diperbarui.');
        } elseif ($action === 'email_settings') {
            $emailEnabled = isset($_POST['email_enabled']);
            $token = trim((string) ($_POST['mailtrap_api_token'] ?? ''));
            $fromEmail = strtolower(trim($_POST['smtp_from_email'] ?? ''));
            $fromName = trim($_POST['smtp_from_name'] ?? '');

            if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Email pengirim tidak valid.');
            }
            $savedToken = trim(setting('smtp_password', ''));
            $savedTransport = setting('email_transport', '');
            if ($emailEnabled && $token === '' && ($savedToken === '' || $savedTransport !== 'mailtrap_api')) {
                throw new RuntimeException('API Token Mailtrap wajib diisi sebelum notifikasi diaktifkan.');
            }

            $values = [
                'email_enabled' => $emailEnabled ? '1' : '0',
                'email_transport' => 'mailtrap_api',
                'mailtrap_api_url' => 'https://send.api.mailtrap.io/api/send',
                'smtp_from_email' => $fromEmail,
                'smtp_from_name' => $fromName !== '' ? $fromName : store_name(),
            ];
            if ($token !== '') {
                $values['smtp_password'] = $token;
            }

            $stmt = db()->prepare('INSERT INTO store_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            foreach ($values as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            flash('success', 'Pengaturan Mailtrap Email API berhasil disimpan.');
        } elseif ($action === 'email_test') {
            $destination = notification_admin_email();
            if (!filter_var($destination, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Isi email toko terlebih dahulu sebagai tujuan pengujian.');
            }
            $content = '<h2 style="margin-top:0">Email berhasil terhubung</h2><p>Mailtrap Email API ' . e(store_name()) . ' sudah dapat digunakan untuk mengirim notifikasi pesanan.</p>';
            send_store_email($destination, 'Pemilik Toko', 'Tes notifikasi ' . store_name(), $content, true);
            flash('success', 'Email percobaan berhasil dikirim ke ' . $destination . '.');
        } elseif ($action === 'bank_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $bankName = trim($_POST['bank_name'] ?? '');
            $accountNumber = trim($_POST['account_number'] ?? '');
            $accountHolder = trim($_POST['account_holder'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if ($bankName === '' || $accountNumber === '' || $accountHolder === '') {
                throw new RuntimeException('Lengkapi data rekening.');
            }
            if ($id) {
                $stmt = db()->prepare('UPDATE bank_accounts SET bank_name=?,account_number=?,account_holder=?,is_active=? WHERE id=?');
                $stmt->execute([$bankName, $accountNumber, $accountHolder, $isActive, $id]);
            } else {
                $stmt = db()->prepare('INSERT INTO bank_accounts(bank_name,account_number,account_holder,is_active) VALUES(?,?,?,?)');
                $stmt->execute([$bankName, $accountNumber, $accountHolder, $isActive]);
            }
            flash('success', 'Rekening bank berhasil disimpan.');
        } elseif ($action === 'bank_delete') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM bank_accounts WHERE id=?')->execute([$id]);
            flash('success', 'Rekening bank berhasil dihapus.');
        }
    } catch (Throwable $exception) {
        $message = $action === 'bank_delete'
            ? 'Rekening sudah digunakan dalam transaksi. Nonaktifkan rekening tersebut.'
            : $exception->getMessage();
        flash('danger', $message);
    }
    redirect('admin/settings.php');
}

$settings = db()->query('SELECT setting_key,setting_value FROM store_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$banks = db()->query('SELECT * FROM bank_accounts ORDER BY is_active DESC,bank_name')->fetchAll();
$editBank = null;
if (isset($_GET['bank'])) {
    foreach ($banks as $bank) {
        if ((int) $bank['id'] === (int) $_GET['bank']) {
            $editBank = $bank;
        }
    }
}

$useLeaflet = true;
$pageTitle = 'Pengaturan Toko';
$pageScript = <<<'JS'
const latitudeInput = document.getElementById('storeLatitude');
const longitudeInput = document.getElementById('storeLongitude');
const mapElement = document.getElementById('storeLocationMap');
const currentButton = document.getElementById('storeCurrentLocation');

if (mapElement && window.L) {
    const hasLocation = latitudeInput.value !== '' && longitudeInput.value !== '';
    const initialLatitude = hasLocation ? Number(latitudeInput.value) : -2.5489;
    const initialLongitude = hasLocation ? Number(longitudeInput.value) : 118.0149;
    const map = L.map(mapElement).setView([initialLatitude, initialLongitude], hasLocation ? 14 : 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
    let marker = null;
    let radiusCircle = null;

    const setStorePoint = (latitude, longitude, centerMap = false) => {
        latitudeInput.value = latitude.toFixed(7);
        longitudeInput.value = longitude.toFixed(7);
        if (!marker) {
            marker = L.marker([latitude, longitude], {draggable: true}).addTo(map).bindPopup('Titik toko').openPopup();
            marker.on('dragend', (event) => {
                const point = event.target.getLatLng();
                setStorePoint(point.lat, point.lng);
            });
        } else {
            marker.setLatLng([latitude, longitude]);
        }
        if (!radiusCircle) {
            radiusCircle = L.circle([latitude, longitude], {radius: 10000, color: '#047857', fillColor: '#10b981', fillOpacity: 0.1}).addTo(map);
        } else {
            radiusCircle.setLatLng([latitude, longitude]);
        }
        if (centerMap) map.setView([latitude, longitude], 14);
    };

    if (hasLocation) setStorePoint(initialLatitude, initialLongitude);
    map.on('click', (event) => setStorePoint(event.latlng.lat, event.latlng.lng));
    currentButton.addEventListener('click', () => {
        if (!navigator.geolocation) {
            alert('Browser tidak mendukung pembacaan lokasi. Klik titik toko langsung pada peta.');
            return;
        }
        currentButton.disabled = true;
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setStorePoint(position.coords.latitude, position.coords.longitude, true);
                currentButton.disabled = false;
            },
            () => {
                alert('Lokasi tidak dapat dibaca. Izinkan akses lokasi atau klik titik toko langsung pada peta.');
                currentButton.disabled = false;
            },
            {enableHighAccuracy: true, timeout: 15000}
        );
    });
}
JS;
require APP_ROOT . '/includes/admin-header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="admin-card">
            <div class="admin-card-header"><strong>Informasi dan wilayah pengantaran</strong><span class="badge text-bg-success">Radius tetap 10 km</span></div>
            <div class="admin-card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="store">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Nama toko</label><input class="form-control" name="store_name" value="<?= e($settings['store_name'] ?? APP_NAME) ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Email penerima notifikasi</label><input class="form-control" type="email" name="store_email" value="<?= e($settings['store_email'] ?? '') ?>"><small class="text-muted">Pesanan dan bukti transfer baru dikirim ke alamat ini.</small></div>
                        <div class="col-md-4"><label class="form-label">Nomor telepon</label><input class="form-control" name="store_phone" value="<?= e($settings['store_phone'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Nomor WhatsApp</label><input class="form-control" name="whatsapp_number" value="<?= e($settings['whatsapp_number'] ?? '') ?>" placeholder="Contoh: 081234567890"></div>
                        <div class="col-md-4"><label class="form-label">Ongkir tetap (Rp)</label><input class="form-control" type="number" min="0" name="shipping_cost" value="<?= e($settings['shipping_cost'] ?? 0) ?>"></div>
                        <div class="col-12"><label class="form-label">Alamat toko</label><textarea class="form-control" name="store_address" rows="2"><?= e($settings['store_address'] ?? '') ?></textarea></div>
                        <div class="col-12"><label class="form-label">Deskripsi toko</label><textarea class="form-control" name="store_description" rows="3"><?= e($settings['store_description'] ?? '') ?></textarea></div>
                        <div class="col-12"><label class="form-label">URL website</label><input class="form-control" type="url" name="app_url" value="<?= e($settings['app_url'] ?? 'http://localhost/eko-ceper') ?>" placeholder="https://tokosaya.com"><small class="text-muted">Dipakai pada tombol detail pesanan di dalam email.</small></div>

                        <div class="col-12 mt-4">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2"><div><label class="form-label fw-bold mb-0">Titik lokasi toko</label><div class="small text-muted">Klik peta, geser penanda, atau gunakan lokasi perangkat. Lingkaran menunjukkan batas 10 km.</div></div><button class="btn btn-light-green ms-md-auto" type="button" id="storeCurrentLocation"><i class="bi bi-crosshair me-1"></i>Gunakan lokasi saya</button></div>
                            <div id="storeLocationMap" class="location-map"></div>
                        </div>
                        <div class="col-md-6"><label class="form-label">Latitude</label><input class="form-control" id="storeLatitude" name="store_latitude" value="<?= e($settings['store_latitude'] ?? '') ?>" readonly required></div>
                        <div class="col-md-6"><label class="form-label">Longitude</label><input class="form-control" id="storeLongitude" name="store_longitude" value="<?= e($settings['store_longitude'] ?? '') ?>" readonly required></div>
                        <div class="col-12"><button class="btn btn-primary">Simpan Informasi dan Lokasi</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="admin-card">
            <div class="admin-card-header"><strong>Notifikasi Mailtrap Email API</strong><span class="badge text-bg-<?= ($settings['email_enabled'] ?? '0') === '1' ? 'success' : 'secondary' ?>"><?= ($settings['email_enabled'] ?? '0') === '1' ? 'Aktif' : 'Nonaktif' ?></span></div>
            <div class="admin-card-body">
                <div class="alert alert-info small mb-3"><strong>Menggunakan HTTPS port 443.</strong> Tidak memakai port SMTP 587 atau 2525. Salin API Token dari <em>Sending Domains → Integration → API</em>. Gunakan domain terverifikasi agar notifikasi masuk ke email pembeli.</div>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="email_settings">
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="email_enabled" id="emailEnabled" value="1" <?= ($settings['email_enabled'] ?? '0') === '1' ? 'checked' : '' ?>><label class="form-check-label fw-semibold" for="emailEnabled">Aktifkan notifikasi email otomatis</label></div></div>
                    <div class="col-md-6"><label class="form-label">Metode pengiriman</label><input class="form-control" value="Mailtrap Email API — HTTPS port 443" readonly></div>
                    <div class="col-md-6"><label class="form-label">API Token Mailtrap</label><input class="form-control" type="password" name="mailtrap_api_token" autocomplete="new-password" placeholder="<?= !empty($settings['smtp_password']) && ($settings['email_transport'] ?? '') === 'mailtrap_api' ? 'Token tersimpan — kosongkan jika tidak diubah' : 'Tempel API Token Mailtrap' ?>"></div>
                    <div class="col-md-6"><label class="form-label">Email pengirim terverifikasi</label><input class="form-control" type="email" name="smtp_from_email" value="<?= e($settings['smtp_from_email'] ?? '') ?>" placeholder="notifikasi@domainanda.com" required><small class="text-muted">Domain email harus sama dengan domain yang diverifikasi di Mailtrap.</small></div>
                    <div class="col-md-6"><label class="form-label">Nama pengirim</label><input class="form-control" name="smtp_from_name" value="<?= e($settings['smtp_from_name'] ?? store_name()) ?>" required></div>
                    <div class="col-12"><button class="btn btn-primary">Simpan Pengaturan Email</button></div>
                </form>
                <form method="post" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="email_test">
                    <button class="btn btn-outline-success" data-confirm="Kirim email percobaan ke email toko?"><i class="bi bi-send me-1"></i>Kirim Email Percobaan</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="admin-card h-100">
            <div class="admin-card-header"><strong><?= $editBank ? 'Edit' : 'Tambah' ?> rekening</strong></div>
            <div class="admin-card-body">
                <?php if (!$banks): ?><div class="alert alert-info small">Belum ada rekening. Tambahkan rekening toko untuk mengaktifkan pembayaran transfer.</div><?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bank_save">
                    <input type="hidden" name="id" value="<?= (int) ($editBank['id'] ?? 0) ?>">
                    <div class="mb-3"><label class="form-label">Nama bank</label><input class="form-control" name="bank_name" value="<?= e($editBank['bank_name'] ?? '') ?>" required></div>
                    <div class="mb-3"><label class="form-label">Nomor rekening</label><input class="form-control" name="account_number" value="<?= e($editBank['account_number'] ?? '') ?>" required></div>
                    <div class="mb-3"><label class="form-label">Nama pemilik</label><input class="form-control" name="account_holder" value="<?= e($editBank['account_holder'] ?? '') ?>" required></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= !isset($editBank) || $editBank['is_active'] ? 'checked' : '' ?>><label class="form-check-label">Rekening aktif</label></div>
                    <button class="btn btn-primary">Simpan Rekening</button>
                    <?php if ($editBank): ?><a class="btn btn-light" href="<?= url('admin/settings.php') ?>">Batal</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="admin-card h-100">
            <div class="admin-card-header"><strong>Daftar rekening bank</strong></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Bank</th><th>Nomor Rekening</th><th>Pemilik</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    <?php if (!$banks): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada rekening bank.</td></tr><?php endif; ?>
                    <?php foreach ($banks as $bank): ?>
                        <tr><td><strong><?= e($bank['bank_name']) ?></strong></td><td><?= e($bank['account_number']) ?></td><td><?= e($bank['account_holder']) ?></td><td><span class="badge text-bg-<?= $bank['is_active'] ? 'success' : 'secondary' ?>"><?= $bank['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td><td class="text-end"><a class="btn btn-sm btn-light" href="?bank=<?= $bank['id'] ?>"><i class="bi bi-pencil"></i></a><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="bank_delete"><input type="hidden" name="id" value="<?= $bank['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Hapus rekening ini?"><i class="bi bi-trash"></i></button></form></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="admin-card">
            <div class="admin-card-header"><strong>Akun pemilik toko</strong></div>
            <div class="admin-card-body">
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="admin_account">
                    <div class="col-md-6"><label class="form-label">Nama</label><input class="form-control" name="name" value="<?= e(current_user()['name']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Email login</label><input class="form-control" type="email" name="email" value="<?= e(current_user()['email']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Password saat ini</label><input class="form-control" type="password" name="current_password" required></div>
                    <div class="col-md-6"><label class="form-label">Password baru</label><input class="form-control" type="password" name="new_password" minlength="8"><small class="text-muted">Kosongkan jika tidak ingin mengganti password.</small></div>
                    <div class="col-12"><button class="btn btn-primary">Simpan Akun</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>
