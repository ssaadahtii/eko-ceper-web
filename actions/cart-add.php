<?php
require_once __DIR__ . '/../config/app.php';
require_customer();
if (!is_post()) redirect('shop.php');
verify_csrf();
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));
$stmt = db()->prepare('SELECT id,name,stock,is_active FROM products WHERE id=? LIMIT 1');
$stmt->execute([$productId]); $product = $stmt->fetch();
if (!$product || !$product['is_active']) { flash('danger','Produk tidak tersedia.'); redirect('shop.php'); }
$cartId = get_or_create_cart((int) current_user()['id']);
$stmt = db()->prepare('SELECT quantity FROM cart_items WHERE cart_id=? AND product_id=?');
$stmt->execute([$cartId,$productId]); $existing=(int)($stmt->fetchColumn() ?: 0);
$newQty = $existing + $quantity;
if ($newQty > (int)$product['stock']) { flash('warning','Jumlah melebihi stok yang tersedia.'); redirect('product.php?id=' . $productId); }
db()->prepare('INSERT INTO cart_items (cart_id,product_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), updated_at=NOW()')->execute([$cartId,$productId,$newQty]);
flash('success',$product['name'].' ditambahkan ke keranjang.');
$back = $_POST['redirect'] ?? 'cart.php';
redirect(preg_match('/^[a-z0-9_.?=&-]+$/i',$back) ? $back : 'cart.php');
