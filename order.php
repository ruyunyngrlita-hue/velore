<?php
require_once __DIR__ . '/includes/config.php';
customer_required('orders.php');

$id = (int)($_GET['id'] ?? 0);

// ดูได้เฉพาะออเดอร์ของตัวเอง (ใส่เลขออเดอร์ของคนอื่นจะไม่พบ)
$stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? AND customer_id = ?');
$stmt->bind_param('ii', $id, $_SESSION['customer_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) {
    http_response_code(404);
    $pageTitle = t('order_nf');
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><p>' . e(t('order_nf_msg')) . '</p><a class="btn btn-outline" href="orders.php">' . e(t('back_orders')) . '</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$order = courier_sync($conn, $order);   // พัสดุถึงแล้ว → สำเร็จอัตโนมัติ

$stmt = $conn->prepare(
    'SELECT oi.quantity, oi.price, p.id, p.name, p.brand, p.image
     FROM order_items oi JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$statuses = order_statuses();
$methods  = payment_methods();

// ขั้นตอนที่แสดงในแถบสถานะ: โอนเงินเริ่มที่รอชำระ / เก็บเงินปลายทางเริ่มที่เตรียมสินค้า
$flow = $order['payment_method'] === 'cod'
    ? ['preparing', 'shipped', 'completed']
    : ['pending', 'paid', 'preparing', 'shipped', 'completed'];
$cancelled = $order['status'] === 'cancelled';
$pos = array_search($order['status'], $flow, true);
if ($pos === false) {
    // สถานะนอกลำดับของวิธีชำระนี้ (เช่น แอดมินเลือกข้าม) ให้ใช้ลำดับเต็มหาตำแหน่งที่ใกล้เคียง
    $full = ['pending', 'paid', 'preparing', 'shipped', 'completed'];
    $fp = array_search($order['status'], $full, true);
    $pos = 0;
    foreach ($flow as $i => $s) {
        if (array_search($s, $full, true) <= $fp) $pos = $i;
    }
}

$pageTitle = t('order_no', $order['id']);
$metaDesc = t('order_meta', $order['id']);
[$sub, $vat, $shipFee, $grand] = [(float)$order['subtotal'], (float)$order['vat_amount'], (float)$order['shipping_fee'], (float)$order['total_price']];
$totalsFinal = true;
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb"><a href="orders.php"><?= e(t('order_history')) ?></a> › <span><?= e(t('order_no', (int)$order['id'])) ?></span></nav>

<h1><?= e(t('order_no', (int)$order['id'])) ?>
    <span class="status status-<?= e($order['status']) ?>" style="font-size:.9rem;vertical-align:middle"><?= e($statuses[$order['status']] ?? $order['status']) ?></span></h1>

<?php if ($cancelled): ?>
    <div class="alert alert-error"><?= e(t('order_cancelled')) ?></div>
<?php else: ?>
    <ol class="steps">
        <?php foreach ($flow as $i => $s): ?>
            <li class="<?= $i < $pos ? 'done' : ($i === $pos ? 'current' : '') ?>">
                <span class="dot"><?= $i < $pos ? '✓' : $i + 1 ?></span>
                <span class="lbl"><?= e($statuses[$s]) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<div class="checkout-layout">
    <div>
        <div class="table-wrap">
        <table>
            <thead><tr><th><?= e(t('product')) ?></th><th class="num"><?= e(t('price_each')) ?></th><th class="num"><?= e(t('qty')) ?></th><th class="num"><?= e(t('line_total')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td>
                        <div style="display:flex;gap:12px;align-items:center">
                            <?= product_img_tag($it, $it['name'], 'thumb') ?>
                            <div><a href="product.php?id=<?= (int)$it['id'] ?>"><strong><?= e($it['name']) ?></strong></a><br>
                                <small class="muted"><?= e($it['brand']) ?></small></div>
                        </div>
                    </td>
                    <td class="num"><?= money($it['price']) ?></td>
                    <td class="num"><?= (int)$it['quantity'] ?></td>
                    <td class="num"><?= money($it['price'] * $it['quantity']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php include __DIR__ . '/includes/totals.php'; ?>
    </div>

    <div>
        <div class="form-box">
            <h3 style="margin-top:0"><?= e(t('ship_info')) ?></h3>
            <p><strong><?= e($order['customer_name']) ?></strong><br>
               <?= e(t('tel_label', $order['phone'])) ?><br>
               <?= nl2br(e($order['address'])) ?>
               <?php if ($order['lat'] !== null): ?><br><a href="<?= e(map_link($order['lat'], $order['lng'])) ?>" target="_blank" rel="noopener"><?= e(t('pin_map')) ?></a><?php endif; ?></p>
            <?php $carriers = shipping_companies(); if (!empty($order['shipping_company']) && isset($carriers[$order['shipping_company']])): $turl = tracking_url($order['shipping_company'], $order['tracking_no']); ?>
                <p><strong><?= e(t('carrier')) ?>:</strong> <?= e($carriers[$order['shipping_company']][0]) ?>
                   <?php if (!empty($order['tracking_no'])): ?><br><strong><?= e(t('tracking_no')) ?>:</strong> <?= e($order['tracking_no']) ?>
                   <?php if ($turl): ?> · <a href="<?= e($turl) ?>"><?= e(t('track_parcel')) ?></a><?php endif; endif; ?></p>
                <?php if (!empty($order['shipped_at'])): ?>
                    <div class="slip-box"><strong><?= e(t('track_title')) ?></strong> <span class="tag"><?= e(t('sim_badge')) ?></span>
                        <?php render_timeline($order['shipped_at']); ?></div>
                <?php endif; ?>
            <?php endif; ?>
            <p class="muted" style="margin-bottom:0"><?= e(t('ordered_at', date('d/m/Y H:i', strtotime($order['created_at'])))) ?></p>
        </div>

        <div class="pay-info" style="margin-top:16px">
            <strong><?= e(t('pay_how', $methods[$order['payment_method']] ?? $order['payment_method'])) ?></strong>
            <?php if ($order['payment_method'] === 'transfer' && $order['status'] === 'pending'): ?>
                <?php if ($order['status'] === 'pending') { render_promptpay_box((float)$order['total_price']); ?><p class="muted"><?= e(t('qr_or_manual')) ?></p><?php } ?>
                <p><?= t('pls_transfer', money($order['total_price'])) ?><br>
                   <?= e(BANK_NAME) ?><br><?= e(t('account_no')) ?> <strong><?= e(BANK_ACCOUNT_NO) ?></strong><br><?= e(t('account_name')) ?> <?= e(BANK_ACCOUNT_NAME) ?></p>
                <p class="muted"><?= e(t('when_verified')) ?></p>
            <?php elseif ($order['payment_method'] === 'cod'): ?>
                <p class="muted"><?= e(t('cod_cash')) ?></p>
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/includes/slip_box.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
