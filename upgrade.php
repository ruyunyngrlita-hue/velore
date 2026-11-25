<?php
// =====================================================
// VELORE - ตัวอัปเกรดฐานข้อมูลจากเวอร์ชัน 1 เป็นเวอร์ชัน 2
//   - เพิ่มตาราง categories, stock_logs, customers (สมาชิก) และคอลัมน์ใหม่ของ orders
//   - ตรวจตารางเดิมที่โครงสร้างไม่ตรง (เช่น คอลัมน์ชื่อ 1,2,3,4) แล้วเปลี่ยนชื่อเก็บเป็นข้อมูลสำรองให้เอง
//   - ย้ายคอลัมน์ products.scent_family -> products.category_id
//   - เพิ่มคอลัมน์ created_at ให้ products / admins
// ปลอดภัยที่จะรันซ้ำ (ข้ามขั้นตอนที่ทำไปแล้ว) ไม่ลบข้อมูลสินค้า/ออเดอร์
// รันที่ http://localhost/VELORE/upgrade.php แล้วลบไฟล์นี้ทิ้งหลังเสร็จ
// =====================================================
define('VELORE_SKIP_SCHEMA_CHECK', true);   // หน้านี้ใช้ซ่อมโครงสร้างเอง จึงไม่ต้องตรวจก่อน
require_once __DIR__ . '/includes/config.php';
unset($_SESSION['schema_ok']);              // ให้ตรวจใหม่หลังอัปเกรดเสร็จ

$steps = [];
$failed = false;

function col_exists(mysqli $c, string $table, string $col): bool {
    $stmt = $c->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->bind_param('ss', $table, $col);
    $stmt->execute();
    $stmt->bind_result($n);
    $stmt->fetch();
    $stmt->close();
    return $n > 0;
}

function fk_exists(mysqli $c, string $table, string $name): bool {
    $stmt = $c->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'");
    $stmt->bind_param('ss', $table, $name);
    $stmt->execute();
    $stmt->bind_result($n);
    $stmt->fetch();
    $stmt->close();
    return $n > 0;
}

function table_exists(mysqli $c, string $table): bool {
    $stmt = $c->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->bind_result($n);
    $stmt->fetch();
    $stmt->close();
    return $n > 0;
}

// ตารางนี้มีคอลัมน์ครบตามที่ระบบต้องการหรือไม่
function table_has_cols(mysqli $c, string $table, array $cols): bool {
    $stmt = $c->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $have = [];
    while ($r = $res->fetch_row()) $have[] = strtolower($r[0]);
    $stmt->close();
    foreach ($cols as $col) {
        if (!in_array(strtolower($col), $have, true)) return false;
    }
    return true;
}

function index_exists(mysqli $c, string $table, string $index): bool {
    $stmt = $c->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $stmt->bind_result($n);
    $stmt->fetch();
    $stmt->close();
    return $n > 0;
}

function step(string $label, callable $fn): void {
    global $steps, $failed;
    if ($failed) {
        $steps[] = [$label, 'skip', 'ข้าม เพราะขั้นตอนก่อนหน้าล้มเหลว'];
        return;
    }
    try {
        $result = $fn();
        $steps[] = [$label, $result === false ? 'skip' : 'ok', $result === false ? 'ทำไปแล้ว ข้าม' : (is_string($result) ? $result : 'สำเร็จ')];
    } catch (Throwable $ex) {
        $failed = true;
        $steps[] = [$label, 'fail', $ex->getMessage()];
    }
}

step('ตรวจตารางเดิมที่โครงสร้างไม่ตรงกับระบบ (เปลี่ยนชื่อเก็บเป็นข้อมูลสำรอง ไม่ลบ)', function () use ($conn) {
    $required = [
        'categories' => ['id', 'name'],
        'stock_logs' => ['id', 'product_id', 'change_amount', 'type', 'note', 'created_at'],
        'customers'  => ['id', 'name', 'email', 'password', 'phone', 'address', 'created_at'],
    ];
    $renamed = [];
    foreach ($required as $table => $cols) {
        if (!table_exists($conn, $table) || table_has_cols($conn, $table, $cols)) continue;
        // foreign key ย้ายตามตารางที่ถูกเปลี่ยนชื่อ จึงต้องถอดออกก่อน แล้วค่อยสร้างใหม่ในขั้นตอนถัดไป
        if ($table === 'categories' && fk_exists($conn, 'products', 'fk_products_category')) {
            $conn->query('ALTER TABLE `products` DROP FOREIGN KEY `fk_products_category`');
        }
        if ($table === 'customers' && fk_exists($conn, 'orders', 'fk_orders_customer')) {
            $conn->query('ALTER TABLE `orders` DROP FOREIGN KEY `fk_orders_customer`');
        }
        $backup = $table . '_backup_bad_' . date('YmdHis');
        $conn->query("RENAME TABLE `$table` TO `$backup`");
        $renamed[] = "$table → $backup";
    }
    return $renamed ? 'เปลี่ยนชื่อ: ' . implode(', ', $renamed) : false;
});

