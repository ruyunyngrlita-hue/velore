<?php
require_once __DIR__ . '/includes/config.php';
customer_required('account.php');

$me = current_customer($conn);
if (!$me) {
    customer_required('account.php');   // ถูกลบออกจากระบบแล้ว → ไปหน้าเข้าสู่ระบบ
}

$errors = [];
$pwErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $lat = clean_coord($_POST['lat'] ?? null, -90, 90);
        $lng = clean_coord($_POST['lng'] ?? null, -180, 180);
        if ($lat === null || $lng === null) { $lat = $lng = null; }
        $errors  = validate_profile($name, $phone, $address);

        if (!$errors) {
            $stmt = $conn->prepare('UPDATE customers SET name = ?, phone = ?, address = ?, lat = ?, lng = ? WHERE id = ?');
            $stmt->bind_param('sssddi', $name, $phone, $address, $lat, $lng, $me['id']);
            $stmt->execute();
            $_SESSION['customer_name'] = $name;
            flash(t('acc_saved'));
            redirect('account.php');
        }
        $me['name'] = $name; $me['phone'] = $phone; $me['address'] = $address; $me['lat'] = $lat; $me['lng'] = $lng;   // แสดงค่าที่กรอกไว้

    } elseif ($action === 'password') {
        $current = $_POST['current'] ?? '';
        $new     = $_POST['new'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        $stmt = $conn->prepare('SELECT password FROM customers WHERE id = ?');
        $stmt->bind_param('i', $me['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($current, $row['password'])) $pwErrors[] = t('err_cur_pw');
        if (mb_strlen($new) < 8)                                  $pwErrors[] = t('err_new_pw_len');
        if ($new !== $confirm)                                    $pwErrors[] = t('err_new_pw_match');

        if (!$pwErrors) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE customers SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $me['id']);
            $stmt->execute();
            session_regenerate_id(true);
            flash(t('pw_changed'));
            redirect('account.php');
        }
    }
}

$pageTitle = t('my_account');
$metaDesc = t('acc_meta');
require __DIR__ . '/includes/header.php';
?>

<h1><?= e(t('my_account')) ?></h1>

<div class="account-grid">
    <div class="form-box">
        <h3 style="margin-top:0"><?= e(t('acc_profile')) ?></h3>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">

            <label><?= e(t('email_fixed')) ?></label>
            <input type="text" value="<?= e($me['email']) ?>" disabled>

            <label for="name"><?= e(t('full_name')) ?></label>
            <input type="text" id="name" name="name" value="<?= e($me['name']) ?>" required>

            <label for="phone"><?= e(t('phone')) ?></label>
            <input type="tel" id="phone" name="phone" value="<?= e($me['phone']) ?>" required>

            <label for="address"><?= e(t('address')) ?></label>
            <textarea id="address" name="address"><?= e($me['address']) ?></textarea>

            <?php require_once __DIR__ . '/includes/mappicker.php'; render_map_picker($me['lat'] ?? null, $me['lng'] ?? null); ?>

            <p style="margin-top:20px"><button class="btn" type="submit"><?= e(t('save_info')) ?></button></p>
        </form>
        <p class="muted" style="margin:10px 0 0;font-size:.85rem"><?= e(t('joined', date('d/m/Y', strtotime($me['created_at'])))) ?></p>
    </div>

    <div class="form-box">
        <h3 style="margin-top:0"><?= e(t('change_pw')) ?></h3>
        <?php foreach ($pwErrors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">

            <label for="current"><?= e(t('current_pw')) ?></label>
            <input type="password" id="current" name="current" required autocomplete="current-password">

            <label for="new"><?= e(t('new_pw')) ?></label>
            <input type="password" id="new" name="new" required minlength="8" autocomplete="new-password">

            <label for="confirm"><?= e(t('confirm_new_pw')) ?></label>
            <input type="password" id="confirm" name="confirm" required minlength="8" autocomplete="new-password">

            <p style="margin-top:20px"><button class="btn btn-outline" type="submit"><?= e(t('change_pw')) ?></button></p>
        </form>

        <p style="margin-top:24px"><a class="btn btn-outline" href="orders.php"><?= e(t('order_history')) ?></a></p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
