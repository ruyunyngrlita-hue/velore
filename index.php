<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/card.php';

$categories = get_categories($conn);
$selected = (int)($_GET['cat'] ?? 0);
if (!in_array($selected, array_map('intval', array_column($categories, 'id')), true)) {
    $selected = 0;
}
$q = trim($_GET['q'] ?? '');

$sortOptions = [
    'new'        => [t('sort_new'), 'p.id DESC'],
    'price_asc'  => [t('sort_price_asc'), 'p.price ASC, p.id DESC'],
    'price_desc' => [t('sort_price_desc'), 'p.price DESC, p.id DESC'],
    'name'       => [t('sort_name'), 'p.name ASC'],
];
$sort = $_GET['sort'] ?? 'new';
if (!isset($sortOptions[$sort])) $sort = 'new';

// ---------- ดึงสินค้า ----------
$where = [];
$types = '';
$params = [];
if ($selected > 0) {
    $where[] = 'p.category_id = ?';
    $types .= 'i';
    $params[] = $selected;
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.scent_notes LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$grouped = ($selected === 0 && $q === '' && $sort === 'new');   // หน้าแรกปกติ: แยกกลุ่มตามหมวด
$orderSql = $grouped ? 'c.name IS NULL, c.name, p.id DESC' : $sortOptions[$sort][1];

$stmt = $conn->prepare(
    "SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id $whereSql ORDER BY $orderSql"
);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$selectedName = '';
foreach ($categories as $c) {
    if ((int)$c['id'] === $selected) $selectedName = $c['name'];
}

$pageTitle = $selectedName !== '' ? t('title_scent', $selectedName) : ($q !== '' ? t('title_search', $q) : t('site_suffix'));
$metaDesc = t('meta_home', implode(', ', array_column($categories, 'name')));
if ($selectedName !== '') {
    $metaDesc = t('meta_scent', $selectedName);
} elseif ($q !== '') {
    $metaDesc = t('meta_search', $q);
}
$showHero = ($selected === 0 && $q === '');
require __DIR__ . '/includes/header.php';

function shop_url(array $override = []): string {
    $qs = array_merge($_GET, $override);
    foreach ($qs as $k => $v) if ($v === '' || $v === 0 || $v === '0') unset($qs[$k]);
    return BASE_URL . '/index.php' . ($qs ? '?' . http_build_query($qs) : '');
}
?>

<?php if ($showHero): ?>
<section class="hero-banner">
    <div class="hero-text">
        <span class="hero-kicker">IMPORTED FRAGRANCE</span>
        <h1><?= e(t('hero_t1')) ?><br><?= e(t('hero_t2')) ?></h1>
        <p><?= e(t('hero_p')) ?></p>
        <a class="btn btn-gold" href="#products"><?= e(t('hero_cta')) ?></a>
    </div>
    <img class="hero-img" src="<?= BASE_URL ?>/assets/hero-bottle.svg" alt="<?= e(t('hero_alt')) ?>">
</section>
<section class="trust-strip">
    <div><strong><?= e(t('trust1_t')) ?></strong><span><?= e(t('trust1_s')) ?></span></div>
    <div><strong><?= e(t('trust2_t')) ?></strong><span><?= e(t('trust2_s')) ?></span></div>
    <div><strong><?= e(t('trust3_t')) ?></strong><span><?= e(t('trust3_s')) ?></span></div>
    <div><strong><?= e(t('trust4_t')) ?></strong><span><?= e(t('trust4_s')) ?></span></div>
</section>
<?php endif; ?>

<?php if ($showHero):
    $promos = array_values(array_filter(require __DIR__ . '/includes/promotions.php',
        fn($p) => empty($p['guest_only']) || !customer_logged_in()));
    if ($promos): ?>
<section class="promo-grid" aria-label="<?= e(t('promo_label')) ?>">
    <?php foreach ($promos as $pr): ?>
        <div class="promo">
            <span class="promo-tag"><?= e($pr['tag']) ?></span>
            <h3><?= e($pr['title']) ?></h3>
            <p><?= e($pr['text']) ?></p>
            <a href="<?= e(BASE_URL . '/' . $pr['link']) ?>"><?= e($pr['cta']) ?> &rarr;</a>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; endif; ?>

<div id="products" class="shop-head">
    <div class="filter-bar">
        <a class="chip <?= $selected === 0 ? 'active' : '' ?>" href="<?= e(shop_url(['cat' => 0])) ?>"><?= e(t('all')) ?></a>
        <?php foreach ($categories as $c): ?>
            <a class="chip <?= $selected === (int)$c['id'] ? 'active' : '' ?>" href="<?= e(shop_url(['cat' => (int)$c['id']])) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <form class="sort-form" method="get" action="<?= BASE_URL ?>/index.php">
        <?php if ($selected): ?><input type="hidden" name="cat" value="<?= (int)$selected ?>"><?php endif; ?>
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
        <label for="sort" class="sr"><?= e(t('sort_by')) ?></label>
        <select id="sort" name="sort" onchange="this.form.submit()">
            <?php foreach ($sortOptions as $k => [$label]): ?>
                <option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-sm" type="submit"><?= e(t('sort_go')) ?></button></noscript>
    </form>
</div>

<?php if ($q !== ''): ?>
    <p class="result-note"><?= e(t('search_result', $q, count($products))) ?>
        · <a href="<?= e(shop_url(['q' => ''])) ?>"><?= e(t('clear_search')) ?></a></p>
<?php endif; ?>

<?php if (!$products): ?>
    <div class="empty-state">
        <p><?= e(t('no_match')) ?></p>
        <a class="btn btn-outline" href="<?= BASE_URL ?>/index.php"><?= e(t('view_all')) ?></a>
    </div>
<?php elseif ($grouped): ?>
    <?php
    $groups = [];
    foreach ($products as $p) $groups[$p['category_name'] ?? t('other')][] = $p;
    foreach ($groups as $groupName => $list): ?>
        <h2><?= e($groupName) ?></h2>
        <div class="grid">
            <?php foreach ($list as $p) echo product_card($p); ?>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="grid">
        <?php foreach ($products as $p) echo product_card($p); ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
