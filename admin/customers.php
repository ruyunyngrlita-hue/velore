<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

// หน้านี้ดูข้อมูลสมาชิกอย่างเดียว ไม่แสดง/แก้รหัสผ่าน และไม่ลบสมาชิก
$sales   = sales_statuses_sql();
$q       = trim($_GET['q'] ?? '');
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));

$whereSql = '';
$types = '';
$params = [];
if ($q !== '') {
    $whereSql = 'WHERE (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $like = '%' . $q . '%';
    $types = 'sss';
    $params = [$like, $like, $like];
}

$stmt = $conn->prepare("SELECT COUNT(*) FROM customers c $whereSql");
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_row()[0];
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $conn->prepare(
    "SELECT c.id, c.name, c.email, c.phone, c.address, c.created_at,
            COUNT(o.id) AS orders_count,
            COALESCE(SUM(CASE WHEN o.status IN ($sales) THEN o.total_price END), 0) AS spent
     FROM customers c LEFT JOIN orders o ON o.customer_id = c.id
     $whereSql
     GROUP BY c.id, c.name, c.email, c.phone, c.address, c.created_at
     ORDER BY c.id DESC LIMIT ? OFFSET ?"
);
$p2 = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($types . 'ii', ...$p2);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function customers_page_url(int $n): string {
    $qs = $_GET;
    $qs['page'] = $n;
    return 'customers.php?' . http_build_query($qs);
}

$pageTitle = 'สมาชิก';
$activeNav = 'customers';
require __DIR__ . '/_header.php';
?>

<h1>สมาชิก (ลูกค้า)</h1>

<form class="search-bar" method="get">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="ค้นหาชื่อ / อีเมล / เบอร์โทร">
    <button class="btn" type="submit">ค้นหา</button>
    <?php if ($q !== ''): ?><a class="btn btn-outline" href="customers.php">ล้างคำค้น</a><?php endif; ?>
</form>

<p class="muted" style="margin:0 0 10px">สมาชิกทั้งหมด <?= $total ?> คน</p>

<div class="table-wrap">
<table>
    <thead>
        <tr><th>ID</th><th>ชื่อ / อีเมล</th><th>เบอร์โทร</th><th>ที่อยู่จัดส่ง</th><th>สมัครเมื่อ</th><th class="num">ออเดอร์</th><th class="num">ยอดซื้อ</th><th></th></tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--muted)">ยังไม่มีสมาชิก</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $c): ?>
        <tr>
            <td><?= (int)$c['id'] ?></td>
            <td><strong><?= e($c['name']) ?></strong><br><small class="muted"><?= e($c['email']) ?></small></td>
            <td><?= e($c['phone']) ?></td>
            <td style="max-width:260px"><?= $c['address'] ? e(mb_strimwidth($c['address'], 0, 90, '…', 'UTF-8')) : '<span class="muted">—</span>' ?></td>
            <td style="white-space:nowrap"><?= e(date('d/m/Y', strtotime($c['created_at']))) ?></td>
            <td class="num"><?= (int)$c['orders_count'] ?></td>
            <td class="num"><?= money($c['spent']) ?></td>
            <td><?php if ($c['orders_count'] > 0): ?><a class="btn btn-sm btn-outline" href="orders.php?customer=<?= (int)$c['id'] ?>">ดูออเดอร์</a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="muted" style="font-size:.85rem">ยอดซื้อ = เฉพาะออเดอร์ที่ชำระแล้ว/กำลังเตรียม/จัดส่ง/สำเร็จ</p>

<?php if ($pages > 1): ?>
<div class="pager">
    <?php if ($page > 1): ?><a href="<?= e(customers_page_url($page - 1)) ?>">&laquo;</a><?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="cur"><?= $i ?></span>
        <?php elseif ($i === 1 || $i === $pages || abs($i - $page) <= 2): ?><a href="<?= e(customers_page_url($i)) ?>"><?= $i ?></a>
        <?php elseif (abs($i - $page) === 3): ?><span>…</span>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="<?= e(customers_page_url($page + 1)) ?>">&raquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
