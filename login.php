<?php
require_once __DIR__ . '/backend/auth_init.php';

$error = '';
if (isset($_SESSION['auth_message'])) {
    $error = (string) $_SESSION['auth_message'];
    unset($_SESSION['auth_message']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = strtolower(trim($_POST['login'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!verify_staywise_csrf()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT user_id, full_name, email, password_hash, role, status
             FROM app_users WHERE email = :email OR username = :username LIMIT 1'
        );
        $stmt->execute([':email' => $login, ':username' => $login]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'The email or password is incorrect.';
        } elseif ($user['status'] === 'pending') {
            $error = 'Your tenant account is waiting for your manager to link your tenant record.';
        } elseif ($user['status'] !== 'active') {
            $error = 'This account is not active. Contact your boarding house manager.';
        } else {
            $tenantId = null;
            $ownerId = (int) $user['user_id'];

            if ($user['role'] === 'tenant') {
                $stmt = $pdo->prepare('SELECT tenant_id, owner_id FROM tenants WHERE user_id = :user_id LIMIT 1');
                $stmt->execute([':user_id' => $user['user_id']]);
                $tenant = $stmt->fetch();
                if (!$tenant || empty($tenant['owner_id'])) {
                    $error = 'Your tenant profile has not been linked yet. Please contact your manager.';
                } else {
                    $tenantId = (int) $tenant['tenant_id'];
                    $ownerId = (int) $tenant['owner_id'];
                }
            }

            if ($error === '') {
                session_regenerate_id(true);
                $_SESSION['staywise_user'] = [
                    'id' => (int) $user['user_id'],
                    'name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'owner_id' => $ownerId,
                    'tenant_id' => $tenantId,
                ];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                log_staywise_activity('Logged in');
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · StayWise</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261022-auth-hide-form-scrollbar">
    <link rel="stylesheet" href="assets/css/theme.css?v=20261022-home-nav-theme">
    <link rel="stylesheet" href="assets/css/auth-flip.css?v=20261022-auth-book-card">
    <script src="assets/js/theme.js?v=20261022-iconify"></script>
    <script src="assets/js/auth-flip.js?v=20261022-auth-book-card" defer></script>
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-brand">
            <div class="brand-mark">⌂ StayWise</div>
            <h1>Your property,<br>clearly managed.</h1>
            <p>Track tenant information and keep important records together in one organized workspace.</p>
            <div class="auth-art"><div class="art-card">⌂</div><div class="art-card">▥</div><div class="art-card">↗</div></div>
        </section>
        <section class="auth-form">
            <p class="eyebrow">Welcome back</p>
            <h2>Log in to StayWise</h2>
            <p class="sub">Sign in to continue to your dashboard.</p>
            <?php if ($error !== ''): ?>
                <div class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <label for="login">Email or username</label>
                <input id="login" type="text" name="login" autocomplete="username" placeholder="Enter your email or username" required>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
                <button type="submit">Log in →</button>
            </form>
            <p class="auth-switch">Don't have an account? <a href="signup.php">Sign up</a></p>
        </section>
    </main>
</body>
</html>
