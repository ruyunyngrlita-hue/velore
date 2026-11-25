<?php
// =====================================================
// VELORE - ระบบตรวจสอบสิทธิ์แอดมิน (Session + CSRF)
// CSRF ใช้ csrf_token() / csrf_field() / csrf_check() จาก config.php
// =====================================================
require_once __DIR__ . '/config.php';

define('LOGIN_MAX_ATTEMPTS', 5);       // ใส่รหัสผิดได้กี่ครั้ง
define('LOGIN_LOCK_SECONDS', 300);     // ถูกล็อกกี่วินาที (5 นาที)
define('ADMIN_IDLE_SECONDS', 7200);    // ไม่ใช้งานนานเกินนี้จะถูกออกจากระบบ (2 ชั่วโมง)

function admin_logged_in(): bool {
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    if (!empty($_SESSION['admin_last']) && time() - $_SESSION['admin_last'] > ADMIN_IDLE_SECONDS) {
        admin_logout();
        return false;
    }
    $_SESSION['admin_last'] = time();
    return true;
}

function admin_required(): void {
    if (!admin_logged_in()) {
        flash('กรุณาเข้าสู่ระบบ', 'error');
        redirect(BASE_URL . '/admin/login.php');
    }
}

function admin_login(array $admin): void {
    session_regenerate_id(true);
    $_SESSION['admin_id']   = (int)$admin['id'];
    $_SESSION['admin_name'] = $admin['username'];
    $_SESSION['admin_last'] = time();
    unset($_SESSION['login_fail'], $_SESSION['login_lock_until']);
}

function admin_logout(): void {
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_last']);
    session_regenerate_id(true);
}

// ---- จำกัดจำนวนครั้งที่ใส่รหัสผิด (นับต่อ session) ----
function login_locked_seconds(): int {
    $until = $_SESSION['login_lock_until'] ?? 0;
    return max(0, $until - time());
}

function login_record_failure(): void {
    $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
    if ($_SESSION['login_fail'] >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION['login_lock_until'] = time() + LOGIN_LOCK_SECONDS;
        $_SESSION['login_fail'] = 0;
    }
}
