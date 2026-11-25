<?php
// =====================================================
// สองภาษา ไทย / English  (หน้าร้านเท่านั้น หลังบ้านเป็นภาษาไทยเสมอ)
// ใช้: t('key') หรือ t('key', $arg1, $arg2) สำหรับข้อความที่มี %s
// เพิ่ม/แก้ข้อความ: แก้ในตาราง $LANG_TEXT ด้านล่าง  'key' => ['ไทย', 'English']
// =====================================================

// หลังบ้าน (โฟลเดอร์ admin) ใช้ภาษาไทยเสมอ
if (!defined('VELORE_ADMIN') && preg_match('#/admin/[^/]*$#', $_SERVER['SCRIPT_NAME'] ?? '')) {
    define('VELORE_ADMIN', true);
}

function current_lang(): string {
    if (defined('VELORE_ADMIN')) return 'th';
    return (($_SESSION['lang'] ?? 'th') === 'en') ? 'en' : 'th';
}

// ลิงก์สลับภาษา (คงหน้าและพารามิเตอร์เดิมไว้)
function lang_switch_url(string $lang): string {
    $qs = $_GET;
    $qs['lang'] = $lang;
    return strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($qs);
}

// รับ ?lang=th|en → จำไว้ใน session แล้วเด้งกลับหน้าเดิมโดยไม่มี ?lang
if (!defined('VELORE_ADMIN') && isset($_GET['lang']) && in_array($_GET['lang'], ['th', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !headers_sent()) {
        $qs = $_GET;
        unset($qs['lang']);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '', '?') . ($qs ? '?' . http_build_query($qs) : ''));
        exit;
    }
}

function t(string $key, ...$args): string {
    global $LANG_TEXT;
    $i = current_lang() === 'en' ? 1 : 0;
    $s = $LANG_TEXT[$key][$i] ?? ($LANG_TEXT[$key][0] ?? $key);
    return $args ? vsprintf($s, $args) : $s;
}

// ชื่อ/คำอธิบายสินค้าตามภาษา (อังกฤษ: ใช้ description_en ถ้ามี ไม่มีใช้ภาษาไทย)
function product_desc(array $p): string {
    if (current_lang() === 'en' && !empty($p['description_en'])) return (string)$p['description_en'];
    return (string)($p['description'] ?? '');
}

