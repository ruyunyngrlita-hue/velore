<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

$statuses = order_statuses();
$filter = $_GET['status'] ?? '';
if (!isset($statuses[$filter])) {
    $filter = '';
}
$q = trim($_GET['q'] ?? '');
$customerId = (int)($_GET['customer'] ?? 0);   // กรองเฉพาะออเดอร์ของสมาชิกคนนี้
$methods = payment_methods();
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$where = [];
$types = '';
$params = [];
if ($filter !== '') {
    $where[] = 'status = ?';
    $types .= 's';
    $params[] = $filter;
}
if ($customerId > 0) {
    $where[] = 'customer_id = ?';
    $types .= 'i';
    $params[] = $customerId;
}
if ($q !== '') {
    $where[] = '(customer_name LIKE ? OR phone LIKE ? OR id = ?)';
    $like = '%' . $q . '%';
    $idq = ctype_digit(ltrim($q, '#')) ? (int)ltrim($q, '#') : 0;
    $types .= 'ssi';
    array_push($params, $like, $like, $idq);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $conn->prepare("SELECT COUNT(*) FROM orders $whereSql");
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_row()[0];
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $conn->prepare("SELECT * FROM orders $whereSql ORDER BY id DESC LIMIT ? OFFSET ?");
$p2 = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($types . 'ii', ...$p2);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function orders_page_url(int $n): string {
    $qs = $_GET;
    $qs['page'] = $n;
    return 'orders.php?' . http_build_query($qs);
}

$pageTitle = 'คำสั่งซื้อ';
$activeNav = 'orders';
require __DIR__ . '/_header.php';
?>

<h1>คำสั่งซื้อของลูกค้า</h1>

<?php if ($customerId > 0): ?>
    <p class="alert alert-success" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
        <span>กำลังดูเฉพาะออเดอร์ของสมาชิก ID <?= $customerId ?></span>
        <a href="orders.php">ดูออเดอร์ทั้งหมด</a>
    </p>
<?php endif; ?>

<div class="filter-bar" style="justify-content:flex-start">
    <?php $keep = ($q !== '' ? '&q=' . urlencode($q) : '') . ($customerId > 0 ? '&customer=' . $customerId : ''); ?>
    <a class="chip <?= $filter === '' ? 'active' : '' ?>" href="orders.php?<?= ltrim($keep, '&') ?>">ทั้งหมด</a>
    <?php foreach ($statuses as $key => $label): ?>
        <a class="chip <?= $filter === $key ? 'active' : '' ?>" href="orders.php?status=<?= e($key) ?><?= e($keep) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<form class="search-bar" method="get">
    <?php if ($filter !== ''): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
    <?php if ($customerId > 0): ?><input type="hidden" name="customer" value="<?= $customerId ?>"><?php endif; ?>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="ค้นหาชื่อลูกค้า / เบอร์โทร / เลขที่ออเดอร์">
    <button class="btn" type="submit">ค้นหา</button>
    <?php if ($q !== ''): ?><a class="btn btn-outline" href="orders.php<?= $filter !== '' ? '?status=' . e($filter) : '' ?>">ล้างคำค้น</a><?php endif; ?>
</form>

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>ลูกค้า</th>
            <th>เบอร์โทร</th>
            <th class="num">ยอดรวม</th>
            <th>ชำระเงิน</th>
            <th>สถานะ</th>
            <th>วันที่สั่ง</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$orders): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--muted)">ไม่พบคำสั่งซื้อ</td></tr>
    <?php endif; ?>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td><?= (int)$o['id'] ?></td>
            <td><?= e($o['customer_name']) ?><?php if ($o['customer_id']): ?> <span class="tag" title="สมาชิก ID <?= (int)$o['customer_id'] ?>">สมาชิก</span><?php endif; ?></td>
            <td><?= e($o['phone']) ?></td>
            <td class="num"><?= money($o['total_price']) ?></td>
            <td><?= e($methods[$o['payment_method']] ?? $o['payment_method']) ?>
                <?php if ($o['payment_method'] === 'transfer' && !empty($o['slip_image'])): ?><br><span class="tag tag-slip">แนบสลิปแล้ว</span><?php endif; ?></td>
            <td><span class="status status-<?= e($o['status']) ?>"><?= e($statuses[$o['status']] ?? $o['status']) ?></span></td>
            <td style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></td>
            <td><a class="btn btn-sm btn-outline" href="order_view.php?id=<?= (int)$o['id'] ?>">ดูรายละเอียด</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($pages > 1): ?>
<div class="pager">
    <?php if ($page > 1): ?><a href="<?= e(orders_page_url($page - 1)) ?>">&laquo;</a><?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="cur"><?= $i ?></span>
        <?php elseif ($i === 1 || $i === $pages || abs($i - $page) <= 2): ?><a href="<?= e(orders_page_url($i)) ?>"><?= $i ?></a>
        <?php elseif (abs($i - $page) === 3): ?><span>…</span>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="<?= e(orders_page_url($page + 1)) ?>">&raquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
