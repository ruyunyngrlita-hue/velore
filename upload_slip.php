<?php
require_once __DIR__ . '/includes/config.php';
customer_required('orders.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('orders.php');
csrf_check();

$orderId = (int)($_POST['order_id'] ?? 0);
$back = 'order.php?id=' . $orderId;

// เฉพาะออเดอร์ของตัวเอง แบบโอนเงิน และยังรอชำระเงิน
$stmt = $conn->prepare("SELECT id, status, payment_method, slip_image FROM orders WHERE id = ? AND customer_id = ?");
$stmt->bind_param('ii', $orderId, $_SESSION['customer_id']);
$stmt->execute();
$o = $stmt->get_result()->fetch_assoc();
if (!$o || $o['payment_method'] !== 'transfer' || $o['status'] !== 'pending') {
    flash(t('slip_not_allowed'), 'error');
    redirect($o ? $back : 'orders.php');
}

try {
    $name = save_slip_upload($_FILES['slip'] ?? [], $orderId);
} catch (RuntimeException $ex) {
    $map = ['no_file' => 'slip_no_file', 'too_big' => 'slip_big', 'bad_type' => 'slip_type'];
    flash(t($map[$ex->getMessage()] ?? 'slip_fail'), 'error');
    redirect($back);
}

$stmt = $conn->prepare('UPDATE orders SET slip_image = ?, slip_uploaded_at = NOW() WHERE id = ?');
$stmt->bind_param('si', $name, $orderId);
$stmt->execute();

if (!empty($o['slip_image']) && is_file(SLIP_DIR . basename($o['slip_image']))) {
    @unlink(SLIP_DIR . basename($o['slip_image']));   // ลบสลิปเก่าที่ถูกแทนที่
}
flash(t('slip_ok'));
redirect($back);
