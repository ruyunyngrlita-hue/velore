<?php
require_once __DIR__ . '/includes/config.php';
customer_required('orders.php');

$orderId = (int)($_SESSION['last_order_id'] ?? 0);
if ($orderId <= 0) {
    redirect('orders.php');
}

// แสดงเฉพาะออเดอร์ของสมาชิกที่ล็อกอินอยู่
$stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? AND customer_id = ?');
$stmt->bind_param('ii', $orderId, $_SESSION['customer_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) {
    redirect('orders.php');
}

$stmt = $conn->prepare(
    'SELECT oi.quantity, oi.price, p.name
     FROM order_items oi JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?'
);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$methods  = payment_methods();
$statuses = order_statuses();

$pageTitle = t('thanks');
$metaDesc = t('success_meta');
[$sub, $vat, $shipFee, $grand] = [(float)$order['subtotal'], (float)$order['vat_amount'], (float)$order['shipping_fee'], (float)$order['total_price']];
$totalsFinal = true;
require __DIR__ . '/includes/header.php';
?>

<div class="form-box" style="margin:0 auto">
    <h1><?= e(t('thanks')) ?></h1>
    <p><?= e(t('order_no_is')) ?> <strong class="order-no">#<?= (int)$order['id'] ?></strong>
       <span class="status status-<?= e($order['status']) ?>"><?= e($statuses[$order['status']] ?? $order['status']) ?></span></p>
    <?php $__c = shipping_companies()[$order['shipping_company'] ?? ''] ?? null; ?>
    <p><?= e(t('receiver')) ?>: <?= e($order['customer_name']) ?> (<?= e($order['phone']) ?>)<br>
       <?= e(t('addr_label')) ?>: <?= nl2br(e($order['address'])) ?>
       <?php if ($order['lat'] !== null): ?><br><a href="<?= e(map_link($order['lat'], $order['lng'])) ?>" target="_blank" rel="noopener"><?= e(t('pin_map')) ?></a><?php endif; ?>
       <?php if ($__c): ?><br><?= e(t('ship_via', $__c[0])) ?> (<?= e(t('ship_days', $__c[3])) ?>)<?php endif; ?></p>

    <div class="table-wrap">
    <table>
        <thead><tr><th><?= e(t('product')) ?></th><th><?= e(t('qty')) ?></th><th class="num"><?= e(t('price')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= e($it['name']) ?></td>
                <td><?= (int)$it['quantity'] ?></td>
                <td class="num"><?= money($it['price'] * $it['quantity']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/includes/totals.php'; ?>

    <div class="pay-info">
        <strong><?= e(t('pay_how', $methods[$order['payment_method']] ?? $order['payment_method'])) ?></strong>
        <?php if ($order['payment_method'] === 'transfer'): ?>
            <?php render_promptpay_box((float)$order['total_price']); ?>
            <p class="muted"><?= e(t('qr_or_manual')) ?></p>
            <p><?= t('pls_transfer', money($order['total_price'])) ?><br>
               <?= e(BANK_NAME) ?><br>
               <?= e(t('account_no')) ?> <strong><?= e(BANK_ACCOUNT_NO) ?></strong><br>
               <?= e(t('account_name')) ?> <?= e(BANK_ACCOUNT_NAME) ?></p>
            <p class="muted"><?= e(t('transfer_after')) ?></p>
        <?php else: ?>
            <p><?= t('cod_note', money($order['total_price'])) ?></p>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/includes/slip_box.php'; ?>

    <p style="margin-top:18px">
        <a class="btn" href="order.php?id=<?= (int)$order['id'] ?>"><?= e(t('track_order')) ?></a>
        <a class="btn btn-outline" href="orders.php"><?= e(t('order_history')) ?></a>
        <a class="btn btn-outline" href="index.php"><?= e(t('back_home')) ?></a>
    </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
