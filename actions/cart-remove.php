<?php
require_once __DIR__ . '/../config/app.php'; require_customer();
if (!is_post()) redirect('cart.php'); verify_csrf();
$itemId=(int)($_POST['item_id']??0);
db()->prepare('DELETE ci FROM cart_items ci JOIN carts c ON c.id=ci.cart_id WHERE ci.id=? AND c.user_id=?')->execute([$itemId,current_user()['id']]);
flash('success','Barang dihapus dari keranjang.'); redirect('cart.php');
