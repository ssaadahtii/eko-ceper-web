<?php
require_once __DIR__.'/../config/app.php';require_customer();if(!is_post())redirect('cart.php');verify_csrf();$cartId=get_or_create_cart((int)current_user()['id']);db()->prepare('DELETE FROM cart_items WHERE cart_id=?')->execute([$cartId]);flash('success','Keranjang berhasil dikosongkan.');redirect('cart.php');
