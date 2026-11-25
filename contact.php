<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = t('contact_us');
$metaDesc = t('contact_meta');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('contact_us')) ?></h1>
<p class="muted"><?= e(t('contact_intro')) ?></p>

<div class="contact-grid">
    <div class="form-box"><h3><?= e(t('c_phone')) ?></h3><p><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', SHOP_PHONE)) ?>"><?= e(SHOP_PHONE) ?></a></p></div>
    <div class="form-box"><h3><?= e(t('email')) ?></h3><p><a href="mailto:<?= e(SHOP_EMAIL) ?>"><?= e(SHOP_EMAIL) ?></a></p></div>
    <div class="form-box"><h3>LINE</h3><p><?= e(SHOP_LINE) ?></p></div>
    <div class="form-box"><h3><?= e(t('c_address')) ?></h3><p><?= e(SHOP_ADDRESS) ?></p></div>
    <div class="form-box"><h3><?= e(t('c_hours')) ?></h3><p><?= e(SHOP_HOURS) ?></p></div>
</div>

<p style="margin-top:24px"><a class="btn btn-gold" href="<?= BASE_URL ?>/index.php"><?= e(t('shop_now')) ?></a></p>

<?php require __DIR__ . '/includes/footer.php'; ?>
