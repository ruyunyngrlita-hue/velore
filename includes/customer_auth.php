<?php
// =====================================================
// VELORE - ระบบสมาชิก (ลูกค้า) : session, ล็อกอิน, จำกัดการเดารหัส
// แยกจากระบบแอดมิน (admin_id) โดยสิ้นเชิง ล็อกอินลูกค้าไม่ทำให้เข้าหลังร้านได้
// ใช้ csrf_*(), flash(), redirect() จาก config.php
// =====================================================

const CUST_MAX_ATTEMPTS = 5;      // ใส่รหัสผิดได้กี่ครั้ง
const CUST_LOCK_SECONDS = 300;    // ถูกล็อกกี่วินาที (5 นาที)

function customer_logged_in(): bool {
    return !empty($_SESSION['customer_id']);
}

// บังคับล็อกอิน ถ้ายังไม่ล็อกอินให้ไปหน้าเข้าสู่ระบบ แล้วพากลับมาหน้าเดิมหลังล็อกอิน
function customer_required(string $returnPage = 'index.php'): void {
    if (!customer_logged_in()) {
        $_SESSION['after_login'] = $returnPage;
        flash(t('must_login'), 'error');
        redirect(BASE_URL . '/login.php');
    }
}

// URL ที่จะไปหลังล็อกอิน (อนุญาตเฉพาะหน้าที่กำหนด กัน redirect ไปเว็บอื่น)
function after_login_url(): string {
    $allowed = ['checkout.php', 'cart.php', 'account.php', 'orders.php', 'index.php'];
    $page = $_SESSION['after_login'] ?? 'index.php';
    unset($_SESSION['after_login']);
    return BASE_URL . '/' . (in_array($page, $allowed, true) ? $page : 'index.php');
}

function customer_login(array $c): void {
    session_regenerate_id(true);
    $_SESSION['customer_id']   = (int)$c['id'];
    $_SESSION['customer_name'] = $c['name'];
    unset($_SESSION['cust_fail'], $_SESSION['cust_lock_until']);
}

function customer_logout(): void {
    unset($_SESSION['customer_id'], $_SESSION['customer_name']);
    session_regenerate_id(true);
}

// ข้อมูลสมาชิกที่ล็อกอินอยู่ (ไม่คืนรหัสผ่าน) ถ้าไม่พบในฐานข้อมูลให้ถือว่าไม่ได้ล็อกอิน
function current_customer($conn): ?array {
    if (!customer_logged_in()) {
        return null;
    }
    $id = (int)$_SESSION['customer_id'];
    $stmt = $conn->prepare('SELECT id, name, email, phone, address, lat, lng, created_at FROM customers WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        customer_logout();
        return null;
    }
    return $row;
}

function customer_locked_seconds(): int {
    return max(0, ($_SESSION['cust_lock_until'] ?? 0) - time());
}

function customer_record_failure(): void {
    $_SESSION['cust_fail'] = ($_SESSION['cust_fail'] ?? 0) + 1;
    if ($_SESSION['cust_fail'] >= CUST_MAX_ATTEMPTS) {
        $_SESSION['cust_lock_until'] = time() + CUST_LOCK_SECONDS;
        $_SESSION['cust_fail'] = 0;
    }
}

// ตรวจข้อมูลโปรไฟล์ที่ใช้ร่วมกันในหน้าสมัครและหน้าแก้ไขข้อมูล คืนรายการข้อผิดพลาด
function validate_profile(string $name, string $phone, string $address): array {
    $errors = [];
    if ($name === '' || mb_strlen($name) > 150) {
        $errors[] = t('err_name');
    }
    if (!preg_match('/^[0-9+\-\s]{9,20}$/', $phone)) {
        $errors[] = t('err_phone');
    }
    if (mb_strlen($address) > 1000) {
        $errors[] = t('err_addr_long');
    }
    return $errors;
}
