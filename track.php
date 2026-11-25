<?php
// หน้าติดตามพัสดุ (ขนส่งจำลอง) : ค้นด้วยเลขพัสดุ ไม่ต้องล็อกอิน ไม่แสดงข้อมูลส่วนตัวของผู้รับ
require_once __DIR__ . '/includes/config.php';

$no = trim($_GET['no'] ?? '');
$order = null;
if ($no !== '') {
    $stmt = $conn->prepare('SELECT id, status, shipping_company, tracking_no, shipped_at FROM orders WHERE tracking_no = ? LIMIT 1');
    $stmt->bind_param('s', $no);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if ($order) $order = courier_sync($conn, $order);
}
$carriers = shipping_companies();
$pageTitle = t('track_title');
$metaDesc = t('track_meta');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('track_title')) ?> <span class="tag"><?= e(t('sim_badge')) ?></span></h1>

<form method="get" class="search-bar" style="margin-bottom:18px">
    <input type="text" name="no" value="<?= e($no) ?>" placeholder="<?= e(t('track_enter')) ?>" required>
    <button class="btn" type="submit"><?= e(t('track_go')) ?></button>
</form>

<?php if ($no !== '' && !$order): ?>
    <div class="alert alert-error"><?= e(t('track_nf')) ?></div>
<?php elseif ($order): ?>
    <div class="form-box" style="max-width:640px">
        <p><strong><?= e($carriers[$order['shipping_company']][0] ?? '') ?></strong><br>
           <?= e(t('tracking_no')) ?>: <strong><?= e($order['tracking_no']) ?></strong><br>
           <?= e(t('track_status')) ?>: <span class="status status-<?= e($order['status']) ?>"><?= e(order_statuses()[$order['status']] ?? $order['status']) ?></span></p>
        <?php if ($order['shipped_at']): render_timeline($order['shipped_at']); else: ?>
            <p class="muted"><?= e(t('track_not_yet')) ?></p>
        <?php endif; ?>
        <p class="muted" style="font-size:.8rem"><?= e(t('ship_sim_note')) ?></p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
