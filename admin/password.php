<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $current = $_POST['current'] ?? '';
    $new     = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    $stmt = $conn->prepare('SELECT password FROM admins WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['admin_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !password_verify($current, $row['password'])) {
        $errors[] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    }
    if (mb_strlen($new) < 8) {
        $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';
    }
    if ($new !== $confirm) {
        $errors[] = 'การยืนยันรหัสผ่านใหม่ไม่ตรงกัน';
    }
    if (!$errors && $new === $current) {
        $errors[] = 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสเดิม';
    }

    if (!$errors) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE admins SET password = ? WHERE id = ?');
        $stmt->bind_param('si', $hash, $_SESSION['admin_id']);
        $stmt->execute();
        session_regenerate_id(true);
        flash('เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
        redirect('index.php');
    }
}

$pageTitle = 'เปลี่ยนรหัสผ่าน';
$activeNav = 'password';
require __DIR__ . '/_header.php';
?>

<h1>เปลี่ยนรหัสผ่าน</h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" class="form-box" autocomplete="off">
    <?= csrf_field() ?>
    <label for="current">รหัสผ่านปัจจุบัน</label>
    <input type="password" id="current" name="current" required>
    <label for="new">รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)</label>
    <input type="password" id="new" name="new" required minlength="8">
    <label for="confirm">ยืนยันรหัสผ่านใหม่</label>
    <input type="password" id="confirm" name="confirm" required minlength="8">
    <p style="margin-top:20px"><button class="btn" type="submit">บันทึก</button></p>
</form>

<?php require __DIR__ . '/_footer.php'; ?>
