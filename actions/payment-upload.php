<?php
require_once __DIR__ . '/../config/app.php'; require_customer();
if(!is_post())redirect('orders.php'); verify_csrf();
$orderId=(int)($_POST['order_id']??0);
$stmt=db()->prepare("SELECT o.id,o.payment_method,o.payment_status,o.order_status,p.id payment_id,p.proof_image FROM orders o JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=?");
$stmt->execute([$orderId,current_user()['id']]);$order=$stmt->fetch();
if(!$order||$order['payment_method']!=='transfer'||$order['order_status']==='cancelled'||!in_array($order['payment_status'],['unpaid','rejected'],true)){flash('danger','Bukti pembayaran tidak dapat diunggah untuk pesanan ini.');redirect('orders.php');}
try{$path=upload_image($_FILES['proof_image']??[],'storage/payment-proofs','payment');$oldPath=$order['proof_image'];$pdo=db();$pdo->beginTransaction();$pdo->prepare("UPDATE payments SET proof_image=?,status='waiting_verification',verified_by=NULL,verified_at=NULL WHERE id=?")->execute([$path,$order['payment_id']]);$pdo->prepare("UPDATE orders SET payment_status='waiting_verification' WHERE id=?")->execute([$orderId]);$pdo->commit();remove_upload($oldPath);notify_payment_proof_uploaded($orderId);flash('success','Bukti transfer berhasil diunggah dan menunggu verifikasi.');}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();remove_upload($path??null);flash('danger',$e->getMessage());}
redirect('order-detail.php?id='.$orderId);
