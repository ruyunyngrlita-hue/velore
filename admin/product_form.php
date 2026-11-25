<?php
require_once __DIR__ . '/../includes/auth.php';
admin_required();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = ['name' => '', 'brand' => '', 'category_id' => '', 'scent_notes' => '', 'price' => '', 'stock' => 0, 'image' => '', 'description' => ''];

if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    if (!$found) {
        flash('ไม่พบสินค้า', 'error');
        redirect('products.php');
    }
    $p = $found;
}

$categories = get_categories($conn);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();


    $p['name']        = trim($_POST['name'] ?? '');
    $p['brand']       = trim($_POST['brand'] ?? '');
    $p['category_id'] = $_POST['category_id'] ?? '';
    $p['scent_notes'] = trim($_POST['scent_notes'] ?? '');
    $p['price']       = $_POST['price'] ?? '';
    $p['stock']       = $_POST['stock'] ?? 0;
    $p['description'] = trim($_POST['description'] ?? '');

    if ($p['name'] === '')  $errors[] = 'กรุณากรอกชื่อสินค้า';
    if ($p['brand'] === '') $errors[] = 'กรุณากรอกแบรนด์';
    if (!is_numeric($p['price']) || $p['price'] < 0) $errors[] = 'ราคาไม่ถูกต้อง';
    if (!is_numeric($p['stock']) || (int)$p['stock'] < 0) $errors[] = 'จำนวนสต็อกไม่ถูกต้อง';

    // หมวดหมู่ต้องมีอยู่จริง (หรือเว้นว่าง)
    $categoryId = null;
    if ($p['category_id'] !== '') {
        if (!in_array((int)$p['category_id'], array_map('intval', array_column($categories, 'id')), true)) {
            $errors[] = 'หมวดหมู่ไม่ถูกต้อง';
        } else {
            $categoryId = (int)$p['category_id'];
        }
    }

    // ---------- อัปโหลดรูป ----------
    $newImage = null;
    if (!empty($_FILES['image']['name'])) {
        $f = $_FILES['image'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'อัปโหลดรูปไม่สำเร็จ (รหัสข้อผิดพลาด ' . (int)$f['error'] . ')';
        } elseif ($f['size'] > MAX_UPLOAD_SIZE) {
            $errors[] = 'ไฟล์รูปต้องมีขนาดไม่เกิน 2MB';
        } else {
            // ตรวจชนิดไฟล์จากเนื้อหาจริง ไม่เชื่อนามสกุลที่ผู้ใช้ส่งมา
            $info = @getimagesize($f['tmp_name']);
            $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
            if (!$info || !isset($allowed[$info[2]])) {
                $errors[] = 'รองรับเฉพาะไฟล์ JPG, PNG, WEBP';
            } else {
                $newImage = bin2hex(random_bytes(12)) . '.' . $allowed[$info[2]];
            }
        }
    }

    if (!$errors && $newImage !== null) {
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        if (!move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $newImage)) {
            $errors[] = 'ไม่สามารถบันทึกไฟล์รูปได้ ตรวจสอบสิทธิ์โฟลเดอร์ uploads';
        }
    }

    if (!$errors) {
        $price = (float)$p['price'];
        $stock = (int)$p['stock'];
        $oldImage = $id > 0 ? $found['image'] : null;
        $imageToSave = $newImage ?? $oldImage;

        try {
            $conn->begin_transaction();

            if ($id > 0) {
                // ล็อกแถวและอ่านสต็อกล่าสุด กันชนกับออเดอร์ที่เข้ามาพร้อมกัน
                $s = $conn->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
                $s->bind_param('i', $id);
                $s->execute();
                $curStock = (int)$s->get_result()->fetch_row()[0];

                $stmt = $conn->prepare('UPDATE products SET name=?, brand=?, category_id=?, scent_notes=?, price=?, stock=?, image=?, description=? WHERE id=?');
                $stmt->bind_param('ssisdissi', $p['name'], $p['brand'], $categoryId, $p['scent_notes'], $price, $stock, $imageToSave, $p['description'], $id);
                $stmt->execute();

                if ($stock !== $curStock) {
                    log_stock($conn, $id, $stock - $curStock, 'adjust', 'แก้ไขจากหน้าแก้ไขสินค้า (' . $curStock . ' → ' . $stock . ')');
                }
            } else {
                $stmt = $conn->prepare('INSERT INTO products (name, brand, category_id, scent_notes, price, stock, image, description) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->bind_param('ssisdiss', $p['name'], $p['brand'], $categoryId, $p['scent_notes'], $price, $stock, $imageToSave, $p['description']);
                $stmt->execute();
                $newId = $conn->insert_id;
                if ($stock > 0) {
                    log_stock($conn, $newId, $stock, 'add', 'สต็อกตั้งต้นตอนเพิ่มสินค้า');
                }
            }
            $conn->commit();
        } catch (Throwable $ex) {
            $conn->rollback();
            if ($newImage !== null && file_exists(UPLOAD_DIR . $newImage)) {
                @unlink(UPLOAD_DIR . $newImage);
            }
            $errors[] = 'บันทึกไม่สำเร็จ: ' . $ex->getMessage();
        }

        if (!$errors) {
            // ลบรูปเก่าเมื่อมีการเปลี่ยนรูป (หลังบันทึกสำเร็จเท่านั้น)
            if ($id > 0 && $newImage !== null && $oldImage && file_exists(UPLOAD_DIR . $oldImage)) {
                @unlink(UPLOAD_DIR . $oldImage);
            }
            flash($id > 0 ? 'แก้ไขสินค้าเรียบร้อยแล้ว' : 'เพิ่มสินค้าเรียบร้อยแล้ว');
            redirect('products.php');
        }
    }
}

