<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

$sales = sales_statuses_sql();   // paid, shipped, completed (ไม่รวมรอชำระ/ยกเลิก)

$period = $_GET['period'] ?? 'daily';
if (!in_array($period, ['daily', 'monthly', 'yearly'], true)) {
    $period = 'daily';
}

$thMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

// ---------- เตรียมช่วงเวลาและช่องว่างของกราฟ ----------
$buckets = [];   // key => ['label' => ..., 'revenue' => 0, 'orders' => 0]
$now = new DateTimeImmutable('today');

if ($period === 'daily') {
    $start = $now->modify('-29 days');
    $title = 'ยอดขายรายวัน (30 วันล่าสุด)';
    $sql = "SELECT DATE(created_at) AS k, SUM(total_price) AS rev, COUNT(*) AS cnt
            FROM orders WHERE status IN ($sales) AND created_at >= ? GROUP BY k";
    for ($d = $start; $d <= $now; $d = $d->modify('+1 day')) {
        $buckets[$d->format('Y-m-d')] = ['label' => $d->format('j') . ' ' . $thMonths[(int)$d->format('n')], 'revenue' => 0, 'orders' => 0];
    }
} elseif ($period === 'monthly') {
    $start = $now->modify('first day of this month')->modify('-11 months');
    $title = 'ยอดขายรายเดือน (12 เดือนล่าสุด)';
    $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS k, SUM(total_price) AS rev, COUNT(*) AS cnt
            FROM orders WHERE status IN ($sales) AND created_at >= ? GROUP BY k";
    for ($d = $start; $d <= $now; $d = $d->modify('+1 month')) {
        $buckets[$d->format('Y-m')] = ['label' => $thMonths[(int)$d->format('n')] . ' ' . $d->format('y'), 'revenue' => 0, 'orders' => 0];
    }
} else {
    $curYear = (int)$now->format('Y');
    $start = new DateTimeImmutable(($curYear - 4) . '-01-01');
    $title = 'ยอดขายรายปี (5 ปีล่าสุด)';
    $sql = "SELECT YEAR(created_at) AS k, SUM(total_price) AS rev, COUNT(*) AS cnt
            FROM orders WHERE status IN ($sales) AND created_at >= ? GROUP BY k";
    for ($y = $curYear - 4; $y <= $curYear; $y++) {
        $buckets[(string)$y] = ['label' => (string)$y, 'revenue' => 0, 'orders' => 0];
    }
}

$startStr = $start->format('Y-m-d') . ' 00:00:00';
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $startStr);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $k = (string)$r['k'];
    if (isset($buckets[$k])) {
        $buckets[$k]['revenue'] = (float)$r['rev'];
        $buckets[$k]['orders']  = (int)$r['cnt'];
    }
}

$totalRev = array_sum(array_column($buckets, 'revenue'));
$totalOrd = array_sum(array_column($buckets, 'orders'));
$maxRev   = max(1, max(array_column($buckets, 'revenue')));
$avg      = $totalOrd > 0 ? $totalRev / $totalOrd : 0;

// ---------- สินค้าขายดีในช่วงเวลาเดียวกัน ----------
$stmt = $conn->prepare(
    "SELECT p.id, p.name, p.brand, SUM(oi.quantity) AS qty, SUM(oi.quantity * oi.price) AS rev
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN products p ON p.id = oi.product_id
     WHERE o.status IN ($sales) AND o.created_at >= ?
     GROUP BY p.id, p.name, p.brand
     ORDER BY qty DESC, rev DESC LIMIT 5"
);
$stmt->bind_param('s', $startStr);
$stmt->execute();
$top = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ---------- ยอดตามสถานะ (ทุกช่วงเวลา) ----------
$byStatus = $conn->query('SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total_price),0) AS rev FROM orders GROUP BY status')->fetch_all(MYSQLI_ASSOC);
$byStatus = array_column($byStatus, null, 'status');
$statuses = order_statuses();

$pageTitle = 'รายงานยอดขาย';
$activeNav = 'reports';
require __DIR__ . '/_header.php';
?>

<h1>รายงานยอดขาย</h1>

