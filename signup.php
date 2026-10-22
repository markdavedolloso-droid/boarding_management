<?php
require_once __DIR__ . '/backend/auth_init.php';

$error = '';
$fullName = '';
$username = '';
$email = '';
$role = $_POST['role'] ?? 'owner';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (!verify_staywise_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($fullName === '' || strlen($fullName) > 150) {
        $error = 'Enter your full name (up to 150 characters).';
    } elseif (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
        $error = 'Choose a username with 3–50 letters, numbers, dots, dashes, or underscores.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'Enter a valid email address.';
    } elseif (!in_array($role, ['owner', 'tenant'], true)) {
        $error = 'Choose a valid account type.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Your passwords do not match.';
    } else {
        $status = $role === 'owner' ? 'active' : 'pending';
        $stmt = $pdo->prepare(
            'INSERT INTO app_users (username, full_name, email, password_hash, role, status)
             VALUES (:username, :full_name, :email, :password_hash, :role, :status)'
        );

        try {
            $stmt->execute([
                ':username' => $username,
                ':full_name' => $fullName,
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => $role,
                ':status' => $status,
            ]);

            if ($role === 'tenant') {
                $error = 'Account created. Your manager must link your tenant profile before you can log in.';
            } else {
                $ownerId = (int) $pdo->lastInsertId();
                $stmt = $pdo->prepare('UPDATE tenants SET owner_id = :owner_id WHERE owner_id IS NULL');
                $stmt->execute([':owner_id' => $ownerId]);
                $error = 'Owner account created. You can now log in.';
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'That username or email is already registered. Try logging in instead.';
            } else {
                throw $e;
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
    <title>Create account · StayWise</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261021-auth-flip-original">
    <script src="assets/js/auth-flip.js?v=20261021-auth-flip-original" defer></script>
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-brand">
            <div class="brand-mark">⌂ StayWise</div>
            <h1>Better records.<br>Calmer management.</h1>
            <p>A smarter, more organized way to manage your boarding house and tenant records.</p>
            <div class="auth-art"><div class="art-card">⌂</div><div class="art-card">▥</div><div class="art-card">↗</div></div>
        </section>
        <section class="auth-form">
            <p class="eyebrow">Get started</p>
            <h2>Create your account</h2>
            <p class="sub">Choose an owner or tenant account.</p>
            <?php if ($error !== ''): ?>
                <div class="alert" role="status"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>" autocomplete="name" maxlength="150" required>
                <label for="username">Username</label>
                <input id="username" name="username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._\-]+" required>
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" maxlength="190" required>
                <label for="role">Account type</label>
                <select id="role" name="role" required>
                    <option value="owner" <?= $role === 'owner' ? 'selected' : '' ?>>Boarding house owner</option>
                    <option value="tenant" <?= $role === 'tenant' ? 'selected' : '' ?>>Tenant</option>
                </select>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" required>
                <label for="confirm_password">Confirm password</label>
                <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                <button type="submit">Create account →</button>
            </form>
            <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
        </section>
    </main>
</body>
</html>
