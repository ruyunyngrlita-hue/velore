<?php
require_once __DIR__ . '/includes/config.php';

// ต้องเป็นสมาชิกและล็อกอินก่อนสั่งซื้อ (ตะกร้ายังอยู่หลังล็อกอิน)
customer_required('checkout.php');
$me = current_customer($conn);
if (!$me) {
    customer_required('checkout.php');
}

if (empty($_SESSION['cart'])) {
    flash(t('cart_empty_msg'), 'error');
    redirect('cart.php');
}

$errors = [];
$methods = payment_methods();

// ค่าเริ่มต้นของฟอร์ม = ข้อมูลในบัญชีสมาชิก (แก้ไขเฉพาะออเดอร์นี้ได้)
$old = [
    'customer_name'  => $me['name'],
    'phone'          => $me['phone'],
    'address'        => (string)$me['address'],
    'lat'            => $me['lat'] ?? null,
    'lng'            => $me['lng'] ?? null,
    'carrier'        => default_carrier(),
    'payment_method' => 'transfer',
    'save_profile'   => ($me['address'] === null || trim((string)$me['address']) === '') ? '1' : '',
];

function load_cart_items($conn) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($p = $res->fetch_assoc()) {
        $p['qty'] = $_SESSION['cart'][$p['id']];
        $items[] = $p;
    }
    return $items;
}

$items = load_cart_items($conn);
if (!$items) {
    $_SESSION['cart'] = [];
    flash(t('cart_items_missing'), 'error');
    redirect('cart.php');
}

// ---------- บันทึกออเดอร์ ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old['customer_name']  = trim($_POST['customer_name'] ?? '');
    $old['phone']          = trim($_POST['phone'] ?? '');
    $old['address']        = trim($_POST['address'] ?? '');
    $old['lat']            = clean_coord($_POST['lat'] ?? null, -90, 90);
    $old['lng']            = clean_coord($_POST['lng'] ?? null, -180, 180);
    if ($old['lat'] === null || $old['lng'] === null) { $old['lat'] = $old['lng'] = null; }
    $old['carrier']        = $_POST['carrier'] ?? '';
    $old['payment_method'] = $_POST['payment_method'] ?? '';
    $old['save_profile']   = !empty($_POST['save_profile']) ? '1' : '';

    if ($old['customer_name'] === '' || mb_strlen($old['customer_name']) > 150) {
        $errors[] = t('err_recipient');
    }
    if (!preg_match('/^[0-9+\-\s]{9,20}$/', $old['phone'])) {
        $errors[] = t('err_phone');
    }
    if ($old['address'] === '') {
        $errors[] = t('err_address');
    }
    if (!isset(shipping_companies()[$old['carrier']])) {
        $errors[] = t('err_carrier');
    }
    if (!isset($methods[$old['payment_method']])) {
        $errors[] = t('err_paymethod');
    }

    if (!$errors) {
        try {
            $conn->begin_transaction();

            $total = 0;
            $lines = [];
            foreach ($items as $it) {
                // ล็อกแถวสินค้าเพื่อตรวจสต็อกและราคาล่าสุด (ไม่เชื่อราคาจากฝั่งลูกค้า)
                $stmt = $conn->prepare('SELECT name, price, stock FROM products WHERE id = ? FOR UPDATE');
                $stmt->bind_param('i', $it['id']);
                $stmt->execute();
                $cur = $stmt->get_result()->fetch_assoc();

                if (!$cur || $cur['stock'] < $it['qty']) {
                    throw new RuntimeException(t('err_stock', $cur['name'] ?? $it['name']));
                }
                $total += $cur['price'] * $it['qty'];
                $lines[] = ['id' => $it['id'], 'qty' => $it['qty'], 'price' => $cur['price']];
            }

            // โอนเงิน → รอชำระเงิน / เก็บเงินปลายทาง → เริ่มที่ "กำลังเตรียมสินค้า"
            $status = $old['payment_method'] === 'cod' ? 'preparing' : 'pending';

            [$sub, $vat, $shipFee, $grand] = order_totals((float)$total, $old['carrier']);

            $stmt = $conn->prepare('INSERT INTO orders (customer_id, customer_name, phone, address, lat, lng, subtotal, vat_amount, shipping_fee, total_price, shipping_company, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isssddddddsss', $me['id'], $old['customer_name'], $old['phone'], $old['address'], $old['lat'], $old['lng'], $sub, $vat, $shipFee, $grand, $old['carrier'], $old['payment_method'], $status);
            $stmt->execute();
            $orderId = $conn->insert_id;

            $insItem  = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
            $updStock = $conn->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            foreach ($lines as $l) {
                $insItem->bind_param('iiid', $orderId, $l['id'], $l['qty'], $l['price']);
                $insItem->execute();
                $updStock->bind_param('ii', $l['qty'], $l['id']);
                $updStock->execute();
                log_stock($conn, (int)$l['id'], -(int)$l['qty'], 'sale', 'ออเดอร์ #' . $orderId);
            }

            // บันทึกเบอร์/ที่อยู่ลงบัญชีสมาชิก ถ้าลูกค้าติ๊กไว้
            if ($old['save_profile'] === '1') {
                $stmt = $conn->prepare('UPDATE customers SET phone = ?, address = ?, lat = ?, lng = ? WHERE id = ?');
                $stmt->bind_param('ssddi', $old['phone'], $old['address'], $old['lat'], $old['lng'], $me['id']);
                $stmt->execute();
            }

            $conn->commit();

            $_SESSION['cart'] = [];
            $_SESSION['last_order_id'] = $orderId;
            redirect('order_success.php');
        } catch (RuntimeException $ex) {
            $conn->rollback();
            $errors[] = $ex->getMessage();
        } catch (Throwable $ex) {
            $conn->rollback();
            $errors[] = t('err_order_fail');
        }
        $items = load_cart_items($conn);   // โหลดใหม่ เผื่อสต็อกเปลี่ยนระหว่างที่กรอกฟอร์ม
    }
}

