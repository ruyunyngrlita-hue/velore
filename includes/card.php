<?php
// การ์ดสินค้า ใช้ซ้ำในหน้าแรกและหน้ารายละเอียดสินค้า
// ต้องมี $p ที่มี: id, name, brand, scent_notes, price, stock, image และ (ถ้ามี) category_name
function product_card(array $p): string {
    $out  = (int)$p['stock'] <= 0;
    $low  = !$out && (int)$p['stock'] <= LOW_STOCK;
    $url  = BASE_URL . '/product.php?id=' . (int)$p['id'];

    ob_start(); ?>
    <div class="card<?= $out ? ' card-out' : '' ?>">
        <a class="card-img" href="<?= $url ?>">
            <?= product_img_tag($p, $p['name']) ?>
            <?php if ($out): ?><span class="ribbon ribbon-out"><?= e(t('sold_out')) ?></span>
            <?php elseif ($low): ?><span class="ribbon ribbon-low"><?= e(t('left_n', (int)$p['stock'])) ?></span><?php endif; ?>
            <?php if (!empty($p['category_name'])): ?><span class="ribbon ribbon-cat"><?= e($p['category_name']) ?></span><?php endif; ?>
        </a>
        <div class="card-body">
            <span class="brand"><?= e($p['brand']) ?></span>
            <a class="name" href="<?= $url ?>"><?= e($p['name']) ?></a>
            <span class="notes"><?= e($p['scent_notes']) ?></span>
            <span class="price"><?= money($p['price']) ?></span>
            <?php if (!$out): ?>
                <form method="post" action="<?= BASE_URL ?>/cart.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-block-sm" type="submit"><?= e(t('add_to_cart')) ?></button>
                </form>
            <?php else: ?>
                <button class="btn btn-block-sm" type="button" disabled><?= e(t('sold_out')) ?></button>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