$pageTitle = $id > 0 ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า';
$activeNav = 'products';
require __DIR__ . '/_header.php';
?>

<h1><?= $id > 0 ? 'แก้ไขสินค้า' : 'เพิ่มสินค้าใหม่' ?></h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="form-box">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <label for="name">ชื่อน้ำหอม</label>
    <input type="text" id="name" name="name" value="<?= e($p['name']) ?>" required>

    <label for="brand">แบรนด์</label>
    <input type="text" id="brand" name="brand" value="<?= e($p['brand']) ?>" required>

    <label for="category_id">หมวดหมู่ (แนวกลิ่น)</label>
    <select id="category_id" name="category_id">
        <option value="">— ไม่ระบุ —</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (string)$p['category_id'] === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <small style="color:var(--muted)">เพิ่มหมวดใหม่ได้ที่เมนู <a href="categories.php">หมวดหมู่</a></small>

    <label for="scent_notes">โน้ตกลิ่น (คั่นด้วยจุลภาค)</label>
    <input type="text" id="scent_notes" name="scent_notes" value="<?= e($p['scent_notes']) ?>" placeholder="เช่น Bergamot, Cedar, Vanilla">

    <label for="price">ราคา (บาท)</label>
    <input type="number" id="price" name="price" step="0.01" min="0" value="<?= e($p['price']) ?>" required>

    <label for="stock">จำนวนสต็อก</label>
    <input type="number" id="stock" name="stock" min="0" value="<?= e($p['stock']) ?>" required>
    <?php if ($id > 0): ?>
        <small style="color:var(--muted)">ถ้าแก้ตัวเลขนี้ ระบบจะบันทึกประวัติสต็อกให้ (หรือใช้หน้า <a href="stock.php?product_id=<?= (int)$id ?>">สต็อก</a> เพื่อระบุเหตุผล)</small>
    <?php endif; ?>

    <label for="image">รูปสินค้า (JPG/PNG/WEBP ไม่เกิน 2MB)</label>
    <?php if ($p['image']): ?>
        <p><?= product_img_tag($p + ['id' => $id], $p['name'], 'thumb thumb-lg') ?> <small>รูปปัจจุบัน (เลือกไฟล์ใหม่เพื่อเปลี่ยน)</small></p>
    <?php endif; ?>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">

    <label for="description">รายละเอียด</label>
    <textarea id="description" name="description"><?= e($p['description']) ?></textarea>

    <p style="margin-top:20px">
        <button class="btn" type="submit">บันทึก</button>
        <a class="btn btn-outline" href="products.php">ยกเลิก</a>
    </p>
</form>

<?php require __DIR__ . '/_footer.php'; ?>
