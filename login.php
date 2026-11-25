<?php
require_once __DIR__ . '/includes/config.php';

if (customer_logged_in()) {
    redirect(after_login_url());
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $wait = customer_locked_seconds();
    if ($wait > 0) {
        $error = t('login_locked', (int)ceil($wait / 60));
    } else {
        $stmt = $conn->prepare('SELECT id, name, password FROM customers WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $c = $stmt->get_result()->fetch_assoc();

        if ($c && password_verify($password, $c['password'])) {
            customer_login($c);
            flash(t('login_ok'));
            redirect(after_login_url());
        }
        customer_record_failure();
        $error = t('login_bad');   // ไม่บอกว่าผิดที่ตัวไหน
    }
}

$pageTitle = t('login');
$metaDesc = t('login_meta');
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="form-box auth-box">
        <h1><?= e(t('login')) ?></h1>

        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <label for="email"><?= e(t('email')) ?></label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email">

            <label for="password"><?= e(t('password')) ?></label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <p style="margin-top:20px"><button class="btn btn-block-sm" type="submit"><?= e(t('login')) ?></button></p>
        </form>
        <p class="auth-alt"><?= e(t('no_account')) ?> <a href="register.php"><?= e(t('signup_free')) ?></a></p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
