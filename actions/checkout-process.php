<?php
require_once __DIR__ . '/../config/app.php';
require_customer();

if (!is_post()) {
    redirect('checkout.php');
}
verify_csrf();

$requiredFields = ['recipient_name', 'phone', 'address', 'district', 'city', 'province', 'postal_code', 'payment_method', 'idempotency_key', 'delivery_latitude', 'delivery_longitude'];
foreach ($requiredFields as $field) {
    if (trim((string) ($_POST[$field] ?? '')) === '') {
        flash('danger', 'Lengkapi alamat dan titik pengantaran sebelum membuat pesanan.');
        redirect('checkout.php');
    }
}

$method = (string) $_POST['payment_method'];
if (!in_array($method, ['cod', 'transfer'], true)) {
    flash('danger', 'Metode pembayaran tidak valid.');
    redirect('checkout.php');
}

$bankId = $method === 'transfer' ? (int) ($_POST['bank_account_id'] ?? 0) : null;
if ($method === 'transfer') {
    $bankStmt = db()->prepare('SELECT id FROM bank_accounts WHERE id=? AND is_active=1');
    $bankStmt->execute([$bankId]);
    if (!$bankStmt->fetch()) {
        flash('danger', 'Rekening tujuan tidak valid.');
        redirect('checkout.php');
    }
}

$deliveryLatitude = filter_var($_POST['delivery_latitude'], FILTER_VALIDATE_FLOAT);
$deliveryLongitude = filter_var($_POST['delivery_longitude'], FILTER_VALIDATE_FLOAT);
$storeLatitudeValue = setting('store_latitude', '');
$storeLongitudeValue = setting('store_longitude', '');
if ($deliveryLatitude === false || $deliveryLongitude === false || !valid_coordinate((float) $deliveryLatitude, (float) $deliveryLongitude)) {
    flash('danger', 'Titik pengantaran tidak valid. Pilih kembali lokasi pada peta.');
    redirect('checkout.php');
}
if (!is_numeric($storeLatitudeValue) || !is_numeric($storeLongitudeValue)) {
    flash('danger', 'Titik lokasi toko belum diatur. Silakan hubungi pemilik toko.');
    redirect('checkout.php');
}

$storeLatitude = (float) $storeLatitudeValue;
$storeLongitude = (float) $storeLongitudeValue;
if (!valid_coordinate($storeLatitude, $storeLongitude)) {
    flash('danger', 'Titik lokasi toko tidak valid. Silakan hubungi pemilik toko.');
    redirect('checkout.php');
}

$deliveryDistance = distance_km($storeLatitude, $storeLongitude, (float) $deliveryLatitude, (float) $deliveryLongitude);
$deliveryRadius = 10.0;
$fulfillmentMethod = $deliveryDistance <= $deliveryRadius ? 'delivery' : 'pickup';
if ($fulfillmentMethod === 'pickup' && $method !== 'cod') {
    flash('danger', 'Di luar radius 10 km hanya tersedia COD di toko. Silakan pilih pembayaran di toko.');
    redirect('checkout.php');
}

$idempotency = substr(preg_replace('/[^a-zA-Z0-9-]/', '', (string) $_POST['idempotency_key']) ?? '', 0, 80);
if ($idempotency === '' || !hash_equals((string) ($_SESSION['checkout_token'] ?? ''), $idempotency)) {
    flash('danger', 'Sesi checkout tidak valid. Silakan ulangi checkout.');
    redirect('checkout.php');
}

