<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('products.php');
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);

$stmt = $conn->prepare('SELECT image FROM products WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();

if (!$p) {
    flash('ไม่พบสินค้า', 'error');
    redirect('products.php');
}

try {
    $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($p['image'] && file_exists(UPLOAD_DIR . $p['image'])) {
        @unlink(UPLOAD_DIR . $p['image']);
    }
    flash('ลบสินค้าเรียบร้อยแล้ว');
} catch (mysqli_sql_exception $ex) {
    // มีออเดอร์อ้างอิงสินค้านี้อยู่ (foreign key)
    flash('ไม่สามารถลบได้ เพราะสินค้านี้มีอยู่ในประวัติคำสั่งซื้อ แนะนำให้ตั้งสต็อกเป็น 0 แทน', 'error');
}

redirect('products.php');
