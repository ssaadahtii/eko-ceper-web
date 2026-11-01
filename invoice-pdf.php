<?php
require_once __DIR__ . '/config/app.php';
require_once APP_ROOT . '/includes/simple-pdf.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$sql = 'SELECT o.*,u.name buyer_name FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?';
$params = [$id];
if (!is_admin()) {
    $sql .= ' AND o.user_id=?';
    $params[] = current_user()['id'];
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('Nota tidak ditemukan.');
}

$itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();
$pdf = new SimplePdf();
$pdf->addPage();

$drawHeader = function () use ($pdf, $order): void {
    $pdf->filledRectangle(0, 0, 595.28, 74, 0.016, 0.471, 0.341);
    $pdf->text(40, 42, store_name(), 19, true);
    $pdf->text(395, 35, 'NOTA PEMBELIAN', 15, true);
    $pdf->text(411, 54, $order['invoice_number'], 9);
};
$drawHeader();

$pdf->text(40, 105, 'Pembeli', 9);
$pdf->text(40, 122, $order['buyer_name'], 11, true);
$pdf->text(40, 140, $order['recipient_name'] . ' - ' . $order['phone'], 9);
$pdf->text(40, 158, mb_strimwidth($order['full_address'], 0, 82, '...'), 8);
$pdf->text(365, 105, 'Nomor Pesanan', 9);
$pdf->text(365, 122, $order['order_number'], 11, true);
$pdf->text(365, 140, date('d/m/Y H:i', strtotime($order['created_at'])) . ' WIB', 9);
$paymentLabel = $order['fulfillment_method'] === 'pickup'
    ? 'COD di Toko'
    : ($order['payment_method'] === 'cod' ? 'COD Antar' : 'Transfer Antar');
$pdf->text(365, 158, 'Pembayaran: ' . $paymentLabel, 9);

$top = 195.0;
$pdf->filledRectangle(35, $top, 525, 28, 0.945, 0.961, 0.976);
$pdf->text(45, $top + 18, 'Produk', 9, true);
$pdf->text(350, $top + 18, 'Qty', 9, true);
$pdf->text(400, $top + 18, 'Harga', 9, true);
$pdf->text(495, $top + 18, 'Subtotal', 9, true);
$top += 43;

foreach ($items as $item) {
    if ($top > 740) {
        $pdf->addPage();
        $drawHeader();
        $top = 105;
    }
    $pdf->text(45, $top, mb_strimwidth($item['product_name'], 0, 42, '...'), 9, true);
    $pdf->text(45, $top + 15, $item['product_sku'], 7);
    $pdf->text(354, $top, (string) $item['quantity'], 9);
    $pdf->text(400, $top, rupiah($item['unit_price']), 8);
    $pdf->text(495, $top, rupiah($item['subtotal']), 8);
    $pdf->line(40, $top + 23, 555, $top + 23, 0.3);
    $top += 39;
}

if ($top > 680) {
    $pdf->addPage();
    $drawHeader();
    $top = 115;
}
$top += 12;
$pdf->text(390, $top, 'Subtotal', 9);
$pdf->text(485, $top, rupiah($order['subtotal']), 9);
$top += 20;
$pdf->text(390, $top, 'Ongkir', 9);
$pdf->text(485, $top, rupiah($order['shipping_cost']), 9);
$top += 26;
$pdf->line(385, $top - 16, 555, $top - 16, 0.7);
$pdf->text(390, $top, 'TOTAL', 11, true);
$pdf->text(480, $top, rupiah($order['total_amount']), 11, true);
$top += 46;
$pdf->text(40, $top, 'Status pembayaran: ' . payment_status_label($order['payment_status']), 9, true);
$pdf->text(40, $top + 18, 'Status pesanan: ' . order_status_label($order['order_status']), 9);
$pdf->text(40, $top + 50, 'Terima kasih telah berbelanja di ' . store_name() . '.', 10, true);

$binary = $pdf->output();
$filename = preg_replace('/[^A-Za-z0-9_-]/', '-', $order['invoice_number']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($binary));
header('Cache-Control: private, no-store');
echo $binary;
