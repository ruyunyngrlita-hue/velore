<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

$statuses = order_statuses();
$methods  = payment_methods();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// ---------- เปลี่ยนสถานะออเดอร์ ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $newStatus = $_POST['status'] ?? '';
    $carriers  = shipping_companies();
    $company   = isset($carriers[$_POST['shipping_company'] ?? '']) ? $_POST['shipping_company'] : null;
    $tracking  = trim($_POST['tracking_no'] ?? '');
    $tracking  = $tracking !== '' ? mb_substr($tracking, 0, 100) : null;
    if (!isset($statuses[$newStatus])) {
        flash('สถานะไม่ถูกต้อง', 'error');
        redirect('order_view.php?id=' . $id);
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare('SELECT status, shipped_at, shipping_company, tracking_no FROM orders WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $cur = $stmt->get_result()->fetch_assoc();
        if (!$cur) {
            throw new RuntimeException('ไม่พบคำสั่งซื้อ');
        }

        $toCancel = $newStatus === 'cancelled' && $cur['status'] !== 'cancelled';
        $reopen   = $cur['status'] === 'cancelled' && $newStatus !== 'cancelled';

        if ($toCancel || $reopen) {
            $stmt = $conn->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($lines as $l) {
                $pid = (int)$l['product_id'];
                $qty = (int)$l['quantity'];

                $s = $conn->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
                $s->bind_param('i', $pid);
                $s->execute();
                $stock = (int)$s->get_result()->fetch_row()[0];

                if ($toCancel) {
                    $change = $qty;     // คืนสต็อก
                    $type = 'cancel';
                    $note = 'ยกเลิกออเดอร์ #' . $id;
                } else {
                    if ($stock < $qty) {
                        throw new RuntimeException('เปิดออเดอร์กลับไม่ได้ เพราะสต็อกสินค้าบางรายการไม่พอ (เหลือ ' . $stock . ' ต้องการ ' . $qty . ') กรุณาเติมสต็อกก่อน');
                    }
                    $change = -$qty;    // ตัดสต็อกอีกครั้ง
                    $type = 'sale';
                    $note = 'เปิดออเดอร์ #' . $id . ' กลับมาใช้งาน';
                }
                $u = $conn->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                $u->bind_param('ii', $change, $pid);
                $u->execute();
                log_stock($conn, $pid, $change, $type, $note);
            }
        }

        // ร้านกด "จัดส่งแล้ว" → ระบบจองขนส่งให้อัตโนมัติ (จำลอง) ออกเลขพัสดุ และเริ่มนับเวลาติดตามพัสดุ
        $shippedAt = $cur['shipped_at'];
        $booked = false;
        if ($newStatus === 'shipped' && !$shippedAt) {
            $shippedAt = date('Y-m-d H:i:s');
            $company = $company ?: ($cur['shipping_company'] ?: default_carrier());
            if (!$tracking) $tracking = courier_book($company);
            $booked = true;
        }
        $stmt = $conn->prepare('UPDATE orders SET status = ?, shipping_company = ?, tracking_no = ?, shipped_at = ? WHERE id = ?');
        $stmt->bind_param('ssssi', $newStatus, $company, $tracking, $shippedAt, $id);
        $stmt->execute();

        $conn->commit();
        flash('อัปเดตสถานะออเดอร์เรียบร้อยแล้ว' . ($toCancel ? ' (คืนสต็อกให้แล้ว)' : '') . ($booked ? ' · จองขนส่งอัตโนมัติแล้ว เลขพัสดุ ' . $tracking : ''));
    } catch (RuntimeException $ex) {
        $conn->rollback();
        flash($ex->getMessage(), 'error');
    } catch (Throwable $ex) {
        $conn->rollback();
        flash('อัปเดตสถานะไม่สำเร็จ', 'error');
    }
    redirect('order_view.php?id=' . $id);
}

$stmt = $conn->prepare(
    'SELECT o.*, c.email AS member_email, c.name AS member_name
     FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE o.id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) {
    flash('ไม่พบคำสั่งซื้อ', 'error');
    redirect('orders.php');
}
$order = courier_sync($conn, $order);   // พัสดุถึงแล้ว → สำเร็จอัตโนมัติ

