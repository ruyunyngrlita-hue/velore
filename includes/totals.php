<?php
// ตารางสรุปยอด ใช้ซ้ำในตะกร้า / ชำระเงิน / ออเดอร์
// ต้องมีตัวแปร $sub, $vat, $shipFee, $grand   (ตั้ง $totalsFinal = true ถ้าเป็นออเดอร์ที่สั่งแล้ว)
$totalsFinal = !empty($totalsFinal);
if ($totalsFinal && $vat <= 0 && $shipFee <= 0): ?>
<div class="totals"><div class="sum-line sum-grand"><strong><?= e(t('total')) ?></strong><strong><?= money($grand) ?></strong></div></div>
<?php return; endif; ?>
<div class="totals" id="totals-box" data-sub="<?= e($sub) ?>" data-vat="<?= e($vat) ?>" data-free="<?= (int)FREE_SHIPPING_MIN ?>" data-incl="<?= VAT_INCLUDED ? 1 : 0 ?>">
    <div class="sum-line"><span><?= e(VAT_INCLUDED ? t('subtotal') : t('subtotal_ex')) ?></span><span><?= money($sub) ?></span></div>
    <?php if ($vat > 0 || (!$totalsFinal && VAT_RATE > 0)): ?>
        <div class="sum-line"><span><?= e(VAT_INCLUDED ? t('vat_incl', VAT_RATE) : t('vat', VAT_RATE)) ?></span><span><?= money($vat) ?></span></div>
    <?php endif; ?>
    <div class="sum-line"><span><?= e(t('shipping')) ?></span><span id="t-ship"><?= $shipFee > 0 ? money($shipFee) : e(t('free')) ?></span></div>
    <?php if (!$totalsFinal): ?>
        <div class="sum-hint"><?= e(t('ship_free_note', money(FREE_SHIPPING_MIN))) ?></div>
    <?php endif; ?>
    <div class="sum-line sum-grand"><strong><?= e(t('grand_total')) ?></strong><strong id="t-grand"><?= money($grand) ?></strong></div>
</div>
