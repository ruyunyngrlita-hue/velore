<?php
require_once __DIR__ . '/config.php';
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME . ' - ' . t('site_suffix');
$metaDesc  = $metaDesc ?? t('default_desc');
$searchQ   = trim($_GET['q'] ?? '');
$isMember  = customer_logged_in();
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDesc) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Noto+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<?php if (GA_MEASUREMENT_ID !== ''): ?>
    <!-- Google Analytics 4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e(GA_MEASUREMENT_ID) ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?= e(GA_MEASUREMENT_ID) ?>');
    </script>
<?php endif; ?>
</head>
<body>
<div class="topbar"><?= e(t('topbar')) ?></div>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= BASE_URL ?>/index.php">VELORE</a>
        <form class="header-search" method="get" action="<?= BASE_URL ?>/index.php" role="search">
            <label for="hs" class="sr"><?= e(t('search_perfume')) ?></label>
            <input id="hs" type="search" name="q" value="<?= e($searchQ) ?>" placeholder="<?= e(t('search_ph')) ?>">
            <button type="submit" aria-label="<?= e(t('search_btn')) ?>"><?= e(t('search_btn')) ?></button>
        </form>
        <div class="header-user">
            <span class="lang-switch" aria-label="Language">
                <a href="<?= e(lang_switch_url('th')) ?>" class="<?= current_lang() === 'th' ? 'on' : '' ?>" hreflang="th">ไทย</a>
                <span>|</span>
                <a href="<?= e(lang_switch_url('en')) ?>" class="<?= current_lang() === 'en' ? 'on' : '' ?>" hreflang="en">EN</a>
            </span>
            <?php if ($isMember): ?>
                <details class="user-menu">
                    <summary><?= e(t('hello', $_SESSION['customer_name'] ?? t('member'))) ?></summary>
                    <div class="user-menu-list">
                        <a href="<?= BASE_URL ?>/orders.php"><?= e(t('order_history')) ?></a>
                        <a href="<?= BASE_URL ?>/account.php"><?= e(t('my_account')) ?></a>
                        <a href="<?= BASE_URL ?>/logout.php"><?= e(t('logout')) ?></a>
                    </div>
                </details>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php"><?= e(t('login')) ?></a>
                <a class="btn btn-sm btn-gold" href="<?= BASE_URL ?>/register.php"><?= e(t('register')) ?></a>
            <?php endif; ?>
            <a class="cart-link" href="<?= BASE_URL ?>/cart.php"><?= e(t('cart')) ?> <span class="cart-badge"><?= cart_count() ?></span></a>
        </div>
    </div>
    <nav class="main-nav-bar">
        <div class="container main-nav">
            <a href="<?= BASE_URL ?>/index.php"><?= e(t('all')) ?></a>
            <?php foreach (get_categories($conn) as $cat): ?>
                <a href="<?= BASE_URL ?>/index.php?cat=<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
<main class="container">
<?= flash() ?>
