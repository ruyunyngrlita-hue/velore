</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <p class="logo-small">VELORE</p>
            <p><?= e(t('footer_tag')) ?></p>
            <p><?= e(t('tel')) ?> <a style="display:inline" href="tel:<?= e(preg_replace('/[^0-9+]/', '', SHOP_PHONE)) ?>"><?= e(SHOP_PHONE) ?></a><br>
               <a href="mailto:<?= e(SHOP_EMAIL) ?>"><?= e(SHOP_EMAIL) ?></a></p>
        </div>
        <div>
            <strong><?= e(t('scent_family')) ?></strong>
            <?php foreach (get_categories($conn) as $cat): ?>
                <a href="<?= BASE_URL ?>/index.php?cat=<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></a>
            <?php endforeach; ?>
        </div>
        <div>
            <strong><?= e(t('menu')) ?></strong>
            <a href="<?= BASE_URL ?>/index.php"><?= e(t('all_products')) ?></a>
            <a href="<?= BASE_URL ?>/cart.php"><?= e(t('cart_full')) ?></a>
            <a href="<?= BASE_URL ?>/contact.php"><?= e(t('contact_us')) ?></a>
            <a href="<?= BASE_URL ?>/privacy.php"><?= e(t('privacy')) ?></a>
            <a href="<?= BASE_URL ?>/admin/login.php"><?= e(t('admin_area')) ?></a>
        </div>
    </div>
    <p class="copyright">&copy; <?= date('Y') ?> VELORE. All rights reserved.</p>
</footer>
</body>
</html>