$stmt = $conn->prepare(
    'SELECT oi.quantity, oi.price, p.name, p.brand
     FROM order_items oi JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'ออเดอร์ #' . $id;
$activeNav = 'orders';
require __DIR__ . '/_header.php';
?>

<div class="toolbar">
    <h1 style="margin:0">ออเดอร์ #<?= (int)$order['id'] ?></h1>
    <a class="btn btn-outline" href="orders.php">&larr; กลับรายการ</a>
</div>

<div class="checkout-layout">
    <div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>สินค้า</th><th class="num">ราคา/ชิ้น</th><th class="num">จำนวน</th><th class="num">รวม</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><strong><?= e($it['name']) ?></strong><br><small style="color:var(--muted)"><?= e($it['brand']) ?></small></td>
                    <td class="num"><?= money($it['price']) ?></td>
                    <td class="num"><?= (int)$it['quantity'] ?></td>
                    <td class="num"><?= money($it['price'] * $it['quantity']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php
        [$sub, $vat, $shipFee, $grand] = [(float)$order['subtotal'], (float)$order['vat_amount'], (float)$order['shipping_fee'], (float)$order['total_price']];
        $totalsFinal = true;
        include __DIR__ . '/../includes/totals.php';
        ?>
    </div>

    <div class="form-box">
        <h3 style="margin-top:0">ข้อมูลลูกค้า</h3>
        <p><strong><?= e($order['customer_name']) ?></strong><br>
           โทร: <?= e($order['phone']) ?><br>
           <?= nl2br(e($order['address'])) ?>
           <?php if ($order['lat'] !== null): ?><br><a href="<?= e(map_link($order['lat'], $order['lng'])) ?>" target="_blank" rel="noopener">📍 ดูตำแหน่งที่ปักหมุด</a>
               <small class="muted">(<?= e($order['lat']) ?>, <?= e($order['lng']) ?>)</small><?php endif; ?></p>
        <p><strong>ชำระเงิน:</strong> <?= e($methods[$order['payment_method']] ?? $order['payment_method']) ?><br>
           <strong>สมาชิก:</strong>
           <?php if ($order['customer_id']): ?>
               <a href="orders.php?customer=<?= (int)$order['customer_id'] ?>"><?= e($order['member_name']) ?></a>
               <small class="muted">(<?= e($order['member_email']) ?>)</small>
           <?php else: ?>
               <span class="muted">ไม่ได้สั่งผ่านบัญชีสมาชิก</span>
           <?php endif; ?></p>
        <?php if ($order['payment_method'] === 'transfer'): ?>
            <div class="slip-box">
                <strong>สลิปโอนเงิน</strong>
                <?php if (!empty($order['slip_image'])): ?>
                    <p class="slip-ok">✓ ลูกค้าแนบสลิปเมื่อ <?= e(date('d/m/Y H:i', strtotime($order['slip_uploaded_at']))) ?>
                        <?php if ($order['status'] === 'pending'): ?><br><strong>ตรวจสลิปแล้วเปลี่ยนสถานะเป็น "ชำระเงินแล้ว" ด้านล่าง</strong><?php endif; ?></p>
                    <a href="<?= BASE_URL ?>/slip.php?id=<?= (int)$order['id'] ?>" target="_blank" rel="noopener">
                        <img class="slip-thumb" src="<?= BASE_URL ?>/slip.php?id=<?= (int)$order['id'] ?>" alt="สลิปโอนเงินออเดอร์ #<?= (int)$order['id'] ?>"></a>
                <?php else: ?>
                    <p class="muted" style="margin:6px 0 0">ลูกค้ายังไม่ได้แนบสลิป</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($order['shipped_at'])): ?>
            <div class="slip-box"><strong>สถานะพัสดุ (ขนส่งจำลอง)</strong>
                <?php render_timeline($order['shipped_at']); ?>
                <a href="<?= e(tracking_url($order['shipping_company'], $order['tracking_no'])) ?>" target="_blank" rel="noopener">เปิดหน้าติดตามพัสดุของลูกค้า</a>
            </div>
        <?php endif; ?>
        <p style="color:var(--muted)">สั่งเมื่อ <?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></p>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            <label for="status">สถานะออเดอร์</label>
            <select id="status" name="status">
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="shipping_company" style="margin-top:12px">บริษัทขนส่ง</label>
            <select id="shipping_company" name="shipping_company">
                <option value="">— ยังไม่ระบุ —</option>
                <?php foreach (shipping_companies() as $k => [$cname]): ?>
                    <option value="<?= e($k) ?>" <?= ($order['shipping_company'] ?? '') === $k ? 'selected' : '' ?>><?= e($cname) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="tracking_no">เลขพัสดุ</label>
            <input type="text" id="tracking_no" name="tracking_no" maxlength="100" value="<?= e($order['tracking_no'] ?? '') ?>" placeholder="เว้นว่างไว้ ระบบจะออกเลขพัสดุให้เมื่อกด “จัดส่งแล้ว”">
            <small style="color:var(--muted)">เมื่อเลือกสถานะ “จัดส่งแล้ว” ระบบจะจองขนส่ง (จำลอง) ออกเลขพัสดุ และสถานะพัสดุเดินหน้าเองจนถึง “สำเร็จ”</small>
            <small style="color:var(--muted)">เลือก "ยกเลิก" ระบบจะคืนสต็อกและบันทึกประวัติให้อัตโนมัติ</small>
            <p style="margin-top:16px"><button class="btn" type="submit">บันทึกสถานะ</button></p>
        </form>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
