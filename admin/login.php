<?php
require_once __DIR__ . '/../includes/auth.php';

if (admin_logged_in()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $wait = login_locked_seconds();
    if ($wait > 0) {
        $error = 'ใส่รหัสผิดหลายครั้งเกินไป กรุณารออีก ' . ceil($wait / 60) . ' นาที';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare('SELECT id, username, password FROM admins WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin && password_verify($password, $admin['password'])) {
            admin_login($admin);
            redirect('index.php');
        }
        login_record_failure();
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}

$pageTitle = 'เข้าสู่ระบบแอดมิน';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | VELORE</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Noto+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-login">
<div class="login-card">
    <div class="login-logo">VELORE</div>
    <p class="login-sub">ระบบหลังร้าน</p>
    <?= flash() ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <label for="username">ชื่อผู้ใช้</label>
        <input type="text" id="username" name="username" required autofocus>
        <label for="password">รหัสผ่าน</label>
        <input type="password" id="password" name="password" required>
        <button class="btn btn-block" type="submit">เข้าสู่ระบบ</button>
    </form>
    <p class="login-back"><a href="<?= BASE_URL ?>/index.php">&larr; กลับหน้าร้าน</a></p>
</div>
</body>
</html>
