<?php
require_once __DIR__ . '/includes/config.php';

if (customer_logged_in()) {
    redirect('account.php');
}

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old['name']    = trim($_POST['name'] ?? '');
    $old['email']   = strtolower(trim($_POST['email'] ?? ''));
    $old['phone']   = trim($_POST['phone'] ?? '');
    $old['address'] = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    $errors = validate_profile($old['name'], $old['phone'], $old['address']);

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
        $errors[] = t('err_email');
    }
    if (mb_strlen($password) < 8) {
        $errors[] = t('err_pw_len');
    }
    if ($password !== $confirm) {
        $errors[] = t('err_pw_match');
    }

    if (!$errors) {
        $stmt = $conn->prepare('SELECT id FROM customers WHERE email = ?');
        $stmt->bind_param('s', $old['email']);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = t('err_email_dup');
        }
    }

    if (!$errors) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO customers (name, email, password, phone, address) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $old['name'], $old['email'], $hash, $old['phone'], $old['address']);
            $stmt->execute();
            customer_login(['id' => $conn->insert_id, 'name' => $old['name']]);
            flash(t('welcome_new'));
            redirect(after_login_url());
        } catch (mysqli_sql_exception $ex) {
            // 1062 = อีเมลซ้ำ (กรณีสมัครพร้อมกันสองครั้ง)
            $errors[] = $ex->getCode() === 1062
                ? t('err_email_dup')
                : t('err_reg_fail');
        }
    }
}

$pageTitle = t('register');
$metaDesc = t('reg_meta');
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="form-box auth-box">
        <h1><?= e(t('register')) ?></h1>
        <p class="muted"><?= e(t('reg_intro')) ?></p>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" autocomplete="on">
            <?= csrf_field() ?>
            <label for="name"><?= e(t('full_name')) ?></label>
            <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" required autocomplete="name">

            <label for="email"><?= e(t('email_login')) ?></label>
            <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email">

            <label for="phone"><?= e(t('phone')) ?></label>
            <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" required autocomplete="tel">

            <label for="address"><?= e(t('address_later')) ?></label>
            <textarea id="address" name="address" autocomplete="street-address"><?= e($old['address']) ?></textarea>

            <label for="password"><?= e(t('password8')) ?></label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">

            <label for="confirm"><?= e(t('confirm_pw')) ?></label>
            <input type="password" id="confirm" name="confirm" required minlength="8" autocomplete="new-password">

            <p style="margin-top:20px"><button class="btn btn-block-sm" type="submit"><?= e(t('register')) ?></button></p>
        </form>
        <p class="auth-alt"><?= e(t('have_account')) ?> <a href="login.php"><?= e(t('login')) ?></a></p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
