<?php
require_once __DIR__ . '/../config/app.php'; require_customer();
if (!is_post()) redirect('cart.php'); verify_csrf();
$itemId=(int)($_POST['item_id']??0); $quantity=max(1,(int)($_POST['quantity']??1));
$stmt=db()->prepare('SELECT ci.id,p.stock FROM cart_items ci JOIN carts c ON c.id=ci.cart_id JOIN products p ON p.id=ci.product_id WHERE ci.id=? AND c.user_id=?');
$stmt->execute([$itemId,current_user()['id']]); $item=$stmt->fetch();
if(!$item){flash('danger','Item keranjang tidak ditemukan.');redirect('cart.php');}
if($quantity>(int)$item['stock']){flash('warning','Jumlah melebihi stok tersedia.');redirect('cart.php');}
db()->prepare('UPDATE cart_items SET quantity=?,updated_at=NOW() WHERE id=?')->execute([$quantity,$itemId]);
flash('success','Jumlah barang diperbarui.');redirect('cart.php');
