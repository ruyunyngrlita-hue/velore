<?php
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ---------- จัดการ action (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $pid    = (int)($_POST['product_id'] ?? 0);

    // เช็คสต็อกของสินค้า
    $stock = null;
    if ($pid > 0) {
        $stmt = $conn->prepare('SELECT stock FROM products WHERE id = ?');
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stock = $row ? (int)$row['stock'] : null;
    }

    switch ($action) {
        case 'add':
            if ($stock === null || $stock < 1) {
                flash(t('out_or_missing'), 'error');
                break;
            }
            $qty = max(1, (int)($_POST['quantity'] ?? 1));
            $new = ($_SESSION['cart'][$pid] ?? 0) + $qty;
            if ($new > $stock) {
                $new = $stock;
                flash(t('qty_adjusted'), 'error');
            } else {
                flash(t('added_to_cart'));
            }
            $_SESSION['cart'][$pid] = $new;
            break;

        case 'update':
            $qty = (int)($_POST['quantity'] ?? 0);
            if ($qty <= 0) {
                unset($_SESSION['cart'][$pid]);
                flash(t('removed_from_cart'));
            } elseif ($stock !== null) {
                $_SESSION['cart'][$pid] = min($qty, $stock);
                flash(t('qty_updated'));
            }
            break;

        case 'step':   // ปุ่ม + / − ทีละ 1
            $delta = (int)($_POST['delta'] ?? 0) <=> 0;   // เหลือแค่ -1, 0, 1
            $cur = $_SESSION['cart'][$pid] ?? 0;
            if ($cur > 0 && $stock !== null) {
                $new = $cur + $delta;
                if ($new <= 0) {
                    unset($_SESSION['cart'][$pid]);
                    flash(t('removed_from_cart'));
                } elseif ($new > $stock) {
                    $_SESSION['cart'][$pid] = $stock;
                    flash(t('only_stock', $stock), 'error');
                } else {
                    $_SESSION['cart'][$pid] = $new;
                }
            }
            break;

        case 'remove':
            unset($_SESSION['cart'][$pid]);
            flash(t('removed_from_cart'));
            break;
    }

    // กลับหน้าก่อนหน้า (ถ้าเพิ่มสินค้า) หรือกลับตะกร้า
    if ($action === 'add' && !empty($_SERVER['HTTP_REFERER'])) {
        $ref = $_SERVER['HTTP_REFERER'];
        $host = parse_url($ref, PHP_URL_HOST);
        if ($host === ($_SERVER['SERVER_NAME'] ?? '') || $host === ($_SERVER['HTTP_HOST'] ?? '')) {
            redirect($ref);
        }
    }
    redirect('cart.php');
}

// ---------- ดึงข้อมูลสินค้าในตะกร้า ----------
$items = [];
$total = 0;
if (!empty($_SESSION['cart'])) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($p = $res->fetch_assoc()) {
        $p['qty']      = $_SESSION['cart'][$p['id']];
        $p['subtotal'] = $p['qty'] * $p['price'];
        $total += $p['subtotal'];
        $items[] = $p;
    }
}

$pageTitle = t('cart_full');
$metaDesc = t('cart_meta');
[$sub, $vat, $shipFee, $grand] = order_totals((float)$total, 'flash');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('cart_full')) ?></h1>

<?php if (!$items): ?>
    <p><?= e(t('cart_empty')) ?> <a href="index.php"><?= e(t('start_shopping')) ?></a></p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th><?= e(t('product')) ?></th>
                <th class="num"><?= e(t('price')) ?></th>
                <th><?= e(t('qty')) ?></th>
                <th class="num"><?= e(t('line_total')) ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td>
                    <div style="display:flex;gap:12px;align-items:center">
                        <?= product_img_tag($it, $it['name'], 'thumb') ?>
                        <div>
                            <strong><?= e($it['name']) ?></strong><br>
                            <small style="color:var(--muted)"><?= e($it['brand']) ?></small>
                        </div>
                    </div>
                </td>
                <td class="num"><?= money($it['price']) ?></td>
                <td>
                    <div class="qty-step">
                        <form method="post" action="cart.php" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="step">
                            <input type="hidden" name="product_id" value="<?= (int)$it['id'] ?>">
                            <button class="step-btn" type="submit" name="delta" value="-1" aria-label="<?= e(t('decrease')) ?>">−</button>
                        </form>
                        <span class="step-qty"><?= (int)$it['qty'] ?></span>
                        <form method="post" action="cart.php" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="step">
                            <input type="hidden" name="product_id" value="<?= (int)$it['id'] ?>">
                            <button class="step-btn" type="submit" name="delta" value="1" aria-label="<?= e(t('increase')) ?>" <?= $it['qty'] >= $it['stock'] ? 'disabled' : '' ?>>+</button>
                        </form>
                    </div>
                </td>
                <td class="num"><?= money($it['subtotal']) ?></td>
                <td>
                    <form method="post" action="cart.php" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= (int)$it['id'] ?>">
                        <button class="btn btn-sm btn-danger" type="submit"><?= e(t('remove')) ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="cart-summary">
        <?php include __DIR__ . '/includes/totals.php'; ?>
        <div class="sum-hint"><?= e(t('ship_calc_note')) ?></div>
        <div style="margin-top:14px">
            <a class="btn btn-outline" href="index.php"><?= e(t('keep_shopping')) ?></a>
            <a class="btn" href="checkout.php"><?= e(t('checkout_btn')) ?></a>
        </div>
        <?php if (!customer_logged_in()): ?>
            <p class="muted" style="font-size:.88rem;margin:10px 0 0"><?= t('login_to_order') ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
