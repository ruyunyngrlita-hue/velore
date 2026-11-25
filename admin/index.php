<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

$sales = sales_statuses_sql();

$stats = $conn->query(
    "SELECT
        (SELECT COUNT(*) FROM products) AS products,
        (SELECT COUNT(*) FROM orders) AS orders,
        (SELECT COUNT(*) FROM orders WHERE status = 'pending') AS pending,
        (SELECT COALESCE(SUM(total_price),0) FROM orders WHERE status IN ($sales)) AS revenue,
        (SELECT COALESCE(SUM(total_price),0) FROM orders WHERE status IN ($sales) AND DATE(created_at) = CURDATE()) AS today_revenue,
        (SELECT COALESCE(SUM(total_price),0) FROM orders WHERE status IN ($sales)
            AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())) AS month_revenue"
)->fetch_assoc();

$low = LOW_STOCK;
$stmt = $conn->prepare('SELECT id, name, brand, stock FROM products WHERE stock <= ? ORDER BY stock ASC, name LIMIT 10');
$stmt->bind_param('i', $low);
$stmt->execute();
$lowStock = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$lowCount = (int)$conn->query("SELECT COUNT(*) FROM products WHERE stock <= $low")->fetch_row()[0];

$recent = $conn->query('SELECT id, customer_name, total_price, status, created_at FROM orders ORDER BY id DESC LIMIT 6')->fetch_all(MYSQLI_ASSOC);
$statuses = order_statuses();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/_header.php';
?>

<h1>Dashboard</h1>

<div class="stat-grid">
    <div class="stat"><div class="label">ยอดขายวันนี้</div><div class="value"><?= money($stats['today_revenue']) ?></div><div class="sub">นับเฉพาะออเดอร์ที่ชำระแล้วขึ้นไป</div></div>
    <div class="stat"><div class="label">ยอดขายเดือนนี้</div><div class="value"><?= money($stats['month_revenue']) ?></div><div class="sub">ยอดสะสมทั้งหมด <?= money($stats['revenue']) ?></div></div>
    <div class="stat"><div class="label">คำสั่งซื้อทั้งหมด</div><div class="value"><?= (int)$stats['orders'] ?></div><div class="sub">รอชำระเงิน <?= (int)$stats['pending'] ?> รายการ</div></div>
    <div class="stat"><div class="label">สินค้าทั้งหมด</div><div class="value"><?= (int)$stats['products'] ?></div><div class="sub">รายการ</div></div>
    <div class="stat <?= $lowCount > 0 ? 'warn' : '' ?>"><div class="label">สินค้าใกล้หมด (≤ <?= $low ?>)</div><div class="value"><?= $lowCount ?></div><div class="sub">รวมสินค้าที่หมดแล้ว</div></div>
</div>

<div class="panel-grid">
    <div class="panel">
        <h3>สินค้าใกล้หมด</h3>
        <?php if (!$lowStock): ?>
            <p style="color:var(--muted)">ไม่มีสินค้าใกล้หมดสต็อก</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>สินค้า</th><th class="num">คงเหลือ</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lowStock as $p): ?>
                <tr>
                    <td><strong><?= e($p['name']) ?></strong><br><small style="color:var(--muted)"><?= e($p['brand']) ?></small></td>
                    <td class="num <?= $p['stock'] <= 0 ? 'stock-out' : 'stock-low' ?>"><?= (int)$p['stock'] ?></td>
                    <td><a class="btn btn-sm btn-outline" href="stock.php?product_id=<?= (int)$p['id'] ?>">เติมสต็อก</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h3>คำสั่งซื้อล่าสุด</h3>
        <?php if (!$recent): ?>
            <p style="color:var(--muted)">ยังไม่มีคำสั่งซื้อ</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>ลูกค้า</th><th class="num">ยอด</th><th>สถานะ</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $o): ?>
                <tr>
                    <td><a href="order_view.php?id=<?= (int)$o['id'] ?>"><?= (int)$o['id'] ?></a></td>
                    <td><?= e($o['customer_name']) ?></td>
                    <td class="num"><?= money($o['total_price']) ?></td>
                    <td><span class="status status-<?= e($o['status']) ?>"><?= e($statuses[$o['status']] ?? $o['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p style="margin:12px 0 0"><a href="orders.php">ดูคำสั่งซื้อทั้งหมด &rarr;</a></p>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
