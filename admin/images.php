<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

// ---------- ดำเนินการ (POST) ----------
// หน้านี้ "ไม่ลบ" ไฟล์รูปหรือสินค้าใด ๆ แก้เฉพาะคอลัมน์ products.image
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $files  = upload_files(true);

    try {
        if ($action === 'autolink') {
            $replace = !empty($_POST['replace']);
            $rows = $conn->query('SELECT id, name, image FROM products')->fetch_all(MYSQLI_ASSOC);
            $stmt = $conn->prepare('UPDATE products SET image = ? WHERE id = ?');
            $n = 0;
            foreach ($rows as $r) {
                $match = find_matching_file($r);
                if ($match === null) continue;
                $cur = basename((string)$r['image']);
                $curOk = $cur !== '' && is_file(UPLOAD_DIR . $cur);
                if ($match === $cur) continue;
                if ($replace || !$curOk) {
                    $stmt->bind_param('si', $match, $r['id']);
                    $stmt->execute();
                    $n++;
                }
            }
            flash($n > 0 ? "เชื่อมรูปให้สินค้า $n รายการเรียบร้อยแล้ว" : 'ไม่พบไฟล์ใหม่ที่ต้องเชื่อม (ตรวจชื่อไฟล์ตามกฎด้านบนอีกครั้ง)', $n > 0 ? 'success' : 'error');

        } elseif ($action === 'assign') {
            $pid  = (int)($_POST['product_id'] ?? 0);
            $file = (string)($_POST['file'] ?? '');
            if ($file !== '' && !in_array($file, $files, true)) {
                throw new RuntimeException('ไม่พบไฟล์นี้ในโฟลเดอร์ uploads');
            }
            $val = $file === '' ? null : $file;
            $stmt = $conn->prepare('UPDATE products SET image = ? WHERE id = ?');
            $stmt->bind_param('si', $val, $pid);
            $stmt->execute();
            flash($file === '' ? 'ยกเลิกการเชื่อมรูปแล้ว (จะแสดงรูปสำรอง)' : 'เชื่อมรูปเรียบร้อยแล้ว');
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'error');
    } catch (Throwable $ex) {
        flash('ดำเนินการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 'error');
    }
    redirect('images.php');
}

$files = upload_files(true);
$products = $conn->query('SELECT p.id, p.name, p.brand, p.image FROM products p ORDER BY p.id')->fetch_all(MYSQLI_ASSOC);

$used = [];
foreach ($products as $p) {
    if ($p['image']) $used[strtolower(basename($p['image']))] = true;
}
$unused = array_values(array_filter($files, fn($f) => !isset($used[strtolower($f)])));

$uploadPath = realpath(UPLOAD_DIR) ?: UPLOAD_DIR;

$pageTitle = 'รูปภาพสินค้า';
$activeNav = 'images';
require __DIR__ . '/_header.php';
?>

<h1>รูปภาพสินค้า</h1>

<div class="panel">
    <h3>วิธีเพิ่มรูป</h3>
    <ol class="howto">
        <li>นำไฟล์รูปไปใส่ในโฟลเดอร์ <code><?= e($uploadPath) ?></code> (นามสกุล <?= e(implode(', ', IMAGE_EXTS)) ?>)</li>
        <li>ตั้งชื่อไฟล์ตามกฎด้านล่าง <strong>ใช้ภาษาอังกฤษ/ตัวเลข ไม่มีเว้นวรรค</strong> (ดูคอลัมน์ "ชื่อไฟล์ที่ต้องตั้ง" ของแต่ละสินค้า)</li>
        <li>กลับมาหน้านี้ กดปุ่ม <strong>เชื่อมรูปอัตโนมัติ</strong> หรือเลือกไฟล์ให้สินค้าด้วยตัวเองในตาราง</li>
    </ol>
    <p class="muted">กฎการตั้งชื่อ (อย่างใดอย่างหนึ่ง): <code>product-<em>ID</em>.jpg</code> เช่น <code>product-1.jpg</code> หรือ ชื่อสินค้าตัวพิมพ์เล็กคั่นด้วย <code>-</code> เช่น <code>sauvage-eau-de-parfum.jpg</code>
        · ถ้าไม่มีรูป ระบบจะแสดงรูปสำรองให้เอง · ข้อมูลสินค้าและไฟล์ที่มีอยู่จะไม่ถูกลบ</p>

    <form method="post" class="row-form" style="margin-top:14px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="autolink">
        <div style="flex:0 0 auto"><button class="btn" type="submit">เชื่อมรูปอัตโนมัติ</button></div>
        <div style="flex:1 1 280px">
            <label style="margin:0;font-weight:400"><input type="checkbox" name="replace" value="1" style="width:auto;margin-right:6px">
                แทนที่รูปเดิมด้วยไฟล์ที่ตั้งชื่อตรงกฎ (ถ้าไม่ติ๊ก จะเชื่อมเฉพาะสินค้าที่ยังไม่มีรูป)</label>
        </div>
    </form>
