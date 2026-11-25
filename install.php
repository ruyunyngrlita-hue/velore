<?php
// =====================================================
// VELORE - ตัวติดตั้งอัตโนมัติ
// เปิด http://localhost/VELORE/install.php 1 ครั้ง แล้วลบไฟล์นี้ทิ้ง
// =====================================================
mysqli_report(MYSQLI_REPORT_OFF);

$host = '127.0.0.1';
$port = 3306;     // ถ้า XAMPP ใช้พอร์ตอื่น (เช่น 3307) ให้แก้ตรงนี้
$user = 'root';
$pass = '';       // ถ้า root มีรหัสผ่าน ให้ใส่ตรงนี้

$messages = [];
$ok = false;

$conn = @new mysqli($host, $user, $pass, '', $port);

if ($conn->connect_errno) {
    $messages[] = ['error', 'เชื่อมต่อ MySQL ไม่ได้: ' . $conn->connect_error
        . ' — ตรวจสอบว่ากด Start MySQL ใน XAMPP แล้ว และค่า $port / $pass ในไฟล์นี้ถูกต้อง'];
} else {
    $conn->set_charset('utf8mb4');

    // เช็คว่าติดตั้งไปแล้วหรือยัง
    $already = false;
    $res = $conn->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE LOWER(table_schema) = 'velore' AND table_name = 'admins'");
    if ($res && ($row = $res->fetch_assoc()) && (int)$row['c'] > 0) {
        $already = true;
    }

    if ($already) {
        $messages[] = ['error', 'ฐานข้อมูล VELORE ถูกติดตั้งไว้แล้ว ไม่ได้ทำอะไรซ้ำ'
            . ' — ถ้าเป็นฐานข้อมูลจากเวอร์ชันเก่า ให้เปิด upgrade.php เพื่ออัปเกรดโดยข้อมูลไม่หาย'
            . ' หรือถ้าต้องการติดตั้งใหม่หมด ให้ลบฐานข้อมูล VELORE ใน phpMyAdmin ก่อน'];
        $ok = true;
    } else {
        $sqlFile = __DIR__ . '/database.sql';
        if (!is_readable($sqlFile)) {
            $messages[] = ['error', 'ไม่พบไฟล์ database.sql ในโฟลเดอร์เดียวกับ install.php'];
        } else {
            $sql = file_get_contents($sqlFile);
            if ($conn->multi_query($sql)) {
                do {
                    if ($r = $conn->store_result()) {
                        $r->free();
                    }
                } while ($conn->more_results() && $conn->next_result());
            }
            if ($conn->errno) {
                $messages[] = ['error', 'ติดตั้งไม่สำเร็จ: ' . $conn->error];
            } else {
                $ok = true;
                $messages[] = ['success', 'สร้างฐานข้อมูล VELORE, ตารางทั้งหมด, แอดมิน และสินค้าตัวอย่างเรียบร้อยแล้ว'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ติดตั้ง VELORE</title>
<style>
    body { font-family: system-ui, 'Noto Sans Thai', sans-serif; background:#faf6ef; color:#1f1b16; display:flex; justify-content:center; padding:48px 16px; }
    .box { background:#fff; border:1px solid #e7dfd0; border-radius:10px; padding:28px; max-width:560px; width:100%; }
    h1 { margin-top:0; letter-spacing:.2em; }
    .msg { padding:12px 14px; border-radius:6px; margin:12px 0; }
    .success { background:#e4f3ea; color:#2f7d4f; }
    .error { background:#fbe9e7; color:#b3382c; }
    a.btn { display:inline-block; background:#a88a4e; color:#fff; padding:9px 18px; border-radius:6px; text-decoration:none; margin:6px 6px 0 0; }
</style>
</head>
<body>
<div class="box">
    <h1>VELORE</h1>
    <?php foreach ($messages as [$type, $text]): ?>
        <div class="msg <?= $type ?>"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <?php if ($ok): ?>
        <p><strong>แอดมินเริ่มต้น:</strong> admin / admin123 (ควรเปลี่ยนรหัสผ่านทันที)</p>
        <p style="color:#b3382c"><strong>สำคัญ:</strong> ลบไฟล์ <code>install.php</code> ออกจากโฟลเดอร์ VELORE หลังติดตั้งเสร็จ</p>
        <a class="btn" href="index.php">ไปหน้าร้าน</a>
        <a class="btn" href="admin/login.php">ไปหน้าแอดมิน</a>
    <?php endif; ?>
</div>
</body>
</html>