$LANG_TEXT = [
    // ---- ทั่วไป / ส่วนหัว / ส่วนท้าย ----
    'topbar'        => ['น้ำหอมนำเข้าแท้ · สั่งซื้อออนไลน์ ชำระเงินโอนหรือเก็บเงินปลายทาง', 'Authentic imported perfume · Order online · Bank transfer or cash on delivery'],
    'default_desc'  => ['VELORE ร้านน้ำหอมนำเข้าแท้ เลือกซื้อออนไลน์ได้ง่าย', 'VELORE - authentic imported perfumes, easy online shopping'],
    'site_suffix'   => ['น้ำหอมนำเข้า', 'Imported Perfume'],
    'search_ph'     => ['ค้นหาชื่อ / แบรนด์ / โน้ตกลิ่น', 'Search name / brand / scent notes'],
    'search_btn'    => ['ค้นหา', 'Search'],
    'search_perfume'=> ['ค้นหาน้ำหอม', 'Search perfumes'],
    'hello'         => ['สวัสดี, %s', 'Hello, %s'],
    'member'        => ['สมาชิก', 'Member'],
    'order_history' => ['ประวัติการสั่งซื้อ', 'Order history'],
    'my_account'    => ['ข้อมูลของฉัน', 'My account'],
    'logout'        => ['ออกจากระบบ', 'Log out'],
    'login'         => ['เข้าสู่ระบบ', 'Log in'],
    'register'      => ['สมัครสมาชิก', 'Sign up'],
    'cart'          => ['ตะกร้า', 'Cart'],
    'all'           => ['ทั้งหมด', 'All'],
    'home'          => ['หน้าแรก', 'Home'],
    'all_products'  => ['สินค้าทั้งหมด', 'All products'],
    'cart_full'     => ['ตะกร้าสินค้า', 'Shopping cart'],
    'menu'          => ['เมนู', 'Menu'],
    'scent_family'  => ['แนวกลิ่น', 'Scent families'],
    'contact_us'    => ['ติดต่อเรา', 'Contact us'],
    'privacy'       => ['นโยบายความเป็นส่วนตัว', 'Privacy policy'],
    'admin_area'    => ['สำหรับผู้ดูแลระบบ', 'Admin'],
    'footer_tag'    => ['น้ำหอมนำเข้า คัดสรรกลิ่นระดับโลก', 'Imported perfumes, curated from around the world'],
    'tel'           => ['โทร', 'Tel'],
    'lang_name'     => ['ไทย', 'EN'],
    'baht_note'     => ['฿', '฿'],

    // ---- ข้อความระบบ / flash ----
    'must_login'    => ['กรุณาเข้าสู่ระบบหรือสมัครสมาชิกก่อน', 'Please log in or sign up first'],
    'logged_out'    => ['ออกจากระบบเรียบร้อยแล้ว', 'You have been logged out'],
    'welcome_new'   => ['สมัครสมาชิกเรียบร้อยแล้ว ยินดีต้อนรับสู่ VELORE', 'Account created. Welcome to VELORE'],
    'cart_empty_msg'=> ['ตะกร้าของคุณว่างเปล่า', 'Your cart is empty'],
    'cart_items_missing'=> ['ไม่พบสินค้าในตะกร้า', 'No items found in your cart'],
    'out_or_missing'=> ['ไม่พบสินค้าหรือสินค้าหมด', 'Product not found or out of stock'],
    'qty_adjusted'  => ['จำนวนสินค้าถูกปรับตามสต็อกที่มีอยู่', 'Quantity adjusted to available stock'],
    'added_to_cart' => ['เพิ่มสินค้าลงตะกร้าแล้ว', 'Added to cart'],
    'removed_from_cart' => ['นำสินค้าออกจากตะกร้าแล้ว', 'Removed from cart'],
    'qty_updated'   => ['อัปเดตจำนวนสินค้าแล้ว', 'Quantity updated'],
    'only_stock'    => ['สินค้ามีในสต็อกเพียง %s ชิ้น', 'Only %s in stock'],

    // ---- หน้าแรก ----
    'hero_t1'       => ['กลิ่นที่เป็นคุณ', 'Your signature scent'],
    'hero_t2'       => ['คัดสรรจากทั่วโลก', 'curated from around the world'],
    'hero_p'        => ['น้ำหอมนำเข้าแท้ เลือกตามแนวกลิ่นที่ใช่ ตั้งแต่ Woody อบอุ่น Floral ละมุน Fresh สดชื่น ไปจนถึง Oriental ลึกลับ', 'Authentic imported perfumes, chosen by scent family: warm Woody, soft Floral, crisp Fresh, mysterious Oriental and bright Citrus.'],
    'hero_cta'      => ['เลือกซื้อเลย', 'Shop now'],
    'hero_alt'      => ['ขวดน้ำหอม VELORE', 'VELORE perfume bottle'],
    'trust1_t'      => ['สินค้านำเข้าแท้', 'Authentic imports'],
    'trust1_s'      => ['คัดสรรก่อนจัดจำหน่าย', 'Carefully selected'],
    'trust2_t'      => ['แพ็กอย่างดี', 'Careful packing'],
    'trust2_s'      => ['ห่อกันกระแทกทุกชิ้น', 'Every bottle cushioned'],
    'trust3_t'      => ['สั่งซื้อง่าย', 'Easy ordering'],
    'trust3_s'      => ['บันทึกที่อยู่ไว้ในบัญชี สั่งซ้ำได้เร็ว', 'Save your address for faster reorders'],
    'trust4_t'      => ['ติดตามออเดอร์', 'Order tracking'],
    'trust4_s'      => ['ดูสถานะได้ในบัญชีสมาชิก', 'Check status in your account'],
    'promo_label'   => ['โปรโมชั่น', 'Promotions'],
    'sort_new'      => ['สินค้ามาใหม่', 'Newest'],
    'sort_price_asc'=> ['ราคา: ต่ำ → สูง', 'Price: low → high'],
    'sort_price_desc'=> ['ราคา: สูง → ต่ำ', 'Price: high → low'],
    'sort_name'     => ['ชื่อ A → Z', 'Name A → Z'],
    'sort_by'       => ['เรียงตาม', 'Sort by'],
    'sort_go'       => ['เรียง', 'Sort'],
    'title_scent'   => ['น้ำหอมแนว %s', '%s perfumes'],
    'title_search'  => ['ค้นหา: %s', 'Search: %s'],
    'meta_home'     => ['VELORE ร้านน้ำหอมนำเข้า คัดสรรกลิ่นระดับโลก แยกตามแนวกลิ่น %s', 'VELORE - imported perfumes curated from around the world, by scent family: %s'],
    'meta_scent'    => ['เลือกซื้อน้ำหอมแนว %s นำเข้าแท้จาก VELORE ดูราคา โน้ตกลิ่น และรายละเอียดสินค้าทุกรุ่น', 'Shop authentic imported %s perfumes at VELORE. See prices, scent notes and details.'],
    'meta_search'   => ['ผลการค้นหา "%s" ในร้านน้ำหอมนำเข้า VELORE', 'Search results for "%s" at VELORE imported perfumes'],
    'search_result' => ['ผลการค้นหา "%s" พบ %s รายการ', 'Search "%s": %s result(s)'],
    'clear_search'  => ['ล้างคำค้น', 'Clear search'],
    'no_match'      => ['ไม่พบสินค้าที่ตรงกับเงื่อนไข', 'No products match your search'],
    'view_all'      => ['ดูสินค้าทั้งหมด', 'View all products'],
    'other'         => ['อื่น ๆ', 'Other'],

    // ---- การ์ดสินค้า / หน้าสินค้า ----
    'sold_out'      => ['สินค้าหมด', 'Sold out'],
    'left_n'        => ['เหลือ %s ชิ้น', '%s left'],
    'only_left_n'   => ['เหลือเพียง %s ชิ้น', 'Only %s left'],
    'in_stock'      => ['มีสินค้าพร้อมส่ง', 'In stock'],
    'add_to_cart'   => ['เพิ่มลงตะกร้า', 'Add to cart'],
    'quantity'      => ['จำนวน', 'Quantity'],
    'scent_notes'   => ['โน้ตกลิ่น', 'Scent notes'],
    'details'       => ['รายละเอียด', 'Details'],
    'scent_of'      => ['แนว %s', '%s'],
    'related'       => ['น้ำหอมแนวเดียวกัน', 'More in this scent family'],
    'product_nf'    => ['ไม่พบสินค้า', 'Product not found'],
    'product_nf_msg'=> ['ไม่พบสินค้าที่ต้องการ', 'We could not find that product'],
    'back_home'     => ['กลับหน้าแรก', 'Back to home'],
    'notes_prefix'  => ['โน้ตกลิ่น: ', 'Scent notes: '],

    // ---- ตะกร้า ----
    'product'       => ['สินค้า', 'Product'],
    'price'         => ['ราคา', 'Price'],
    'qty'           => ['จำนวน', 'Qty'],
    'line_total'    => ['รวม', 'Total'],
    'remove'        => ['ลบ', 'Remove'],
    'decrease'      => ['ลดจำนวน', 'Decrease'],
    'increase'      => ['เพิ่มจำนวน', 'Increase'],
    'cart_empty'    => ['ตะกร้าของคุณยังว่างอยู่', 'Your cart is empty.'],
    'start_shopping'=> ['เลือกซื้อสินค้า', 'Start shopping'],
    'keep_shopping' => ['เลือกสินค้าต่อ', 'Continue shopping'],
    'checkout_btn'  => ['สั่งซื้อสินค้า', 'Checkout'],
    'cart_meta'     => ['ตรวจสอบรายการน้ำหอมในตะกร้า ปรับจำนวน และสั่งซื้อกับ VELORE ได้ง่าย ๆ', 'Review the perfumes in your cart, adjust quantities and check out with VELORE'],
    'login_to_order'=> ['ต้องเข้าสู่ระบบหรือ <a href="register.php">สมัครสมาชิก</a> ก่อนสั่งซื้อ (สินค้าในตะกร้าจะยังอยู่)', 'Please log in or <a href="register.php">sign up</a> before checkout (your cart is kept).'],

    // ---- ยอดเงิน ----
    'subtotal'      => ['ราคาสินค้า', 'Subtotal'],
    'subtotal_ex'   => ['ราคาสินค้า (ก่อนภาษี)', 'Subtotal (before VAT)'],
    'vat'           => ['ภาษีมูลค่าเพิ่ม %s%%', 'VAT %s%%'],
    'vat_incl'      => ['รวมภาษีมูลค่าเพิ่ม %s%% แล้ว', 'includes VAT %s%%'],
    'shipping'      => ['ค่าจัดส่ง', 'Shipping'],
    'free'          => ['ส่งฟรี', 'Free'],
    'free_ship_hint'=> ['ซื้อครบ %s ส่งฟรี', 'Free shipping on orders of %s or more'],
    'grand_total'   => ['ยอดชำระทั้งหมด', 'Total to pay'],
    'total'         => ['ยอดรวม', 'Total'],

    // ---- สมัคร / เข้าสู่ระบบ / บัญชี ----
    'reg_meta'      => ['สมัครสมาชิก VELORE ฟรี บันทึกที่อยู่จัดส่งไว้ สั่งน้ำหอมนำเข้าครั้งต่อไปได้เร็วขึ้น', 'Join VELORE for free and save your delivery address for faster orders'],
    'reg_intro'     => ['บันทึกชื่อ เบอร์โทร และที่อยู่จัดส่งไว้ สั่งซื้อครั้งต่อไปได้เร็วขึ้น', 'Save your name, phone and delivery address for faster ordering.'],
    'full_name'     => ['ชื่อ-นามสกุล (ภาษาไทยหรืออังกฤษ)', 'Full name (Thai or English)'],
    'email_login'   => ['อีเมล (ใช้สำหรับเข้าสู่ระบบ)', 'Email (used to log in)'],
    'email'         => ['อีเมล', 'Email'],
    'email_fixed'   => ['อีเมล (เปลี่ยนไม่ได้)', 'Email (cannot be changed)'],
    'phone'         => ['เบอร์โทรศัพท์', 'Phone number'],
    'address_later' => ['ที่อยู่จัดส่ง (กรอกภายหลังได้)', 'Delivery address (can be added later)'],
    'address'       => ['ที่อยู่จัดส่ง', 'Delivery address'],
    'password'      => ['รหัสผ่าน', 'Password'],
    'password8'     => ['รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)', 'Password (at least 8 characters)'],
    'confirm_pw'    => ['ยืนยันรหัสผ่าน', 'Confirm password'],
    'have_account'  => ['เป็นสมาชิกอยู่แล้ว?', 'Already a member?'],
    'no_account'    => ['ยังไม่เป็นสมาชิก?', 'Not a member yet?'],
    'signup_free'   => ['สมัครสมาชิกฟรี', 'Sign up for free'],
    'login_meta'    => ['เข้าสู่ระบบสมาชิก VELORE เพื่อสั่งซื้อน้ำหอม ติดตามสถานะออเดอร์ และดูประวัติการสั่งซื้อ', 'Log in to VELORE to order perfumes, track orders and view your history'],
    'err_name'      => ['กรุณากรอกชื่อ-นามสกุล (ไม่เกิน 150 ตัวอักษร)', 'Please enter your full name (max 150 characters)'],
    'err_phone'     => ['กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (ตัวเลข 9-20 หลัก)', 'Please enter a valid phone number (9-20 digits, may include + or -)'],
    'err_addr_long' => ['ที่อยู่ยาวเกินไป (ไม่เกิน 1000 ตัวอักษร)', 'Address is too long (max 1000 characters)'],
    'err_email'     => ['กรุณากรอกอีเมลให้ถูกต้อง', 'Please enter a valid email'],
    'err_pw_len'    => ['รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร', 'Password must be at least 8 characters'],
    'err_pw_match'  => ['การยืนยันรหัสผ่านไม่ตรงกัน', 'Passwords do not match'],
    'err_email_dup' => ['อีเมลนี้สมัครสมาชิกไว้แล้ว กรุณาเข้าสู่ระบบ', 'This email is already registered. Please log in.'],
    'err_reg_fail'  => ['สมัครสมาชิกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 'Sign-up failed. Please try again.'],
    'acc_meta'      => ['แก้ไขชื่อ เบอร์โทร ที่อยู่จัดส่ง และรหัสผ่านของบัญชีสมาชิก VELORE', 'Edit your name, phone, delivery address and password'],
    'acc_profile'   => ['ข้อมูลส่วนตัวและที่อยู่จัดส่ง', 'Profile and delivery address'],
    'save_info'     => ['บันทึกข้อมูล', 'Save'],
    'acc_saved'     => ['บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว', 'Your details have been saved'],
    'joined'        => ['สมัครเมื่อ %s', 'Joined %s'],
    'change_pw'     => ['เปลี่ยนรหัสผ่าน', 'Change password'],
    'current_pw'    => ['รหัสผ่านปัจจุบัน', 'Current password'],
    'new_pw'        => ['รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)', 'New password (at least 8 characters)'],
    'confirm_new_pw'=> ['ยืนยันรหัสผ่านใหม่', 'Confirm new password'],
    'err_cur_pw'    => ['รหัสผ่านปัจจุบันไม่ถูกต้อง', 'Current password is incorrect'],
    'err_new_pw_len'=> ['รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร', 'New password must be at least 8 characters'],
    'err_new_pw_match'=> ['การยืนยันรหัสผ่านใหม่ไม่ตรงกัน', 'New passwords do not match'],
    'pw_changed'    => ['เปลี่ยนรหัสผ่านเรียบร้อยแล้ว', 'Password changed'],

    // ---- ปักหมุดแผนที่ ----
    'map_title'     => ['ปักหมุดตำแหน่งจัดส่ง (ไม่บังคับ)', 'Pin your delivery location (optional)'],
    'map_hint'      => ['คลิกบนแผนที่หรือลากหมุดไปยังจุดที่ต้องการให้ส่ง ช่วยให้ขนส่งหาที่อยู่ได้ง่ายขึ้น', 'Click the map or drag the pin to where we should deliver. It helps the courier find you.'],
    'map_locate'    => ['ใช้ตำแหน่งปัจจุบันของฉัน', 'Use my current location'],
    'map_clear'     => ['ล้างหมุด', 'Clear pin'],
    'map_pinned'    => ['ปักหมุดแล้ว', 'Pin set'],
    'map_none'      => ['ยังไม่ได้ปักหมุด', 'No pin set'],
    'map_denied'    => ['ไม่สามารถอ่านตำแหน่งได้ กรุณาอนุญาตการเข้าถึงตำแหน่ง หรือคลิกบนแผนที่เอง', 'Could not get your location. Allow location access or click on the map.'],
    'map_open'      => ['เปิดแผนที่', 'Open map'],

    // ---- ชำระเงิน ----
    'checkout_meta' => ['กรอกข้อมูลผู้รับ เลือกวิธีชำระเงิน โอนผ่านธนาคารหรือเก็บเงินปลายทาง แล้วยืนยันคำสั่งซื้อ', 'Enter recipient details, choose bank transfer or cash on delivery, then confirm your order'],
    'confirm_order' => ['ยืนยันการสั่งซื้อ', 'Confirm your order'],
    'recipient'     => ['ข้อมูลผู้รับสินค้า', 'Recipient details'],
    'recipient_name'=> ['ชื่อ-นามสกุลผู้รับ (ภาษาไทยหรืออังกฤษ)', 'Recipient full name (Thai or English)'],
    'save_to_profile'=> ['บันทึกเบอร์โทรและที่อยู่นี้ไว้ในบัญชีของฉัน', 'Save this phone and address to my account'],
    'pay_method'    => ['วิธีการชำระเงิน', 'Payment method'],
    'pay_transfer'  => ['โอนเงินผ่านบัญชีธนาคาร', 'Bank transfer'],
    'pay_cod'       => ['เก็บเงินปลายทาง', 'Cash on delivery'],
    'pay_transfer_hint' => ['แสดงเลขบัญชีหลังยืนยัน และแนบสลิปโอนเงินได้', 'Account details shown after confirming; you can attach your transfer slip'],
    'pay_cod_hint'  => ['ชำระเงินสดเมื่อได้รับสินค้า', 'Pay cash when you receive the goods'],
    'place_order'   => ['ยืนยันสั่งซื้อ', 'Place order'],
    'back_to_cart'  => ['กลับไปตะกร้า', 'Back to cart'],
    'order_summary' => ['สรุปรายการ', 'Order summary'],
    'err_recipient' => ['กรุณากรอกชื่อ-นามสกุลผู้รับ', 'Please enter the recipient name'],
    'err_address'   => ['กรุณากรอกที่อยู่จัดส่ง', 'Please enter the delivery address'],
    'err_paymethod' => ['กรุณาเลือกวิธีการชำระเงิน', 'Please choose a payment method'],
    'err_stock'     => ['สินค้า "%s" มีสต็อกไม่เพียงพอ กรุณาปรับจำนวนในตะกร้า', '"%s" does not have enough stock. Please adjust your cart.'],
    'err_order_fail'=> ['เกิดข้อผิดพลาดในการบันทึกคำสั่งซื้อ กรุณาลองใหม่อีกครั้ง', 'Could not place your order. Please try again.'],

    // ---- สั่งซื้อสำเร็จ / ออเดอร์ ----
    'success_meta'  => ['VELORE ได้รับคำสั่งซื้อของคุณแล้ว ตรวจสอบเลขที่ออเดอร์และวิธีชำระเงินได้ที่หน้านี้', 'VELORE has received your order. Check your order number and payment details here'],
    'thanks'        => ['ขอบคุณสำหรับการสั่งซื้อ', 'Thank you for your order'],
    'order_no_is'   => ['เลขที่คำสั่งซื้อของคุณคือ', 'Your order number is'],
    'receiver'      => ['ผู้รับ', 'Recipient'],
    'addr_label'    => ['ที่อยู่', 'Address'],
    'pay_how'       => ['วิธีชำระเงิน: %s', 'Payment: %s'],
    'pls_transfer'  => ['กรุณาโอนเงิน <strong>%s</strong> มาที่', 'Please transfer <strong>%s</strong> to'],
    'account_no'    => ['เลขที่บัญชี', 'Account no.'],
    'account_name'  => ['ชื่อบัญชี', 'Account name'],
    'transfer_after'=> ['หลังโอนแล้ว แนบสลิปด้านล่างเพื่อแจ้งชำระเงิน ทางร้านจะตรวจสอบและเปลี่ยนสถานะเป็น "ชำระเงินแล้ว"', 'After transferring, attach your slip below to notify us. We will verify it and mark the order as "Paid".'],
    'cod_note'      => ['เตรียมเงินสดจำนวน <strong>%s</strong> ไว้ชำระเมื่อได้รับสินค้า ทางร้านจะติดต่อกลับทางโทรศัพท์เพื่อยืนยันการจัดส่ง', 'Please prepare <strong>%s</strong> in cash. We will call you to confirm delivery.'],
    'track_order'   => ['ติดตามสถานะคำสั่งซื้อ', 'Track this order'],
    'order_no'      => ['คำสั่งซื้อ #%s', 'Order #%s'],
    'order_nf'      => ['ไม่พบคำสั่งซื้อ', 'Order not found'],
    'order_nf_msg'  => ['ไม่พบคำสั่งซื้อนี้', 'We could not find this order'],
    'back_orders'   => ['กลับไปประวัติการสั่งซื้อ', 'Back to order history'],
    'order_meta'    => ['ติดตามสถานะคำสั่งซื้อ #%s รายการสินค้า และข้อมูลการชำระเงิน', 'Track order #%s, items and payment details'],
    'order_cancelled'=> ['คำสั่งซื้อนี้ถูกยกเลิกแล้ว หากมีข้อสงสัยกรุณาติดต่อทางร้าน', 'This order has been cancelled. Please contact us if you have questions.'],
    'price_each'    => ['ราคา/ชิ้น', 'Price each'],
    'ship_info'     => ['ข้อมูลจัดส่ง', 'Delivery details'],
    'tel_label'     => ['โทร: %s', 'Tel: %s'],
    'ordered_at'    => ['สั่งเมื่อ %s', 'Ordered %s'],
    'pin_map'       => ['ตำแหน่งที่ปักหมุด', 'Pinned location'],
    'carrier'       => ['ขนส่ง', 'Carrier'],
    'tracking_no'   => ['เลขพัสดุ', 'Tracking no.'],
    'track_parcel'  => ['ติดตามพัสดุ', 'Track parcel'],
    'when_verified' => ['เมื่อทางร้านตรวจสอบการโอนแล้ว สถานะจะเปลี่ยนเป็น "ชำระเงินแล้ว"', 'Once we verify your transfer, the status changes to "Paid".'],
    'cod_cash'      => ['ชำระเงินสดเมื่อได้รับสินค้า', 'Pay cash on delivery'],

    // ---- สลิป ----
    'slip_title'    => ['แนบสลิปการโอนเงิน', 'Attach transfer slip'],
    'slip_hint'     => ['รองรับไฟล์ JPG, PNG, WEBP ขนาดไม่เกิน 5 MB', 'JPG, PNG or WEBP, up to 5 MB'],
    'slip_send'     => ['ส่งสลิป', 'Send slip'],
    'slip_resend'   => ['ส่งสลิปใหม่ (แทนของเดิม)', 'Send a new slip (replaces the old one)'],
    'slip_received' => ['ได้รับสลิปแล้วเมื่อ %s ทางร้านกำลังตรวจสอบ', 'Slip received on %s. We are verifying it.'],
    'slip_view'     => ['ดูสลิปที่แนบ', 'View uploaded slip'],
    'slip_ok'       => ['แนบสลิปเรียบร้อยแล้ว ทางร้านจะตรวจสอบโดยเร็ว', 'Slip uploaded. We will verify it shortly.'],
    'slip_no_file'  => ['กรุณาเลือกไฟล์สลิป', 'Please choose a slip file'],
    'slip_big'      => ['ไฟล์ใหญ่เกิน 5 MB', 'File is larger than 5 MB'],
    'slip_type'     => ['รองรับเฉพาะไฟล์รูป JPG, PNG, WEBP', 'Only JPG, PNG or WEBP images are accepted'],
    'slip_fail'     => ['อัปโหลดไม่สำเร็จ กรุณาลองใหม่', 'Upload failed. Please try again.'],
    'slip_not_allowed'=> ['ออเดอร์นี้แนบสลิปไม่ได้', 'A slip cannot be attached to this order'],

    // ---- ประวัติ ----
    'orders_meta'   => ['ดูประวัติและสถานะคำสั่งซื้อน้ำหอมทั้งหมดของคุณที่ VELORE', 'View all your VELORE orders and their status'],
    'no_orders'     => ['คุณยังไม่มีคำสั่งซื้อ', 'You have no orders yet'],
    'start_pick'    => ['เริ่มเลือกซื้อน้ำหอม', 'Start shopping'],
    'col_no'        => ['เลขที่', 'No.'],
    'col_date'      => ['วันที่สั่ง', 'Date'],
    'col_items'     => ['จำนวน (ชิ้น)', 'Items'],
    'col_total'     => ['ยอดรวม', 'Total'],
    'col_payment'   => ['ชำระเงิน', 'Payment'],
    'col_status'    => ['สถานะ', 'Status'],
    'view_track'    => ['ดู / ติดตาม', 'View / Track'],

    // ---- สถานะ ----
    'st_pending'    => ['รอชำระเงิน', 'Awaiting payment'],
    'st_paid'       => ['ชำระเงินแล้ว', 'Paid'],
    'st_preparing'  => ['กำลังเตรียมสินค้า', 'Preparing'],
    'st_shipped'    => ['จัดส่งแล้ว', 'Shipped'],
    'st_completed'  => ['สำเร็จ', 'Completed'],
    'st_cancelled'  => ['ยกเลิก', 'Cancelled'],

    // ---- ติดต่อ / นโยบาย / 404 ----
    'contact_meta'  => ['ติดต่อ VELORE ร้านน้ำหอมนำเข้า โทร อีเมล LINE และที่อยู่ร้าน พร้อมเวลาทำการ', 'Contact VELORE: phone, email, LINE, store address and opening hours'],
    'contact_intro' => ['สอบถามสินค้า สถานะออเดอร์ หรือปัญหาการสั่งซื้อ ติดต่อเราได้ตามช่องทางด้านล่าง', 'Questions about products, order status or problems? Reach us below.'],
    'c_phone'       => ['โทรศัพท์', 'Phone'],
    'c_address'     => ['ที่อยู่ร้าน', 'Store address'],
    'c_hours'       => ['เวลาทำการ', 'Opening hours'],
    'shop_now'      => ['เลือกซื้อน้ำหอม', 'Shop perfumes'],
    'nf_title'      => ['ไม่พบหน้าที่ต้องการ (404)', 'Page not found (404)'],
    'nf_meta'       => ['ไม่พบหน้าที่คุณต้องการ อาจถูกย้ายหรือลบไปแล้ว กลับไปเลือกชมน้ำหอมนำเข้าที่ VELORE ได้เลย', 'We could not find that page. It may have moved. Come back and browse VELORE perfumes.'],
    'nf_h1'         => ['ไม่พบหน้าที่คุณต้องการ', 'Page not found'],
    'nf_p'          => ['ลิงก์อาจพิมพ์ผิด หรือหน้านี้ถูกย้ายหรือลบไปแล้ว', 'The link may be wrong, or the page was moved or removed.'],
    'login_locked'  => ['ใส่รหัสผิดหลายครั้งเกินไป กรุณารออีก %s นาที', 'Too many failed attempts. Please wait %s minute(s).'],
    'login_ok'      => ['เข้าสู่ระบบเรียบร้อยแล้ว', 'You are now logged in'],
    'login_bad'     => ['อีเมลหรือรหัสผ่านไม่ถูกต้อง', 'Incorrect email or password'],
    'ship_choose'   => ['เลือกบริษัทขนส่ง', 'Choose a courier'],
    'ship_days'     => ['ถึงใน %s วัน', 'Delivery in %s days'],
    'ship_sim_note' => ['* ระบบขนส่งเป็นการจำลองเพื่อการสาธิต ไม่ได้ส่งของจริง', '* Courier service is a simulation for demonstration; nothing is actually shipped'],
    'ship_free_note'=> ['ซื้อครบ %s ส่งฟรีทุกขนส่ง', 'Free shipping with any courier on orders of %s or more'],
    'err_carrier'   => ['กรุณาเลือกบริษัทขนส่ง', 'Please choose a courier'],
    'ship_calc_note'=> ['ค่าจัดส่งคำนวณจากขนส่งที่เลือกตอนชำระเงิน', 'Shipping depends on the courier you choose at checkout'],
    'track_title'   => ['ติดตามพัสดุ', 'Track parcel'],
    'track_meta'    => ['ติดตามสถานะพัสดุด้วยเลขพัสดุ (ระบบขนส่งจำลอง)', 'Track your parcel with its tracking number (simulated courier)'],
    'track_enter'   => ['กรอกเลขพัสดุ', 'Enter tracking number'],
    'track_go'      => ['ค้นหา', 'Track'],
    'track_nf'      => ['ไม่พบเลขพัสดุนี้', 'Tracking number not found'],
    'track_not_yet' => ['ร้านยังไม่ได้ส่งพัสดุ หรือขนส่งยังไม่รับของ', 'The parcel has not been handed to the courier yet'],
    'track_status'  => ['สถานะ', 'Status'],
    'ev_booked'     => ['ร้านค้าเรียกรถรับพัสดุแล้ว (จองขนส่งสำเร็จ)', 'Pickup booked by the shop'],
    'ev_picked'     => ['ขนส่งรับพัสดุจากร้านค้าแล้ว', 'Picked up from the shop'],
    'ev_origin_hub' => ['พัสดุถึงศูนย์คัดแยกต้นทาง', 'Arrived at origin sorting hub'],
    'ev_transit'    => ['อยู่ระหว่างขนส่งไปยังศูนย์ปลายทาง', 'In transit to destination hub'],
    'ev_dest_hub'   => ['พัสดุถึงศูนย์กระจายสินค้าปลายทาง', 'Arrived at destination hub'],
    'ev_out'        => ['พนักงานกำลังนำจ่ายพัสดุ', 'Out for delivery'],
    'ev_delivered'  => ['จัดส่งสำเร็จ ผู้รับได้รับพัสดุแล้ว', 'Delivered to the recipient'],
    'sim_badge'     => ['ระบบจำลอง', 'Simulation'],
    'to_recipient'  => ['ผู้รับ', 'Recipient'],
    'ship_via'      => ['ขนส่ง: %s', 'Courier: %s'],
    'qr_title'      => ['สแกนจ่ายด้วย QR พร้อมเพย์', 'Scan to pay with PromptPay QR'],
    'qr_steps'      => ['เปิดแอปธนาคาร → เลือก “สแกน” → สแกน QR นี้ ยอดเงินจะขึ้นให้อัตโนมัติ → ยืนยันโอน แล้วแนบสลิปด้านล่าง', 'Open your banking app → choose “Scan” → scan this QR. The amount is filled in for you → confirm, then attach your slip below.'],
    'qr_or_manual'  => ['หรือโอนเข้าบัญชีด้านล่าง', 'Or transfer to the account below'],
    'browse'        => ['เลือกชมสินค้า', 'Browse products'],
];
