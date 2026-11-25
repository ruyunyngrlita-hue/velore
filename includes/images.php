<?php
// =====================================================
// VELORE - ตัวช่วยจัดการรูปสินค้า
//   - หารูปจากโฟลเดอร์ uploads/
//   - จับคู่รูปกับสินค้าจากชื่อไฟล์ (product-ID.jpg หรือ ชื่อสินค้า.jpg)
//   - ถ้าไม่มีรูป หรือไฟล์เสีย → ใช้รูปสำรอง assets/placeholder.svg
// ฟังก์ชันในไฟล์นี้ "อ่านอย่างเดียว" ไม่แก้/ลบไฟล์ใด ๆ
// =====================================================

const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];

// หา BASE_URL (เช่น /VELORE) จากตำแหน่งโฟลเดอร์โปรเจกต์เทียบกับ document root
// ทำงานได้แม้ตั้งชื่อโฟลเดอร์ใน htdocs ต่างจาก VELORE หรือพิมพ์ตัวเล็ก/ใหญ่ไม่ตรง
function detect_base_url(string $projectRoot, string $docRoot, string $fallback = '/VELORE'): string {
    $root = rtrim(str_replace('\\', '/', $projectRoot), '/');
    $doc  = rtrim(str_replace('\\', '/', $docRoot), '/');
    if ($doc === '' || $root === '') {
        return $fallback;
    }
    // ต้องอยู่ใต้ document root จริง (ตัดอักขระถัดจากส่วนที่ตรงต้องเป็น / หรือจบพอดี กันกรณี htdocs กับ htdocs2)
    if (strcasecmp(substr($root, 0, strlen($doc)), $doc) !== 0
        || (strlen($root) > strlen($doc) && $root[strlen($doc)] !== '/')) {
        return $fallback;
    }
    $base = substr($root, strlen($doc));          // '' ถ้าโปรเจกต์อยู่ที่ราก
    return $base === '' ? '' : '/' . trim($base, '/');
}

// รายชื่อไฟล์รูปทั้งหมดในโฟลเดอร์ uploads (จำผลไว้ต่อหนึ่งคำขอ)
function upload_files(bool $refresh = false): array {
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    $cache = [];
    $dir = rtrim(UPLOAD_DIR, '/\\');
    if (!is_dir($dir)) {
        return $cache;
    }
    foreach (scandir($dir) as $f) {
        if ($f === '' || $f[0] === '.') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, IMAGE_EXTS, true) && is_file($dir . '/' . $f)) {
            $cache[] = $f;
        }
    }
    natcasesort($cache);
    $cache = array_values($cache);
    return $cache;
}

// แปลงชื่อสินค้าเป็นชื่อไฟล์ตัวพิมพ์เล็ก เช่น "Terre d'Hermès" -> terre-d-hermes
function product_slug(string $name): string {
    static $map = [
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ā'=>'a',
        'ç'=>'c','ć'=>'c','č'=>'c','đ'=>'d',
        'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ě'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ī'=>'i',
        'ñ'=>'n','ň'=>'n',
        'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ō'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ū'=>'u','ů'=>'u',
        'ý'=>'y','ÿ'=>'y','ž'=>'z','ś'=>'s','š'=>'s',
    ];
    $s = strtr(mb_strtolower($name, 'UTF-8'), $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-');
}

// ชื่อไฟล์ (ไม่รวมนามสกุล) ที่ระบบจะจับคู่กับสินค้านี้ เรียงตามลำดับความสำคัญ
function image_candidates(array $p): array {
    $c = [];
    if (!empty($p['id'])) {
        $c[] = 'product-' . (int)$p['id'];
        $c[] = (string)(int)$p['id'];
    }
    if (!empty($p['name'])) {
        $slug = product_slug($p['name']);
        if ($slug !== '') $c[] = $slug;
    }
    return $c;
}

// หาไฟล์ใน uploads ที่ชื่อตรงกับกฎของสินค้านี้ (ไม่สนตัวพิมพ์เล็ก/ใหญ่) ไม่เจอคืน null
function find_matching_file(array $p): ?string {
    $files = upload_files();
    foreach (image_candidates($p) as $cand) {
        foreach (IMAGE_EXTS as $ext) {                // ลำดับนามสกุลตาม IMAGE_EXTS
            foreach ($files as $f) {
                if (strcasecmp($f, $cand . '.' . $ext) === 0) {
                    return $f;
                }
            }
        }
    }
    return null;
}

// ไฟล์รูปที่ใช้แสดงจริงของสินค้า: 1) ชื่อที่บันทึกในฐานข้อมูล (ถ้าไฟล์มีอยู่)
// 2) ไฟล์ที่ชื่อตรงกฎ  3) null (ใช้รูปสำรอง)
function product_image_file(array $p): ?string {
    $f = isset($p['image']) ? basename((string)$p['image']) : '';
    if ($f !== '' && is_file(UPLOAD_DIR . $f)) {
        return $f;
    }
    return find_matching_file($p);
}

function placeholder_url(): string {
    return BASE_URL . '/assets/placeholder.svg';
}

function product_image_url(array $p): string {
    $f = product_image_file($p);
    if ($f === null) {
        return placeholder_url();
    }
    $v = @filemtime(UPLOAD_DIR . $f) ?: 0;            // ต่อท้ายเวลาแก้ไข ให้เบราว์เซอร์โหลดใหม่เมื่อเปลี่ยนรูป
    return UPLOAD_URL . rawurlencode($f) . '?v=' . $v;
}

// แท็ก <img> พร้อมรูปสำรองอัตโนมัติ ถ้าไฟล์เสียหรือโหลดไม่ได้
function product_img_tag(array $p, string $alt = '', string $class = '', bool $lazy = true): string {
    return '<img src="' . e(product_image_url($p)) . '"'
        . ' alt="' . e($alt) . '"'
        . ($class !== '' ? ' class="' . e($class) . '"' : '')
        . ($lazy ? ' loading="lazy"' : '')
        . ' onerror="this.onerror=null;this.src=\'' . e(placeholder_url()) . '\'">';
}