step('สร้างตาราง categories', function () use ($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_category_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
});

step('เพิ่มคอลัมน์ products.category_id', function () use ($conn) {
    if (col_exists($conn, 'products', 'category_id')) return false;
    $conn->query('ALTER TABLE `products` ADD COLUMN `category_id` INT UNSIGNED DEFAULT NULL AFTER `brand`');
});

step('ย้ายข้อมูล scent_family เป็น categories', function () use ($conn) {
    if (!col_exists($conn, 'products', 'scent_family')) return false;
    $conn->query("INSERT IGNORE INTO categories (name) SELECT DISTINCT scent_family FROM products WHERE scent_family IS NOT NULL AND scent_family <> ''");
    $conn->query('UPDATE products p JOIN categories c ON c.name = p.scent_family SET p.category_id = c.id');
    $conn->query('ALTER TABLE `products` DROP COLUMN `scent_family`');
    return 'ย้ายแล้ว และลบคอลัมน์ scent_family เดิมออก';
});

step('เพิ่มหมวดหมู่เริ่มต้น (ถ้ายังไม่มีหมวดใดเลย)', function () use ($conn) {
    $n = (int)$conn->query('SELECT COUNT(*) FROM categories')->fetch_row()[0];
    if ($n > 0) return false;
    $conn->query("INSERT INTO categories (name) VALUES ('Woody'),('Floral'),('Fresh'),('Oriental')");
});