$existing = db()->prepare('SELECT id FROM orders WHERE idempotency_key=? AND user_id=?');
$existing->execute([$idempotency, current_user()['id']]);
if ($existingId = $existing->fetchColumn()) {
    redirect('order-success.php?id=' . (int) $existingId);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $cartStmt = $pdo->prepare('SELECT c.id cart_id,ci.product_id,ci.quantity,p.name,p.sku,p.price,p.stock,p.is_active FROM carts c JOIN cart_items ci ON ci.cart_id=c.id JOIN products p ON p.id=ci.product_id WHERE c.user_id=? ORDER BY p.id FOR UPDATE');
    $cartStmt->execute([current_user()['id']]);
    $items = $cartStmt->fetchAll();
    if (!$items) {
        throw new RuntimeException('Keranjang kosong.');
    }

    $subtotal = 0.0;
    foreach ($items as $item) {
        if (!$item['is_active'] || (int) $item['quantity'] > (int) $item['stock']) {
            throw new RuntimeException('Stok ' . $item['name'] . ' tidak mencukupi.');
        }
        $subtotal += (float) $item['price'] * (int) $item['quantity'];
    }

    $shipping = $fulfillmentMethod === 'delivery' ? max(0, (float) setting('shipping_cost', '0')) : 0.0;
    $total = $subtotal + $shipping;
    $orderNo = order_number($pdo);
    $invoiceNo = invoice_number($pdo);
    $fullAddress = trim($_POST['address']) . ', ' . trim($_POST['district']) . ', ' . trim($_POST['city']) . ', ' . trim($_POST['province']) . ' ' . trim($_POST['postal_code']);

    $insert = $pdo->prepare("INSERT INTO orders (order_number,user_id,invoice_number,recipient_name,phone,full_address,delivery_latitude,delivery_longitude,delivery_distance_km,fulfillment_method,subtotal,shipping_cost,total_amount,payment_method,payment_status,order_status,notes,idempotency_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'unpaid','pending',?,?)");
    $insert->execute([
        $orderNo,
        current_user()['id'],
        $invoiceNo,
        trim($_POST['recipient_name']),
        trim($_POST['phone']),
        $fullAddress,
        $deliveryLatitude,
        $deliveryLongitude,
        round($deliveryDistance, 3),
        $fulfillmentMethod,
        $subtotal,
        $shipping,
        $total,
        $method,
        trim($_POST['notes'] ?? ''),
        $idempotency,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $itemInsert = $pdo->prepare('INSERT INTO order_items (order_id,product_id,product_name,product_sku,quantity,unit_price,subtotal) VALUES (?,?,?,?,?,?,?)');
    $stockUpdate = $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?');
    $movement = $pdo->prepare("INSERT INTO stock_movements (product_id,user_id,order_id,movement_type,quantity,stock_before,stock_after,notes) VALUES (?,?,?,'sale',?,?,?,?)");
    foreach ($items as $item) {
        $quantity = (int) $item['quantity'];
        $lineTotal = (float) $item['price'] * $quantity;
        $itemInsert->execute([$orderId, $item['product_id'], $item['name'], $item['sku'], $quantity, $item['price'], $lineTotal]);
        $stockUpdate->execute([$quantity, $item['product_id'], $quantity]);
        if ($stockUpdate->rowCount() !== 1) {
            throw new RuntimeException('Stok berubah saat checkout. Silakan ulangi.');
        }
        $movement->execute([$item['product_id'], current_user()['id'], $orderId, -$quantity, $item['stock'], (int) $item['stock'] - $quantity, 'Penjualan ' . $orderNo]);
    }

    $pdo->prepare("INSERT INTO payments (order_id,bank_account_id,method,amount,status) VALUES (?,?,?,?,'unpaid')")->execute([$orderId, $bankId, $method, $total]);
    $pdo->prepare("INSERT INTO order_status_histories (order_id,changed_by,old_status,new_status,notes) VALUES (?,?,NULL,'pending','Pesanan dibuat')")->execute([$orderId, current_user()['id']]);
    $pdo->prepare('UPDATE addresses SET is_default=0 WHERE user_id=?')->execute([current_user()['id']]);
    $pdo->prepare('INSERT INTO addresses (user_id,recipient_name,phone,address,district,city,province,postal_code,latitude,longitude,is_default) VALUES (?,?,?,?,?,?,?,?,?,?,1)')->execute([
        current_user()['id'],
        trim($_POST['recipient_name']),
        trim($_POST['phone']),
        trim($_POST['address']),
        trim($_POST['district']),
        trim($_POST['city']),
        trim($_POST['province']),
        trim($_POST['postal_code']),
        $deliveryLatitude,
        $deliveryLongitude,
    ]);
    $pdo->prepare('DELETE FROM cart_items WHERE cart_id=?')->execute([$items[0]['cart_id']]);
    $pdo->commit();

    unset($_SESSION['checkout_token']);
    notify_order_created($orderId);
    $successMessage = $fulfillmentMethod === 'pickup'
        ? 'Pesanan berhasil dibuat sebagai COD di toko. Silakan ambil dan bayar pesanan di toko.'
        : 'Pesanan antar berhasil dibuat. Konfirmasi dan nota dikirim ke email Anda jika notifikasi aktif.';
    flash('success', $successMessage);
    redirect('order-success.php?id=' . $orderId);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($exception instanceof PDOException && $exception->getCode() === '23000') {
        $duplicate = db()->prepare('SELECT id FROM orders WHERE idempotency_key=? AND user_id=?');
        $duplicate->execute([$idempotency, current_user()['id']]);
        if ($duplicateId = $duplicate->fetchColumn()) {
            redirect('order-success.php?id=' . (int) $duplicateId);
        }
    }
    error_log($exception->getMessage());
    flash('danger', $exception instanceof RuntimeException ? $exception->getMessage() : 'Checkout gagal. Silakan coba kembali.');
    redirect('checkout.php');
}
