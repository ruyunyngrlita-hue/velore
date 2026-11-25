<?php
// กล่องแนบสลิปโอนเงิน ใช้ในหน้า order.php / order_success.php  ต้องมี $order
if ($order['payment_method'] !== 'transfer' || $order['status'] === 'cancelled') return;
$hasSlip = !empty($order['slip_image']);
$canUpload = $order['status'] === 'pending';
if (!$hasSlip && !$canUpload) return;
?>
<div class="slip-box">
    <h4><?= e(t('slip_title')) ?></h4>
    <?php if ($hasSlip): ?>
        <p class="slip-ok">✓ <?= e(t('slip_received', date('d/m/Y H:i', strtotime($order['slip_uploaded_at'])))) ?></p>
        <p><a href="<?= BASE_URL ?>/slip.php?id=<?= (int)$order['id'] ?>" target="_blank" rel="noopener"><?= e(t('slip_view')) ?></a></p>
    <?php endif; ?>
    <?php if ($canUpload): ?>
        <form method="post" action="<?= BASE_URL ?>/upload_slip.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
            <label for="slip-<?= (int)$order['id'] ?>" class="sr"><?= e(t('slip_title')) ?></label>
            <input type="file" id="slip-<?= (int)$order['id'] ?>" name="slip" accept="image/jpeg,image/png,image/webp" required>
            <small class="muted"><?= e(t('slip_hint')) ?></small>
            <p style="margin:10px 0 0"><button class="btn btn-sm" type="submit"><?= e($hasSlip ? t('slip_resend') : t('slip_send')) ?></button></p>
        </form>
    <?php endif; ?>
</div>
