<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/card.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();

if (!$p) {
    http_response_code(404);
    $pageTitle = t('product_nf');
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><p>' . e(t('product_nf_msg')) . '</p><a class="btn btn-outline" href="index.php">' . e(t('back_home')) . '</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// สินค้าที่เกี่ยวข้อง (หมวดเดียวกัน ไม่รวมตัวเอง สูงสุด 4 รายการ)
$related = [];
if ($p['category_id']) {
    $stmt = $conn->prepare(
        'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.category_id = ? AND p.id <> ? ORDER BY p.stock > 0 DESC, p.id DESC LIMIT 4'
    );
    $stmt->bind_param('ii', $p['category_id'], $id);
    $stmt->execute();
    $related = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$stock = (int)$p['stock'];
$pageTitle = $p['name'] . ' - ' . $p['brand'];
$desc = product_desc($p);
$metaDesc = mb_substr(trim(($desc !== '' ? $desc : ($p['brand'] . ' ' . $p['name'])) . ' ' . t('notes_prefix') . $p['scent_notes']), 0, 150);
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="breadcrumb">
    <a href="index.php"><?= e(t('home')) ?></a> ›
    <?php if ($p['category_name']): ?>
        <a href="index.php?cat=<?= (int)$p['category_id'] ?>"><?= e($p['category_name']) ?></a> ›
    <?php endif; ?>
    <span><?= e($p['name']) ?></span>
</nav>

<div class="two-col product-detail">
    <div class="product-photo">
        <?= product_img_tag($p, $p['name'], '', false) ?>
    </div>
    <div>
        <span class="brand-line"><?= e($p['brand']) ?></span>
        <h1><?= e($p['name']) ?></h1>
        <?php if ($p['category_name']): ?>
            <p><a class="chip" href="index.php?cat=<?= (int)$p['category_id'] ?>"><?= e(t('scent_of', $p['category_name'])) ?></a></p>
        <?php endif; ?>
        <p class="detail-price"><?= money($p['price']) ?></p>

        <?php if ($stock <= 0): ?>
            <p class="stock-line stock-out"><?= e(t('sold_out')) ?></p>
        <?php elseif ($stock <= LOW_STOCK): ?>
            <p class="stock-line stock-low"><?= e(t('only_left_n', $stock)) ?></p>
        <?php else: ?>
            <p class="stock-line stock-ok"><?= e(t('in_stock')) ?></p>
        <?php endif; ?>

        <?php if ($stock > 0): ?>
            <form method="post" action="cart.php" class="buy-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <label for="qty"><?= e(t('quantity')) ?></label>
                <input class="qty-input" id="qty" type="number" name="quantity" value="1" min="1" max="<?= $stock ?>">
                <button class="btn" type="submit"><?= e(t('add_to_cart')) ?></button>
            </form>
        <?php else: ?>
            <button class="btn" type="button" disabled><?= e(t('sold_out')) ?></button>
        <?php endif; ?>

        <?php if ($p['scent_notes']): ?>
            <h3 class="detail-h"><?= e(t('scent_notes')) ?></h3>
            <p class="note-tags">
                <?php foreach (array_filter(array_map('trim', explode(',', $p['scent_notes']))) as $note): ?>
                    <span class="tag"><?= e($note) ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>

        <?php if ($desc !== ''): ?>
            <h3 class="detail-h"><?= e(t('details')) ?></h3>
            <p><?= nl2br(e($desc)) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if ($related): ?>
    <h2><?= e(t('related')) ?></h2>
    <div class="grid">
        <?php foreach ($related as $r) echo product_card($r); ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
