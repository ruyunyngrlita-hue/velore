<?php
require_once __DIR__ . '/includes/config.php';
customer_required('orders.php');

$cid = (int)$_SESSION['customer_id'];
$stmt = $conn->prepare(
    'SELECT o.id, o.total_price, o.payment_method, o.status, o.created_at,
            (SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_id = o.id) AS item_count
     FROM orders o WHERE o.customer_id = ? ORDER BY o.id DESC LIMIT 100'
);
$stmt->bind_param('i', $cid);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$statuses = order_statuses();
$methods  = payment_methods();

$pageTitle = t('order_history');
$metaDesc = t('orders_meta');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('order_history')) ?></h1>

<?php if (!$orders): ?>
    <div class="empty-state">
        <p><?= e(t('no_orders')) ?></p>
        <a class="btn" href="index.php"><?= e(t('start_pick')) ?></a>
    </div>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead>
        <tr><th><?= e(t('col_no')) ?></th><th><?= e(t('col_date')) ?></th><th class="num"><?= e(t('col_items')) ?></th><th class="num"><?= e(t('col_total')) ?></th><th><?= e(t('col_payment')) ?></th><th><?= e(t('col_status')) ?></th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td><strong>#<?= (int)$o['id'] ?></strong></td>
            <td style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></td>
            <td class="num"><?= (int)$o['item_count'] ?></td>
            <td class="num"><?= money($o['total_price']) ?></td>
            <td><?= e($methods[$o['payment_method']] ?? $o['payment_method']) ?></td>
            <td><span class="status status-<?= e($o['status']) ?>"><?= e($statuses[$o['status']] ?? $o['status']) ?></span></td>
            <td><a class="btn btn-sm btn-outline" href="order.php?id=<?= (int)$o['id'] ?>"><?= e(t('view_track')) ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