</div>

<div class="panel">
    <h3>สินค้าทั้งหมด (<?= count($products) ?> รายการ)</h3>
    <div class="table-wrap">
    <table>
        <thead><tr><th>ID</th><th>รูป</th><th>สินค้า</th><th>สถานะ</th><th>ชื่อไฟล์ที่ต้องตั้ง</th><th>เลือกไฟล์เอง</th></tr></thead>
        <tbody>
        <?php if (!$products): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted)">ยังไม่มีสินค้า</td></tr>
        <?php endif; ?>
        <?php foreach ($products as $p):
            $cur = basename((string)$p['image']);
            $curOk = $cur !== '' && is_file(UPLOAD_DIR . $cur);
            $match = find_matching_file($p);
            $slug = product_slug($p['name']);
        ?>
            <tr>
                <td><?= (int)$p['id'] ?></td>
                <td><?= product_img_tag($p, $p['name'], 'thumb') ?></td>
                <td><strong><?= e($p['name']) ?></strong><br><small class="muted"><?= e($p['brand']) ?></small></td>
                <td>
                    <?php if ($curOk): ?>
                        <span class="stock-ok">ใช้ไฟล์: <?= e($cur) ?></span>
                    <?php elseif ($cur !== ''): ?>
                        <span class="stock-out">ไม่พบไฟล์ <?= e($cur) ?></span><br><small class="muted">กำลังใช้รูปสำรอง</small>
                    <?php else: ?>
                        <span class="muted">ยังไม่มีรูป (ใช้รูปสำรอง)</span>
                    <?php endif; ?>
                    <?php if ($match !== null && $match !== $cur): ?>
                        <br><span class="stock-low">พบไฟล์ใหม่ที่ตรงกฎ: <?= e($match) ?> (ยังไม่ได้เชื่อม)</span>
                    <?php endif; ?>
                </td>
                <td><code>product-<?= (int)$p['id'] ?>.jpg</code>
                    <?php if ($slug !== ''): ?><br><code><?= e($slug) ?>.jpg</code><?php endif; ?></td>
                <td>
                    <form method="post" class="row-form" style="flex-wrap:nowrap">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="assign">
                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                        <select name="file" style="min-width:150px">
                            <option value="">— ไม่ใช้รูป (รูปสำรอง) —</option>
                            <?php foreach ($files as $f): ?>
                                <option value="<?= e($f) ?>" <?= strcasecmp($f, $cur) === 0 ? 'selected' : '' ?>><?= e($f) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline" type="submit">บันทึก</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="panel">
    <h3>ไฟล์ในโฟลเดอร์ uploads ที่ยังไม่ได้ใช้กับสินค้าใด (<?= count($unused) ?> ไฟล์)</h3>
    <?php if (!$files): ?>
        <p class="muted">ยังไม่มีไฟล์รูปในโฟลเดอร์ <code><?= e($uploadPath) ?></code></p>
    <?php elseif (!$unused): ?>
        <p class="muted">ทุกไฟล์ถูกใช้งานแล้ว</p>
    <?php else: ?>
        <div class="file-grid">
            <?php foreach ($unused as $f): ?>
                <div class="file-item">
                    <img src="<?= e(UPLOAD_URL . rawurlencode($f)) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='<?= e(placeholder_url()) ?>'">
                    <span><?= e($f) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
