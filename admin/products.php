<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

$perPage = 15;
$q       = trim($_GET['q'] ?? '');
$cat     = $_GET['cat'] ?? '';          // '' = ทั้งหมด, 'none' = ไม่มีหมวด, ตัวเลข = id หมวด
$stockF  = $_GET['stock'] ?? '';        // '', 'low', 'out'
$page    = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$types  = '';
$params = [];

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.scent_notes LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($cat === 'none') {
    $where[] = 'p.category_id IS NULL';
} elseif ($cat !== '' && ctype_digit((string)$cat)) {
    $where[] = 'p.category_id = ?';
    $types .= 'i';
    $params[] = (int)$cat;
}
if ($stockF === 'out') {
    $where[] = 'p.stock <= 0';
} elseif ($stockF === 'low') {
    $where[] = 'p.stock > 0 AND p.stock <= ?';
    $types .= 'i';
    $params[] = LOW_STOCK;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// นับทั้งหมดเพื่อแบ่งหน้า
$stmt = $conn->prepare("SELECT COUNT(*) FROM products p $whereSql");
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_row()[0];
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $conn->prepare(
    "SELECT p.*, c.name AS category_name
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     $whereSql ORDER BY p.id DESC LIMIT ? OFFSET ?"
);
$t2 = $types . 'ii';
$p2 = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($t2, ...$p2);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$categories = get_categories($conn);

function page_url(int $n): string {
    $qs = $_GET;
    $qs['page'] = $n;
    return 'products.php?' . http_build_query($qs);
}

$pageTitle = 'จัดการสินค้า';
$activeNav = 'products';
require __DIR__ . '/_header.php';
?>

<div class="toolbar">
    <h1 style="margin:0">จัดการสินค้า</h1>
    <a class="btn" href="product_form.php">+ เพิ่มสินค้าใหม่</a>
</div>

<form class="search-bar" method="get">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="ค้นหาชื่อ / แบรนด์ / โน้ตกลิ่น">
    <select name="cat">
        <option value="">ทุกหมวดหมู่</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (string)$cat === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
        <option value="none" <?= $cat === 'none' ? 'selected' : '' ?>>(ไม่มีหมวดหมู่)</option>
    </select>
    <select name="stock">
        <option value="">สต็อกทั้งหมด</option>
        <option value="low" <?= $stockF === 'low' ? 'selected' : '' ?>>ใกล้หมด (1–<?= LOW_STOCK ?>)</option>
        <option value="out" <?= $stockF === 'out' ? 'selected' : '' ?>>หมดแล้ว</option>
    </select>
    <button class="btn" type="submit">ค้นหา</button>
    <?php if ($q !== '' || $cat !== '' || $stockF !== ''): ?>
        <a class="btn btn-outline" href="products.php">ล้างตัวกรอง</a>
    <?php endif; ?>
</form>

<p style="color:var(--muted);margin:0 0 10px">พบ <?= $total ?> รายการ</p>

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>รูป</th>
            <th>ชื่อ / แบรนด์</th>
            <th>หมวดหมู่</th>
            <th class="num">ราคา</th>
            <th class="num">สต็อก</th>
            <th>จัดการ</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$products): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--muted)">ไม่พบสินค้า</td></tr>
    <?php endif; ?>
    <?php foreach ($products as $p): ?>
        <?php $cls = $p['stock'] <= 0 ? 'stock-out' : ($p['stock'] <= LOW_STOCK ? 'stock-low' : 'stock-ok'); ?>
        <tr>
            <td><?= (int)$p['id'] ?></td>
            <td><?= product_img_tag($p, $p['name'], 'thumb') ?></td>
            <td><strong><?= e($p['name']) ?></strong><br><small style="color:var(--muted)"><?= e($p['brand']) ?></small></td>
            <td><?= $p['category_name'] ? '<span class="tag">' . e($p['category_name']) . '</span>' : '<small style="color:var(--muted)">—</small>' ?></td>
            <td class="num"><?= money($p['price']) ?></td>
            <td class="num <?= $cls ?>"><?= (int)$p['stock'] ?></td>
            <td style="white-space:nowrap">
                <a class="btn btn-sm btn-outline" href="product_form.php?id=<?= (int)$p['id'] ?>">แก้ไข</a>
                <a class="btn btn-sm btn-outline" href="stock.php?product_id=<?= (int)$p['id'] ?>">สต็อก</a>
                <form class="inline-form" method="post" action="product_delete.php" onsubmit="return confirm('ยืนยันการลบสินค้านี้?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-danger" type="submit">ลบ</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($pages > 1): ?>
<div class="pager">
    <?php if ($page > 1): ?><a href="<?= e(page_url($page - 1)) ?>">&laquo;</a><?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="cur"><?= $i ?></span>
        <?php elseif ($i === 1 || $i === $pages || abs($i - $page) <= 2): ?><a href="<?= e(page_url($i)) ?>"><?= $i ?></a>
        <?php elseif (abs($i - $page) === 3): ?><span>…</span>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="<?= e(page_url($page + 1)) ?>">&raquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