$total = 0;
foreach ($items as $it) {
    $total += $it['price'] * $it['qty'];
}

[$sub, $vat, $shipFee, $grand] = order_totals((float)$total, $old['carrier']);

$pageTitle = t('checkout_btn');
$metaDesc = t('checkout_meta');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('confirm_order')) ?></h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="checkout-layout">
    <form method="post" class="form-box">
        <?= csrf_field() ?>
        <h3 style="margin-top:0"><?= e(t('recipient')) ?></h3>

        <label for="customer_name"><?= e(t('recipient_name')) ?></label>
        <input type="text" id="customer_name" name="customer_name" value="<?= e($old['customer_name']) ?>" required>

        <label for="phone"><?= e(t('phone')) ?></label>
        <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" required>

        <label for="address"><?= e(t('address')) ?></label>
        <textarea id="address" name="address" required><?= e($old['address']) ?></textarea>

        <?php require_once __DIR__ . '/includes/mappicker.php'; render_map_picker($old['lat'], $old['lng']); ?>

        <label class="check-line"><input type="checkbox" name="save_profile" value="1" <?= $old['save_profile'] === '1' ? 'checked' : '' ?>>
            <?= e(t('save_to_profile')) ?></label>

        <h3><?= e(t('ship_choose')) ?></h3>
        <div class="pay-options">
            <?php foreach (shipping_companies() as $ck => $cv): $free = $sub >= FREE_SHIPPING_MIN; ?>
                <label class="pay-option">
                    <input type="radio" name="carrier" value="<?= e($ck) ?>" data-fee="<?= (int)$cv[2] ?>" <?= $old['carrier'] === $ck ? 'checked' : '' ?>>
                    <span><strong><?= e($cv[0]) ?></strong>
                    <small><?= e(t('ship_days', $cv[3])) ?> · <?= $free ? e(t('free')) : money($cv[2]) ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="muted" style="font-size:.8rem;margin:6px 0 0"><?= e(t('ship_sim_note')) ?></p>

        <h3><?= e(t('pay_method')) ?></h3>
        <div class="pay-options">
            <?php foreach ($methods as $key => $label): ?>
                <label class="pay-option">
                    <input type="radio" name="payment_method" value="<?= e($key) ?>" <?= $old['payment_method'] === $key ? 'checked' : '' ?>>
                    <span><strong><?= e($label) ?></strong>
                    <small><?= e($key === 'transfer' ? t('pay_transfer_hint') : t('pay_cod_hint')) ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>

        <p style="margin-top:20px"><button class="btn" type="submit"><?= e(t('place_order')) ?></button>
        <a class="btn btn-outline" href="cart.php"><?= e(t('back_to_cart')) ?></a></p>
    </form>

    <div class="form-box">
        <h3 style="margin-top:0"><?= e(t('order_summary')) ?></h3>
        <?php foreach ($items as $it): ?>
            <div class="sum-line">
                <span><?= e($it['name']) ?> &times; <?= (int)$it['qty'] ?></span>
                <span><?= money($it['price'] * $it['qty']) ?></span>
            </div>
        <?php endforeach; ?>
        <hr style="border:0;border-top:1px solid var(--line)">
        <?php include __DIR__ . '/includes/totals.php'; ?>
    </div>
</div>

<script>
(function () {
    var box = document.getElementById('totals-box');
    if (!box) return;
    var sub = parseFloat(box.dataset.sub), vat = parseFloat(box.dataset.vat), free = parseFloat(box.dataset.free), incl = box.dataset.incl === '1';
    var freeText = <?= json_encode(t('free'), JSON_UNESCAPED_UNICODE) ?>;
    function fmt(n) { return '฿' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function update() {
        var r = document.querySelector('input[name=carrier]:checked');
        var fee = r ? parseFloat(r.dataset.fee) : 0;
        var ship = (sub >= free) ? 0 : fee;
        document.getElementById('t-ship').textContent = ship > 0 ? fmt(ship) : freeText;
        document.getElementById('t-grand').textContent = fmt((incl ? sub : sub + vat) + ship);
    }
    document.querySelectorAll('input[name=carrier]').forEach(function (el) { el.addEventListener('change', update); });
    update();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
