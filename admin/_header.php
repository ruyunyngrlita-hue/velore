<?php
// ส่วนหัวของหน้าแอดมินทุกหน้า (บังคับล็อกอิน)
// ก่อน include ไฟล์นี้ให้ตั้งค่า $pageTitle และ $activeNav (dashboard|products|stock|categories|orders|reports|password)
require_once __DIR__ . '/../includes/auth.php';
admin_required();

$pageTitle = isset($pageTitle) ? $pageTitle . ' | VELORE Admin' : 'VELORE Admin';
$activeNav = $activeNav ?? '';
$navItems = [
    'dashboard'  => ['index.php',      'Dashboard'],
    'products'   => ['products.php',   'สินค้า'],
    'stock'      => ['stock.php',      'สต็อก'],
    'categories' => ['categories.php', 'หมวดหมู่'],
    'images'     => ['images.php',     'รูปภาพ'],
    'orders'     => ['orders.php',     'คำสั่งซื้อ'],
    'customers'  => ['customers.php',  'สมาชิก'],
    'reports'    => ['reports.php',    'รายงานยอดขาย'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Noto+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="admin">
<header class="admin-top">
    <div class="container admin-top-inner">
        <a class="admin-brand" href="<?= BASE_URL ?>/admin/index.php">VELORE <span>ADMIN</span></a>
        <nav class="admin-nav">
            <?php foreach ($navItems as $key => [$href, $label]): ?>
                <a href="<?= BASE_URL ?>/admin/<?= $href ?>" class="<?= $activeNav === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-user">
            <a href="<?= BASE_URL ?>/index.php" target="_blank" rel="noopener">ดูหน้าร้าน</a>
            <a href="<?= BASE_URL ?>/admin/password.php" class="<?= $activeNav === 'password' ? 'active' : '' ?>">เปลี่ยนรหัสผ่าน</a>
            <a href="<?= BASE_URL ?>/admin/logout.php">ออกจากระบบ (<?= e($_SESSION['admin_name'] ?? '') ?>)</a>
        </div>
    </div>
</header>
<main class="container admin-main">
<?= flash() ?>
