<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

$typeLabels = [
    'add'    => 'เพิ่มสต็อก',
    'remove' => 'ลดสต็อก',
    'adjust' => 'ปรับยอด',
    'sale'   => 'ขายออก',
    'cancel' => 'คืนจากการยกเลิก',
];

// ---------- บันทึกการปรับสต็อก ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $pid  = (int)($_POST['product_id'] ?? 0);
    $mode = $_POST['mode'] ?? '';
    $qty  = $_POST['quantity'] ?? '';
    $note = trim($_POST['note'] ?? '');
    if (mb_strlen($note) > 255) $note = mb_substr($note, 0, 255);

    $back = 'stock.php?product_id=' . $pid;

    if ($pid <= 0 || !in_array($mode, ['add', 'remove', 'set'], true) || !ctype_digit((string)$qty)) {
        flash('ข้อมูลไม่ถูกต้อง กรุณาเลือกสินค้าและกรอกจำนวนเป็นเลขจำนวนเต็มไม่ติดลบ', 'error');
        redirect($back);
    }
    $qty = (int)$qty;
    if ($mode !== 'set' && $qty < 1) {
        flash('จำนวนต้องมากกว่า 0', 'error');
        redirect($back);
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare('SELECT name, stock FROM products WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $cur = $stmt->get_result()->fetch_assoc();
        if (!$cur) {
            throw new RuntimeException('ไม่พบสินค้า');
        }
        $curStock = (int)$cur['stock'];

        if ($mode === 'add') {
            $change = $qty;      $type = 'add';
        } elseif ($mode === 'remove') {
            if ($qty > $curStock) {
                throw new RuntimeException('ลดสต็อกไม่ได้ เพราะมีของเหลือเพียง ' . $curStock . ' ชิ้น');
            }
            $change = -$qty;     $type = 'remove';
        } else {
            $change = $qty - $curStock;  $type = 'adjust';
        }

        if ($change === 0) {
            throw new RuntimeException('จำนวนไม่เปลี่ยนแปลง ไม่มีอะไรให้บันทึก');
        }

        $newStock = $curStock + $change;
        $stmt = $conn->prepare('UPDATE products SET stock = ? WHERE id = ?');
        $stmt->bind_param('ii', $newStock, $pid);
        $stmt->execute();

        log_stock($conn, $pid, $change, $type, $note !== '' ? $note : null);
        $conn->commit();

        flash($cur['name'] . ': สต็อก ' . $curStock . ' → ' . $newStock . ' เรียบร้อยแล้ว');
    } catch (RuntimeException $ex) {
        $conn->rollback();
        flash($ex->getMessage(), 'error');
    } catch (Throwable $ex) {
        $conn->rollback();
        flash('บันทึกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 'error');
    }
    redirect($back);
}

// ---------- แสดงหน้า ----------
$selectedId = (int)($_GET['product_id'] ?? 0);
$products = $conn->query('SELECT id, name, brand, stock FROM products ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$selected = null;
foreach ($products as $p) {
    if ((int)$p['id'] === $selectedId) $selected = $p;
}

// ประวัติ (กรองตามสินค้าที่เลือก ถ้ามี) แบ่งหน้า
$perPage = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
if ($selected) {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM stock_logs WHERE product_id = ?');
    $stmt->bind_param('i', $selectedId);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_row()[0];
} else {
    $total = (int)$conn->query('SELECT COUNT(*) FROM stock_logs')->fetch_row()[0];
}
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = 'SELECT l.*, p.name, p.brand FROM stock_logs l JOIN products p ON p.id = l.product_id '
     . ($selected ? 'WHERE l.product_id = ? ' : '')
     . 'ORDER BY l.id DESC LIMIT ? OFFSET ?';
$stmt = $conn->prepare($sql);
if ($selected) {
    $stmt->bind_param('iii', $selectedId, $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function stock_page_url(int $n): string {
    $qs = $_GET;
    $qs['page'] = $n;
    return 'stock.php?' . http_build_query($qs);
}

$pageTitle = 'จัดการสต็อก';
$activeNav = 'stock';
require __DIR__ . '/_header.php';
?>

<h1>จัดการสต็อก</h1>

<div class="panel">
    <h3>ปรับสต็อก</h3>
    <form method="post" class="row-form">
        <?= csrf_field() ?>
        <div style="flex-basis:260px">
            <label for="product_id">สินค้า</label>
            <select id="product_id" name="product_id" required onchange="location.href='stock.php?product_id='+this.value">
                <option value="">— เลือกสินค้า —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === $selectedId ? 'selected' : '' ?>>
                        <?= e($p['name']) ?> — <?= e($p['brand']) ?> (คงเหลือ <?= (int)$p['stock'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex-basis:150px">
            <label for="mode">การปรับ</label>
            <select id="mode" name="mode">
                <option value="add">เพิ่มสต็อก (+)</option>
                <option value="remove">ลดสต็อก (−)</option>
                <option value="set">ตั้งยอดคงเหลือเป็น</option>
            </select>
        </div>
        <div style="flex-basis:110px">
            <label for="quantity">จำนวน</label>
            <input type="number" id="quantity" name="quantity" min="0" step="1" required>
        </div>
        <div style="flex-basis:220px">
            <label for="note">หมายเหตุ</label>
            <input type="text" id="note" name="note" maxlength="255" placeholder="เช่น รับของเข้าล็อตใหม่ / ของแตกเสียหาย">
        </div>
        <div style="flex:0 0 auto">
            <button class="btn" type="submit">บันทึก</button>
        </div>
    </form>
    <?php if ($selected): ?>
        <p style="margin:12px 0 0;color:var(--muted)">กำลังดู: <strong><?= e($selected['name']) ?></strong> คงเหลือ <?= (int)$selected['stock'] ?> ชิ้น
            · <a href="stock.php">ดูประวัติทุกสินค้า</a></p>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>ประวัติการเปลี่ยนแปลงสต็อก <small style="color:var(--muted);font-size:.8rem">(<?= $total ?> รายการ)</small></h3>
    <div class="table-wrap">
    <table>
        <thead>
            <tr><th>วันเวลา</th><th>สินค้า</th><th>ประเภท</th><th class="num">เปลี่ยนแปลง</th><th>หมายเหตุ</th></tr>
        </thead>
        <tbody>
        <?php if (!$logs): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--muted)">ยังไม่มีประวัติ</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
                <td><a href="stock.php?product_id=<?= (int)$l['product_id'] ?>"><?= e($l['name']) ?></a><br><small style="color:var(--muted)"><?= e($l['brand']) ?></small></td>
                <td><span class="tag"><?= e($typeLabels[$l['type']] ?? $l['type']) ?></span></td>
                <td class="num <?= $l['change_amount'] > 0 ? 'log-plus' : 'log-minus' ?>"><?= $l['change_amount'] > 0 ? '+' : '' ?><?= (int)$l['change_amount'] ?></td>
                <td><?= e($l['note']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pager">
        <?php if ($page > 1): ?><a href="<?= e(stock_page_url($page - 1)) ?>">&laquo;</a><?php endif; ?>
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <?php if ($i === $page): ?><span class="cur"><?= $i ?></span>
            <?php elseif ($i === 1 || $i === $pages || abs($i - $page) <= 2): ?><a href="<?= e(stock_page_url($i)) ?>"><?= $i ?></a>
            <?php elseif (abs($i - $page) === 3): ?><span>…</span>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a href="<?= e(stock_page_url($page + 1)) ?>">&raquo;</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
