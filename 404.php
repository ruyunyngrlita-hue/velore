<?php
// หน้า Error 404 (ตั้งให้ Apache เรียกใช้ผ่าน .htaccess)
require_once __DIR__ . '/includes/config.php';
http_response_code(404);
$pageTitle = t('nf_title');
$metaDesc = t('nf_meta');
require __DIR__ . '/includes/header.php';
?>

<div class="empty-state error-page">
    <p class="error-code">404</p>
    <h1><?= e(t('nf_h1')) ?></h1>
    <p><?= e(t('nf_p')) ?></p>
    <p>
        <a class="btn btn-gold" href="<?= BASE_URL ?>/index.php"><?= e(t('back_home')) ?></a>
        <a class="btn btn-outline" href="<?= BASE_URL ?>/index.php#products"><?= e(t('browse')) ?></a>
        <a class="btn btn-outline" href="<?= BASE_URL ?>/contact.php"><?= e(t('contact_us')) ?></a>
    </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
