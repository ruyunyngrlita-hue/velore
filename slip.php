<?php
// แสดงรูปสลิป: เจ้าของออเดอร์ หรือแอดมินที่ล็อกอินอยู่เท่านั้น (ไฟล์จริงถูกบล็อกไม่ให้เปิดตรง)
require_once __DIR__ . '/includes/config.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT customer_id, slip_image FROM orders WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$o = $stmt->get_result()->fetch_assoc();

$isAdmin = !empty($_SESSION['admin_id']);
$isOwner = $o && customer_logged_in() && (int)$o['customer_id'] === (int)$_SESSION['customer_id'];
$file = $o ? SLIP_DIR . basename((string)$o['slip_image']) : '';

if (!$o || !($isAdmin || $isOwner) || $o['slip_image'] === '' || !is_file($file)) {
    http_response_code(404);
    exit('Not found');
}
$info = getimagesize($file);
header('Content-Type: ' . ($info['mime'] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($file);
