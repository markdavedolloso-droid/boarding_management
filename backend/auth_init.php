<?php
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function database_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([':table_name' => $table, ':column_name' => $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function database_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
    );
    $stmt->execute([':table_name' => $table, ':index_name' => $index]);

    return (int) $stmt->fetchColumn() > 0;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS app_meta (
        meta_key VARCHAR(100) PRIMARY KEY,
        meta_value VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
$schemaVersion = (int) ($pdo->query("SELECT meta_value FROM app_meta WHERE meta_key = 'schema_version'")->fetchColumn() ?: 0);

if ($schemaVersion < 2) {
    $pdo->exec(
    "CREATE TABLE IF NOT EXISTS app_users (
        user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NULL UNIQUE,
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('owner', 'tenant') NOT NULL DEFAULT 'owner',
        status ENUM('active', 'pending', 'disabled') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if (!database_column_exists($pdo, 'app_users', 'role')) {
    $pdo->exec("ALTER TABLE app_users ADD role ENUM('owner', 'tenant') NOT NULL DEFAULT 'owner'");
}
if (!database_column_exists($pdo, 'app_users', 'username')) {
    $pdo->exec('ALTER TABLE app_users ADD username VARCHAR(50) NULL UNIQUE');
}
if (!database_column_exists($pdo, 'app_users', 'status')) {
    $pdo->exec("ALTER TABLE app_users ADD status ENUM('active', 'pending', 'disabled') NOT NULL DEFAULT 'active'");
}
$pdo->exec("UPDATE app_users SET username = CONCAT('user', user_id) WHERE username IS NULL OR username = ''");
if (!database_column_exists($pdo, 'tenants', 'user_id')) {
    $pdo->exec('ALTER TABLE tenants ADD user_id INT UNSIGNED NULL');
}
if (!database_column_exists($pdo, 'tenants', 'owner_id')) {
    $pdo->exec('ALTER TABLE tenants ADD owner_id INT UNSIGNED NULL');
}
if (!database_index_exists($pdo, 'tenants', 'uq_tenants_user_id')) {
    $pdo->exec('ALTER TABLE tenants ADD UNIQUE KEY uq_tenants_user_id (user_id)');
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS rooms (
        room_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        owner_id INT UNSIGNED NOT NULL,
        room_number VARCHAR(60) NOT NULL,
        floor INT NOT NULL DEFAULT 1,
        capacity INT UNSIGNED NOT NULL DEFAULT 1,
        monthly_rent DECIMAL(10,2) NOT NULL,
        status ENUM('available', 'maintenance') NOT NULL DEFAULT 'available',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_rooms_owner_number (owner_id, room_number),
        KEY idx_rooms_owner_status (owner_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS rental_contracts (
        contract_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        owner_id INT UNSIGNED NOT NULL,
        tenant_id INT NOT NULL,
        room_id INT UNSIGNED NOT NULL,
        monthly_rent DECIMAL(10,2) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NULL,
        contract_status ENUM('active', 'ended', 'cancelled') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_contracts_owner_status (owner_id, contract_status, start_date, end_date),
        KEY idx_contracts_tenant (tenant_id),
        KEY idx_contracts_room (room_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
if (!database_column_exists($pdo, 'rental_contracts', 'monthly_rent')) {
    $pdo->exec('ALTER TABLE rental_contracts ADD monthly_rent DECIMAL(10,2) NOT NULL DEFAULT 0');
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS payments (
        payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        owner_id INT UNSIGNED NOT NULL,
        contract_id INT UNSIGNED NOT NULL,
        payment_date DATE NOT NULL,
        billing_month DATE NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        payment_status ENUM('paid', 'pending', 'overdue') NOT NULL DEFAULT 'paid',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_payments_owner_date (owner_id, payment_date),
        KEY idx_payments_contract_month (contract_id, billing_month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS utility_bills (
        bill_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        owner_id INT UNSIGNED NOT NULL,
        contract_id INT UNSIGNED NOT NULL,
        electricity_bill DECIMAL(10,2) NOT NULL DEFAULT 0,
        water_bill DECIMAL(10,2) NOT NULL DEFAULT 0,
        billing_month DATE NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        due_date DATE NULL,
        bill_status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_bills_contract_month (contract_id, billing_month),
        KEY idx_bills_owner_status (owner_id, bill_status, due_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        owner_id INT UNSIGNED NOT NULL,
        tenant_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        source_key VARCHAR(100) NULL UNIQUE,
        date_sent TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status ENUM('unread', 'read') NOT NULL DEFAULT 'unread',
        KEY idx_notifications_tenant (tenant_id, status, date_sent),
        KEY idx_notifications_owner (owner_id, date_sent)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
if (!database_column_exists($pdo, 'notifications', 'source_key')) {
    $pdo->exec('ALTER TABLE notifications ADD source_key VARCHAR(100) NULL UNIQUE');
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS activity_logs (
        log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        owner_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        activity VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_activity_owner_date (owner_id, created_at),
        KEY idx_activity_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$firstOwnerId = $pdo->query(
    "SELECT user_id FROM app_users WHERE role = 'owner' ORDER BY user_id LIMIT 1"
)->fetchColumn();
if ($firstOwnerId !== false) {
    $stmt = $pdo->prepare('UPDATE tenants SET owner_id = :owner_id WHERE owner_id IS NULL');
    $stmt->execute([':owner_id' => $firstOwnerId]);
}
$stmt = $pdo->prepare(
    "INSERT INTO app_meta (meta_key, meta_value) VALUES ('schema_version', '2')
     ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)"
);
$stmt->execute();
}

function require_staywise_login(): void
{
    if (empty($_SESSION['staywise_user'])) {
        header('Location: login.php');
        exit;
    }

    $userId = (int) ($_SESSION['staywise_user']['id'] ?? 0);
    $stmt = $GLOBALS['pdo']->prepare(
        'SELECT user_id, full_name, email, role, status FROM app_users WHERE user_id = :user_id LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId]);
    $account = $stmt->fetch();
    if (!$account || $account['status'] !== 'active') {
        unset($_SESSION['staywise_user']);
        $_SESSION['auth_message'] = 'Your account is no longer active. Please contact your manager.';
        header('Location: login.php');
        exit;
    }

    $tenantId = null;
    $ownerId = $userId;
    if ($account['role'] === 'tenant') {
        $stmt = $GLOBALS['pdo']->prepare('SELECT tenant_id, owner_id FROM tenants WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $tenant = $stmt->fetch();
        if (!$tenant || empty($tenant['owner_id'])) {
            unset($_SESSION['staywise_user']);
            $_SESSION['auth_message'] = 'Your tenant profile is not currently linked to a boarding house.';
            header('Location: login.php');
            exit;
        }
        $tenantId = (int) $tenant['tenant_id'];
        $ownerId = (int) $tenant['owner_id'];
    }

    $_SESSION['staywise_user'] = [
        'id' => (int) $account['user_id'],
        'name' => $account['full_name'],
        'email' => $account['email'],
        'role' => $account['role'],
        'owner_id' => $ownerId,
        'tenant_id' => $tenantId,
    ];

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function require_staywise_owner(): void
{
    require_staywise_login();
    if (($_SESSION['staywise_user']['role'] ?? '') !== 'owner') {
        header('Location: index.php');
        exit;
    }
}

function verify_staywise_csrf(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function staywise_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function log_staywise_activity(string $activity): void
{
    if (empty($_SESSION['staywise_user']['id']) || empty($_SESSION['staywise_user']['owner_id'])) {
        return;
    }

    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (owner_id, user_id, activity) VALUES (:owner_id, :user_id, :activity)'
    );
    $stmt->execute([
        ':owner_id' => $_SESSION['staywise_user']['owner_id'],
        ':user_id' => $_SESSION['staywise_user']['id'],
        ':activity' => $activity,
    ]);
}