<div class="tabs">
    <a class="chip <?= $period === 'daily' ? 'active' : '' ?>" href="reports.php?period=daily">รายวัน</a>
    <a class="chip <?= $period === 'monthly' ? 'active' : '' ?>" href="reports.php?period=monthly">รายเดือน</a>
    <a class="chip <?= $period === 'yearly' ? 'active' : '' ?>" href="reports.php?period=yearly">รายปี</a>
</div>

<div class="stat-grid">
    <div class="stat"><div class="label">ยอดขายรวมในช่วงนี้</div><div class="value"><?= money($totalRev) ?></div></div>
    <div class="stat"><div class="label">จำนวนออเดอร์</div><div class="value"><?= (int)$totalOrd ?></div></div>
    <div class="stat"><div class="label">เฉลี่ยต่อออเดอร์</div><div class="value"><?= money($avg) ?></div></div>
</div>

<div class="panel">
    <h3><?= e($title) ?></h3>
    <div class="chart" role="img" aria-label="<?= e($title) ?>">
        <?php foreach ($buckets as $b): ?>
            <?php $h = $b['revenue'] > 0 ? max(2, round($b['revenue'] / $maxRev * 84)) : 1; ?>
            <div class="bar-col" title="<?= e($b['label'] . ': ' . money($b['revenue']) . ' (' . $b['orders'] . ' ออเดอร์)') ?>">
                <?php if ($b['revenue'] > 0 && count($buckets) <= 14): ?>
                    <div class="bar-val"><?= number_format($b['revenue']) ?></div>
                <?php endif; ?>
                <div class="bar <?= $b['revenue'] > 0 ? '' : 'zero' ?>" style="height:<?= $h ?>%"></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="chart-labels">
        <?php $n = count($buckets); $i = 0; foreach ($buckets as $b): $i++; ?>
            <span><?= ($n > 14 && ($i % 3 !== 1)) ? '' : e($b['label']) ?></span>
        <?php endforeach; ?>
    </div>
    <p style="color:var(--muted);font-size:.85rem;margin:10px 0 0">นับเฉพาะออเดอร์สถานะ "ชำระเงินแล้ว", "จัดส่งแล้ว", "สำเร็จ" (ไม่รวมรอชำระเงินและยกเลิก) · เลื่อนเมาส์บนแท่งกราฟเพื่อดูรายละเอียด</p>
</div>

<div class="panel-grid">
    <div class="panel">
        <h3>ตารางตัวเลข</h3>
        <div class="table-wrap"><table>
            <thead><tr><th>ช่วงเวลา</th><th class="num">ออเดอร์</th><th class="num">ยอดขาย</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($buckets, true) as $b): ?>
                <?php if ($period === 'daily' && $b['orders'] === 0) continue; ?>
                <tr>
                    <td><?= e($b['label']) ?></td>
                    <td class="num"><?= (int)$b['orders'] ?></td>
                    <td class="num"><?= money($b['revenue']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($totalOrd === 0): ?>
                <tr><td colspan="3" style="text-align:center;color:var(--muted)">ยังไม่มียอดขายในช่วงนี้</td></tr>
            <?php endif; ?>
            </tbody>
        </table></div>
    </div>

    <div>
        <div class="panel">
            <h3>สินค้าขายดี 5 อันดับ</h3>
            <?php if (!$top): ?>
                <p style="color:var(--muted)">ยังไม่มีข้อมูล</p>
            <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>สินค้า</th><th class="num">ขายได้ (ชิ้น)</th><th class="num">ยอดเงิน</th></tr></thead>
                <tbody>
                <?php foreach ($top as $t): ?>
                    <tr>
                        <td><strong><?= e($t['name']) ?></strong><br><small style="color:var(--muted)"><?= e($t['brand']) ?></small></td>
                        <td class="num"><?= (int)$t['qty'] ?></td>
                        <td class="num"><?= money($t['rev']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h3>ออเดอร์ทั้งหมดแยกตามสถานะ</h3>
            <div class="table-wrap"><table>
                <thead><tr><th>สถานะ</th><th class="num">ออเดอร์</th><th class="num">มูลค่า</th></tr></thead>
                <tbody>
                <?php foreach ($statuses as $key => $label): ?>
                    <tr>
                        <td><span class="status status-<?= e($key) ?>"><?= e($label) ?></span></td>
                        <td class="num"><?= (int)($byStatus[$key]['cnt'] ?? 0) ?></td>
                        <td class="num"><?= money($byStatus[$key]['rev'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
