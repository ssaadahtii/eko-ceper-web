<?php
require_once __DIR__ . '/config/app.php';
require_customer();

$errors = [];
if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? 'profile';
    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (mb_strlen($name) < 3) $errors[] = 'Nama minimal 3 karakter.';
        if (strlen($phone) < 9) $errors[] = 'Nomor HP tidak valid.';
        if ($password !== '' && strlen($password) < 8) $errors[] = 'Password baru minimal 8 karakter.';
        if (!$errors) {
            if ($password !== '') {
                db()->prepare('UPDATE users SET name=?,phone=?,password=? WHERE id=?')->execute([$name, $phone, password_hash($password, PASSWORD_DEFAULT), current_user()['id']]);
            } else {
                db()->prepare('UPDATE users SET name=?,phone=? WHERE id=?')->execute([$name, $phone, current_user()['id']]);
            }
            flash('success', 'Profil berhasil diperbarui.');
            redirect('profile.php');
        }
    } elseif ($action === 'address_save') {
        $id = (int) ($_POST['id'] ?? 0);
        $fields = ['recipient_name', 'phone', 'address', 'district', 'city', 'province', 'postal_code'];
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = trim((string) ($_POST[$field] ?? ''));
            if ($values[$field] === '') $errors[] = 'Lengkapi seluruh data alamat.';
        }
        $errors = array_unique($errors);
        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE addresses SET is_default=0 WHERE user_id=?')->execute([current_user()['id']]);
                if ($id) {
                    $stmt = $pdo->prepare('UPDATE addresses SET recipient_name=?,phone=?,address=?,district=?,city=?,province=?,postal_code=?,is_default=1 WHERE id=? AND user_id=?');
                    $stmt->execute([$values['recipient_name'], $values['phone'], $values['address'], $values['district'], $values['city'], $values['province'], $values['postal_code'], $id, current_user()['id']]);
                    if ($stmt->rowCount() < 1) throw new RuntimeException('Alamat tidak ditemukan atau tidak berubah.');
                } else {
                    $pdo->prepare('INSERT INTO addresses(user_id,recipient_name,phone,address,district,city,province,postal_code,is_default) VALUES(?,?,?,?,?,?,?,?,1)')->execute([current_user()['id'], $values['recipient_name'], $values['phone'], $values['address'], $values['district'], $values['city'], $values['province'], $values['postal_code']]);
                }
                $pdo->commit();
                flash('success', 'Alamat utama berhasil disimpan.');
                redirect('profile.php');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($action === 'address_delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM addresses WHERE id=? AND user_id=?')->execute([$id, current_user()['id']]);
        flash('success', 'Alamat berhasil dihapus.');
        redirect('profile.php');
    }
}

$user = current_user();
$addressesStmt = db()->prepare('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC,id DESC');
$addressesStmt->execute([$user['id']]);
$addresses = $addressesStmt->fetchAll();
$editAddress = null;
if (isset($_GET['address'])) {
    foreach ($addresses as $row) if ((int) $row['id'] === (int) $_GET['address']) $editAddress = $row;
}
$pageTitle = 'Profil Saya';
require APP_ROOT . '/includes/header.php';
?>
<section class="page-hero"><div class="container"><div class="section-kicker">Akun pembeli</div><h1>Profil Saya</h1></div></section>
<section class="pb-5"><div class="container">
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="row g-4">
    <div class="col-lg-6"><div class="content-card"><h4 class="fw-bold mb-4">Informasi akun</h4><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><div class="mb-3"><label class="form-label">Nama lengkap</label><input class="form-control" name="name" value="<?= e($user['name']) ?>" required></div><div class="mb-3"><label class="form-label">Email</label><input class="form-control" value="<?= e($user['email']) ?>" disabled><small class="text-muted">Email digunakan sebagai identitas login.</small></div><div class="mb-3"><label class="form-label">Nomor HP</label><input class="form-control" name="phone" value="<?= e($user['phone']) ?>" required></div><div class="mb-4"><label class="form-label">Password baru <span class="text-muted">(opsional)</span></label><input class="form-control" type="password" name="password" minlength="8"><small class="text-muted">Kosongkan jika tidak ingin mengganti password.</small></div><button class="btn btn-primary">Simpan Profil</button></form></div></div>
    <div class="col-lg-6"><div class="content-card"><h4 class="fw-bold mb-4"><?= $editAddress ? 'Edit' : 'Tambah' ?> alamat pengiriman</h4><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="address_save"><input type="hidden" name="id" value="<?= (int) ($editAddress['id'] ?? 0) ?>"><div class="row g-3"><div class="col-md-6"><label class="form-label">Nama penerima</label><input class="form-control" name="recipient_name" value="<?= e($editAddress['recipient_name'] ?? $user['name']) ?>" required></div><div class="col-md-6"><label class="form-label">Nomor HP</label><input class="form-control" name="phone" value="<?= e($editAddress['phone'] ?? $user['phone']) ?>" required></div><div class="col-12"><label class="form-label">Alamat lengkap</label><textarea class="form-control" name="address" rows="2" required><?= e($editAddress['address'] ?? '') ?></textarea></div><div class="col-md-6"><label class="form-label">Kecamatan</label><input class="form-control" name="district" value="<?= e($editAddress['district'] ?? '') ?>" required></div><div class="col-md-6"><label class="form-label">Kota/Kabupaten</label><input class="form-control" name="city" value="<?= e($editAddress['city'] ?? '') ?>" required></div><div class="col-md-6"><label class="form-label">Provinsi</label><input class="form-control" name="province" value="<?= e($editAddress['province'] ?? '') ?>" required></div><div class="col-md-6"><label class="form-label">Kode pos</label><input class="form-control" name="postal_code" value="<?= e($editAddress['postal_code'] ?? '') ?>" required></div><div class="col-12"><button class="btn btn-primary">Simpan sebagai Alamat Utama</button><?php if ($editAddress): ?><a class="btn btn-light" href="<?= url('profile.php') ?>">Batal</a><?php endif; ?></div></div></form></div></div>
    <div class="col-12"><div class="content-card"><h4 class="fw-bold mb-3">Daftar alamat</h4><?php if (!$addresses): ?><p class="text-muted mb-0">Belum ada alamat tersimpan.</p><?php else: ?><div class="row g-3"><?php foreach ($addresses as $address): ?><div class="col-md-6"><div class="border rounded-3 p-3 h-100"><div class="d-flex justify-content-between"><div><strong><?= e($address['recipient_name']) ?></strong><?php if ($address['is_default']): ?><span class="badge bg-success-subtle text-success ms-1">Utama</span><?php endif; ?></div><div><a class="btn btn-sm btn-light" href="?address=<?= $address['id'] ?>"><i class="bi bi-pencil"></i></a><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="address_delete"><input type="hidden" name="id" value="<?= $address['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Hapus alamat ini?"><i class="bi bi-trash"></i></button></form></div></div><div class="small text-muted"><?= e($address['phone']) ?></div><div class="small mt-2"><?= e($address['address'] . ', ' . $address['district'] . ', ' . $address['city'] . ', ' . $address['province'] . ' ' . $address['postal_code']) ?></div></div></div><?php endforeach; ?></div><?php endif; ?></div></div>
</div></div></section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