step('เพิ่ม index และ foreign key ของ category_id', function () use ($conn) {
    // ถ้ามี FK อยู่แล้วต้องชี้ไปที่ตาราง categories จริง ถ้าชี้ผิดตารางให้ถอดแล้วสร้างใหม่
    $stmt = $conn->prepare("SELECT referenced_table_name FROM information_schema.referential_constraints
        WHERE constraint_schema = DATABASE() AND table_name = 'products' AND constraint_name = 'fk_products_category'");
    $stmt->execute();
    $stmt->bind_result($ref);
    $has = $stmt->fetch();
    $stmt->close();
    if ($has) {
        if (strtolower((string)$ref) === 'categories') return false;
        $conn->query('ALTER TABLE `products` DROP FOREIGN KEY `fk_products_category`');
    }
    if (!index_exists($conn, 'products', 'idx_category')) {
        $conn->query('ALTER TABLE `products` ADD KEY `idx_category` (`category_id`)');
    }
    // category_id ที่ไม่มีหมวดรองรับ (ค้างจากตารางเดิม) ตั้งเป็นว่าง เพื่อให้สร้าง foreign key ได้ ไม่ลบสินค้า
    $conn->query('UPDATE `products` SET `category_id` = NULL WHERE `category_id` IS NOT NULL AND `category_id` NOT IN (SELECT `id` FROM `categories`)');
    $conn->query('ALTER TABLE `products` ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL');
});

step('เพิ่มคอลัมน์ products.created_at', function () use ($conn) {
    if (col_exists($conn, 'products', 'created_at')) return false;
    $conn->query('ALTER TABLE `products` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
});

step('เพิ่มคอลัมน์ admins.created_at', function () use ($conn) {
    if (col_exists($conn, 'admins', 'created_at')) return false;
    $conn->query('ALTER TABLE `admins` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
});

step('สร้างตาราง stock_logs', function () use ($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS `stock_logs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED NOT NULL,
        `change_amount` INT NOT NULL,
        `type` ENUM('add','remove','adjust','sale','cancel') NOT NULL,
        `note` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_log_product` (`product_id`),
        KEY `idx_log_created` (`created_at`),
        CONSTRAINT `fk_logs_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
});

step('บันทึกสต็อกตั้งต้นให้สินค้าที่ยังไม่มีประวัติ', function () use ($conn) {
    $conn->query("INSERT INTO stock_logs (product_id, change_amount, type, note)
        SELECT p.id, p.stock, 'add', 'สต็อกตั้งต้น (ตอนอัปเกรดระบบ)'
        FROM products p
        WHERE p.stock > 0 AND NOT EXISTS (SELECT 1 FROM stock_logs s WHERE s.product_id = p.id)");
    return 'เพิ่ม ' . $conn->affected_rows . ' รายการ';
});

step('ใส่รูปขวดน้ำหอมให้สินค้าตัวอย่างที่ยังไม่มีรูป', function () use ($conn) {
    $map = [
        'Sauvage Eau de Parfum' => 'sauvage.svg', 'Terre d\'Hermès' => 'terre.svg',
        'Chance Eau Tendre' => 'chance.svg',     'J\'adore' => 'jadore.svg',
        'Acqua di Giò' => 'acqua.svg',           'Light Blue' => 'lightblue.svg',
        'Black Opium' => 'blackopium.svg',       'Shalimar' => 'shalimar.svg',
    ];
    $stmt = $conn->prepare('UPDATE products SET image = ? WHERE name = ? AND (image IS NULL OR image = \'\')');
    $n = 0;
    foreach ($map as $name => $file) {
        if (!is_file(UPLOAD_DIR . $file)) continue;   // ข้ามถ้าไฟล์รูปไม่ได้ถูกคัดลอกมา
        $stmt->bind_param('ss', $file, $name);
        $stmt->execute();
        $n += $stmt->affected_rows;
    }
    return 'ใส่รูปให้ ' . $n . ' รายการ';
});

step('เพิ่มหมวดหมู่ Citrus และสินค้าตัวอย่างอีก 4 รายการ (ข้ามถ้ามีชื่อนี้อยู่แล้ว)', function () use ($conn) {
    $conn->query("INSERT INTO categories (name) SELECT 'Citrus' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Citrus')");
    $new = [
        ['Bleu de Chanel', 'Chanel', 'Woody', 'Grapefruit, Incense, Cedar, Sandalwood', 4900, 11, 'bleu.svg', 'กลิ่นไม้ผสมซิตรัสเข้ม สุขุมและทันสมัย เหมาะกับทุกโอกาส'],
        ['Angel', 'Mugler', 'Oriental', 'Caramel, Patchouli, Vanilla, Chocolate', 3800, 7, 'angel.svg', 'กลิ่นหวานแบบกูร์มองด์ เอกลักษณ์โดดเด่น ติดทนนาน'],
        ['Neroli Portofino', 'Tom Ford', 'Citrus', 'Bergamot, Lemon, Neroli, Amber', 7200, 6, 'neroli.svg', 'ซิตรัสสดชื่นแบบริมทะเลเมดิเตอร์เรเนียน หรูและโปร่งเบา'],
        ['CK One', 'Calvin Klein', 'Citrus', 'Lemon, Green Tea, Jasmine, Musk', 2400, 25, 'ck1.svg', 'ซิตรัสสะอาดสดใส ใช้ได้ทั้งชายและหญิง เหมาะกับทุกวัน'],
    ];
    $chk = $conn->prepare('SELECT id FROM products WHERE name = ? AND brand = ?');
    $cat = $conn->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
    $ins = $conn->prepare('INSERT INTO products (name, brand, category_id, scent_notes, price, stock, image, description) VALUES (?,?,?,?,?,?,?,?)');
    $added = 0;
    foreach ($new as [$name, $brand, $catName, $notes, $price, $stock, $img, $desc]) {
        $chk->bind_param('ss', $name, $brand);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) continue;
        $cat->bind_param('s', $catName);
        $cat->execute();
        $row = $cat->get_result()->fetch_assoc();
        $cid = $row ? (int)$row['id'] : null;
        $imgName = is_file(UPLOAD_DIR . $img) ? $img : '';
        $ins->bind_param('ssisdiss', $name, $brand, $cid, $notes, $price, $stock, $imgName, $desc);
        $ins->execute();
        $pid = (int)$conn->insert_id;
        $conn->query("INSERT INTO stock_logs (product_id, change_amount, type, note) VALUES ($pid, $stock, 'add', 'สต็อกตั้งต้น (สินค้าเพิ่มตอนอัปเกรด)')");
        $added++;
    }
    return 'เพิ่มสินค้า ' . $added . ' รายการ';
});

step('สร้างตาราง customers (สมาชิก)', function () use ($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS `customers` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `email` VARCHAR(150) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(20) NOT NULL DEFAULT '',
        `address` TEXT,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_customer_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
});

step('เพิ่มคอลัมน์ orders.customer_id (ออเดอร์เดิมคงเดิม ค่าว่าง)', function () use ($conn) {
    if (col_exists($conn, 'orders', 'customer_id')) return false;
    $conn->query('ALTER TABLE `orders` ADD COLUMN `customer_id` INT UNSIGNED DEFAULT NULL AFTER `id`');
});

step('เพิ่มคอลัมน์ orders.payment_method (ออเดอร์เดิมเป็น "โอนเงิน")', function () use ($conn) {
    if (col_exists($conn, 'orders', 'payment_method')) return false;
    $conn->query("ALTER TABLE `orders` ADD COLUMN `payment_method` ENUM('transfer','cod') NOT NULL DEFAULT 'transfer' AFTER `total_price`");
});

step('เพิ่มคอลัมน์ใหม่: ภาษี, ค่าส่ง, พิกัดแผนที่, สลิป, ขนส่ง, คำอธิบายอังกฤษ', function () use ($conn) {
    $cols = [
        ['orders', 'subtotal',         "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `address`"],
        ['orders', 'vat_amount',       "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`"],
        ['orders', 'shipping_fee',     "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `vat_amount`"],
        ['orders', 'lat',              "DECIMAL(10,7) DEFAULT NULL"],
        ['orders', 'lng',              "DECIMAL(10,7) DEFAULT NULL"],
        ['orders', 'slip_image',       "VARCHAR(255) DEFAULT NULL"],
        ['orders', 'slip_uploaded_at', "DATETIME DEFAULT NULL"],
        ['orders', 'shipping_company', "VARCHAR(50) DEFAULT NULL"],
        ['orders', 'tracking_no',      "VARCHAR(100) DEFAULT NULL"],
        ['orders', 'shipped_at',       "DATETIME DEFAULT NULL"],
        ['customers', 'lat',           "DECIMAL(10,7) DEFAULT NULL"],
        ['customers', 'lng',           "DECIMAL(10,7) DEFAULT NULL"],
        ['products', 'description_en', "TEXT"],
    ];
    $n = 0;
    foreach ($cols as [$t, $c, $def]) {
        if (col_exists($conn, $t, $c)) continue;
        $conn->query("ALTER TABLE `$t` ADD COLUMN `$c` $def");
        $n++;
    }
    // ออเดอร์เก่า: ยอดก่อนภาษี = ยอดรวมเดิม (ไม่มีภาษี/ค่าส่ง) เพื่อให้ตัวเลขเดิมไม่เปลี่ยน
    $conn->query("UPDATE `orders` SET `subtotal` = `total_price` WHERE `subtotal` = 0 AND `total_price` > 0");
    return 'เพิ่ม ' . $n . ' คอลัมน์';
});

step('เพิ่มสถานะ "กำลังเตรียมสินค้า" ให้ orders.status', function () use ($conn) {
    $stmt = $conn->prepare("SELECT data_type, column_type FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'status'");
    $stmt->execute();
    $stmt->bind_result($dt, $ct);
    $stmt->fetch();
    $stmt->close();
    if (strtolower((string)$dt) !== 'enum') return false;                 // ถ้าเป็นข้อความธรรมดา ใส่ค่าใดก็ได้อยู่แล้ว
    if (strpos((string)$ct, "'preparing'") !== false) return false;
    $conn->query("ALTER TABLE `orders` MODIFY `status` ENUM('pending','paid','preparing','shipped','completed','cancelled') NOT NULL DEFAULT 'pending'");
});

step('เชื่อม orders.customer_id กับตารางสมาชิก (foreign key)', function () use ($conn) {
    if (fk_exists($conn, 'orders', 'fk_orders_customer')) return false;
    if (!index_exists($conn, 'orders', 'idx_customer')) {
        $conn->query('ALTER TABLE `orders` ADD KEY `idx_customer` (`customer_id`)');
    }
    $conn->query('UPDATE `orders` SET `customer_id` = NULL WHERE `customer_id` IS NOT NULL AND `customer_id` NOT IN (SELECT `id` FROM `customers`)');
    $conn->query('ALTER TABLE `orders` ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL');
});

step('เพิ่ม index ที่ orders (เพื่อให้รายงานเร็วขึ้น)', function () use ($conn) {
    $stmt = $conn->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_created'");
    if ((int)$stmt->fetch_row()[0] > 0) return false;
    $conn->query('ALTER TABLE `orders` ADD KEY `idx_created` (`created_at`), ADD KEY `idx_status` (`status`)');
});
step('ใส่คำอธิบายสินค้าภาษาอังกฤษ (เฉพาะที่ยังว่าง)', function () use ($conn) {
    $en = [
        'Sauvage Eau de Parfum' => 'A fresh, bold men\'s fragrance blending the warmth of woods with vanilla. Long lasting.',
        'Terre d\'Hermès' => 'Fresh orange meets mineral flint and vetiver, a fragrance inspired by nature.',
        'Chance Eau Tendre' => 'A soft, fruity-floral scent that is gentle and easy to wear every day.',
        'J\'adore' => 'A luxurious bouquet of flowers for women. Elegant and modern.',
        'Acqua di Giò' => 'A fresh marine scent that feels light, airy and clean.',
        'Light Blue' => 'A bright summer citrus fragrance, cheerful and comfortable in hot weather.',
        'Black Opium' => 'Coffee and vanilla in a sweet, mysterious and seductive scent.',
        'Shalimar' => 'A legendary oriental fragrance, warm, luxurious and rich.',
        'Bleu de Chanel' => 'A woody-citrus fragrance that is calm, refined and modern. Suits any occasion.',
        'Angel' => 'A gourmand sweet scent with a distinctive signature. Long lasting.',
        'Neroli Portofino' => 'Fresh Mediterranean-coast citrus, luxurious yet light.',
        'CK One' => 'A clean, bright citrus for men and women, easy to wear every day.'
    ];
    $stmt = $conn->prepare("UPDATE products SET description_en = ? WHERE name = ? AND (description_en IS NULL OR description_en = '')");
    $n = 0;
    foreach ($en as $name => $text) {
        $stmt->bind_param('ss', $text, $name);
        $stmt->execute();
        $n += $stmt->affected_rows;
    }
    return 'ใส่ ' . $n . ' รายการ';
});
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>อัปเกรด VELORE</title>
<style>
    body { font-family: system-ui, 'Noto Sans Thai', sans-serif; background:#faf6ef; color:#1f1b16; display:flex; justify-content:center; padding:48px 16px; }
    .box { background:#fff; border:1px solid #e7dfd0; border-radius:10px; padding:28px; max-width:640px; width:100%; }
    h1 { margin-top:0; letter-spacing:.2em; }
    ul { list-style:none; padding:0; }
    li { padding:8px 12px; border-radius:6px; margin:6px 0; background:#f6f2ea; }
    li.ok { background:#e4f3ea; } li.fail { background:#fbe9e7; color:#b3382c; }
    small { display:block; color:#7a6f60; }
    a.btn { display:inline-block; background:#a88a4e; color:#fff; padding:9px 18px; border-radius:6px; text-decoration:none; margin:6px 6px 0 0; }
</style>
</head>
<body>
<div class="box">
    <h1>VELORE UPGRADE</h1>
    <ul>
    <?php foreach ($steps as [$label, $state, $detail]): ?>
        <li class="<?= e($state) ?>"><?= e($label) ?><small><?= e($detail) ?></small></li>
    <?php endforeach; ?>
    </ul>
    <?php if ($failed): ?>
        <p style="color:#b3382c">การอัปเกรดหยุดกลางทาง ส่งข้อความสีแดงด้านบนมาให้ตรวจสอบ ข้อมูลเดิมไม่ได้ถูกลบ (ยกเว้นขั้นที่ขึ้นสำเร็จไปแล้ว) และกดรันซ้ำได้</p>
    <?php else: ?>
        <p>อัปเกรดเสร็จแล้ว <strong style="color:#b3382c">กรุณาลบไฟล์ upgrade.php และ install.php ออกจากโฟลเดอร์ VELORE</strong></p>
        <a class="btn" href="index.php">ไปหน้าร้าน</a>
        <a class="btn" href="admin/login.php">ไปหน้าแอดมิน</a>
    <?php endif; ?>
</div>
</body>
</html>
