<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

start_session();
if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!csrf_ok()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your user name and password.';
    } else {
        $account = fetch_one('SELECT * FROM users WHERE username = ?', [$username]);
        if ($account && password_verify($password, $account['password_hash'])) {
            login_as((int)$account['id']);
            redirect('dashboard.php');
        }
        $error = 'Wrong user name or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | YESG</title>
    <link rel="stylesheet" href="CSS/app.css">
</head>
<body class="auth-body" data-page="login">
    <section class="container">
        <form method="post" action="index.php" id="login-form" novalidate>
            <?= csrf_field() ?>
            <h2>LOGIN</h2>
            <div class="input-container">
                <?= icon('user') ?>
                <input type="text" id="username" name="username" placeholder="User Name" autocomplete="username" value="<?= e($username) ?>" required>
            </div>
            <div id="password-container">
                <div class="input-container">
                    <?= icon('lock') ?>
                    <input type="password" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
                </div>
                <a href="#" id="forgot-pass" data-hint="Ask the treasurer or secretary to reset your password.">forgot password?</a>
            </div>
            <?php if ($error !== ''): ?>
                <p class="form-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
            <button type="submit" id="login-btn">LOGIN</button>
        </form>
        <article id="welcome">
            <h2>Welcome back!</h2>
            <p>We are happy to see you again. Please login into your account to continue.</p>
        </article>
    </section>
    <script src="JS/app.js"></script>
</body>
</html>
