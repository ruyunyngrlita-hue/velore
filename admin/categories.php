<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $name   = trim($_POST['name'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'add' || $action === 'rename') {
            if ($name === '' || mb_strlen($name) > 100) {
                throw new RuntimeException('กรุณากรอกชื่อหมวดหมู่ (ไม่เกิน 100 ตัวอักษร)');
            }
            // ตรวจชื่อซ้ำ (ไม่สนตัวพิมพ์เล็ก/ใหญ่ ตาม collation) ยกเว้นตัวเอง
            $stmt = $conn->prepare('SELECT id FROM categories WHERE name = ? AND id <> ?');
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                throw new RuntimeException('มีหมวดหมู่ชื่อ "' . $name . '" อยู่แล้ว');
            }
            if ($action === 'add') {
                $stmt = $conn->prepare('INSERT INTO categories (name) VALUES (?)');
                $stmt->bind_param('s', $name);
                $stmt->execute();
                flash('เพิ่มหมวดหมู่เรียบร้อยแล้ว');
            } else {
                $stmt = $conn->prepare('UPDATE categories SET name = ? WHERE id = ?');
                $stmt->bind_param('si', $name, $id);
                $stmt->execute();
                flash('เปลี่ยนชื่อหมวดหมู่เรียบร้อยแล้ว');
            }
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $used = (int)$stmt->get_result()->fetch_row()[0];

            $stmt = $conn->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            flash('ลบหมวดหมู่แล้ว' . ($used > 0 ? " (สินค้า $used รายการจะกลายเป็น \"ไม่มีหมวดหมู่\")" : ''));
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'error');
    } catch (Throwable $ex) {
        flash('ดำเนินการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 'error');
    }
    redirect('categories.php');
}

$rows = $conn->query(
    'SELECT c.id, c.name, COUNT(p.id) AS product_count
     FROM categories c LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name ORDER BY c.name'
)->fetch_all(MYSQLI_ASSOC);
$uncat = (int)$conn->query('SELECT COUNT(*) FROM products WHERE category_id IS NULL')->fetch_row()[0];

$pageTitle = 'จัดการหมวดหมู่';
$activeNav = 'categories';
require __DIR__ . '/_header.php';
?>

<h1>หมวดหมู่น้ำหอม (แนวกลิ่น)</h1>

<div class="panel">
    <h3>เพิ่มหมวดหมู่ใหม่</h3>
    <form method="post" class="row-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div style="flex-basis:260px">
            <label for="new_name">ชื่อหมวดหมู่</label>
            <input type="text" id="new_name" name="name" maxlength="100" placeholder="เช่น Citrus, Aquatic, Gourmand" required>
        </div>
        <div style="flex:0 0 auto"><button class="btn" type="submit">เพิ่ม</button></div>
    </form>
</div>

<div class="panel">
    <h3>หมวดหมู่ทั้งหมด</h3>
    <div class="table-wrap">
    <table>
        <thead><tr><th>ชื่อหมวดหมู่</th><th class="num">จำนวนสินค้า</th><th>จัดการ</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--muted)">ยังไม่มีหมวดหมู่</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td>
                    <form method="post" class="row-form" style="flex-wrap:nowrap">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="rename">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="text" name="name" value="<?= e($r['name']) ?>" maxlength="100" required style="min-width:140px">
                        <button class="btn btn-sm btn-outline" type="submit">เปลี่ยนชื่อ</button>
                    </form>
                </td>
                <td class="num"><?= (int)$r['product_count'] ?></td>
                <td>
                    <form class="inline-form" method="post"
                          onsubmit="return confirm('ลบหมวดหมู่ &quot;<?= e(addslashes($r['name'])) ?>&quot; ?<?= $r['product_count'] > 0 ? ' สินค้า ' . (int)$r['product_count'] . ' รายการจะกลายเป็นไม่มีหมวดหมู่' : '' ?>');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-danger" type="submit">ลบ</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($uncat > 0): ?>
        <p style="color:var(--muted);margin:12px 0 0">มีสินค้าที่ยังไม่ระบุหมวดหมู่ <?= $uncat ?> รายการ <a href="products.php?cat=none">ดูรายการ</a></p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
