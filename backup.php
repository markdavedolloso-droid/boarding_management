<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
$activePage = 'backup';
$ownerId = (int) $_SESSION['staywise_user']['owner_id'];
$message = '';
$messageType = 'status';

function backup_valid_date(mixed $value, bool $nullable = false): bool
{
    if ($value === null) {
        return $nullable;
    }
    if (!is_string($value)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function backup_valid_datetime(mixed $value): bool
{
    if (!is_string($value)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    return $date !== false && $date->format('Y-m-d H:i:s') === $value;
}

if (isset($_GET['download']) && $_GET['download'] === '1') {
    $data = [];
    $queries = [
        'tenants' => [
            'SELECT t.tenant_id AS backup_id, t.first_name, t.last_name, t.gender,
                    t.contact_number, t.address, t.date_registered, u.email AS account_email
             FROM tenants t LEFT JOIN app_users u ON u.user_id = t.user_id
             WHERE t.owner_id = :owner_id ORDER BY t.tenant_id',
        ],
        'rooms' => [
            'SELECT room_id AS backup_id, room_number, floor, capacity, monthly_rent, status, created_at
             FROM rooms WHERE owner_id = :owner_id ORDER BY room_id',
        ],
        'contracts' => [
            'SELECT contract_id AS backup_id, tenant_id, room_id, monthly_rent,
                    start_date, end_date, contract_status, created_at
             FROM rental_contracts WHERE owner_id = :owner_id ORDER BY contract_id',
        ],
        'payments' => [
            'SELECT payment_id AS backup_id, contract_id, payment_date, billing_month,
                    amount, payment_method, payment_status, created_at
             FROM payments WHERE owner_id = :owner_id ORDER BY payment_id',
        ],
        'utility_bills' => [
            'SELECT bill_id AS backup_id, contract_id, electricity_bill, water_bill,
                    billing_month, total_amount, due_date, bill_status, created_at
             FROM utility_bills WHERE owner_id = :owner_id ORDER BY bill_id',
        ],
        'notifications' => [
            'SELECT notification_id AS backup_id, tenant_id, title, message, source_key, date_sent, status
             FROM notifications WHERE owner_id = :owner_id ORDER BY notification_id',
        ],
        'activity_logs' => [
            'SELECT l.log_id AS backup_id, u.email AS actor_email, l.activity, l.created_at
             FROM activity_logs l JOIN app_users u ON u.user_id = l.user_id
             WHERE l.owner_id = :owner_id ORDER BY l.log_id',
        ],
    ];

    foreach ($queries as $key => [$sql]) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':owner_id' => $ownerId]);
        $data[$key] = $stmt->fetchAll();
    }

    $backup = [
        'format' => 'staywise-backup',
        'version' => 1,
        'exported_at' => date(DATE_ATOM),
        'data' => $data,
    ];
    log_staywise_activity('Downloaded a property backup');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="staywise-backup-' . date('Y-m-d') . '.json"');
    echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_staywise_csrf()) {
        $message = 'Your session expired. Please try again.';
        $messageType = 'error';
    } elseif (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Choose a valid backup file to restore.';
        $messageType = 'error';
    } elseif ($_FILES['backup_file']['size'] > 15 * 1024 * 1024) {
        $message = 'Backup files must be 15 MB or smaller.';
        $messageType = 'error';
    } else {
        try {
            $backup = json_decode(
                file_get_contents($_FILES['backup_file']['tmp_name']),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $requiredTables = ['tenants', 'rooms', 'contracts', 'payments', 'utility_bills', 'notifications', 'activity_logs'];
            if (!is_array($backup) || ($backup['format'] ?? '') !== 'staywise-backup'
                || ($backup['version'] ?? null) !== 1 || !isset($backup['data']) || !is_array($backup['data'])) {
                throw new DomainException('This is not a supported StayWise backup.');
            }
            foreach ($requiredTables as $table) {
                if (!isset($backup['data'][$table]) || !is_array($backup['data'][$table])
                    || count($backup['data'][$table]) > 10000) {
                    throw new DomainException('The backup is missing valid ' . $table . ' data or exceeds the record limit.');
                }
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE app_users u JOIN tenants t ON t.user_id = u.user_id
                 SET u.status = \'pending\' WHERE t.owner_id = :owner_id AND u.role = \'tenant\''
            );
            $stmt->execute([':owner_id' => $ownerId]);

            foreach (['payments', 'utility_bills', 'rental_contracts', 'notifications', 'rooms', 'tenants', 'activity_logs'] as $table) {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE owner_id = :owner_id");
                $stmt->execute([':owner_id' => $ownerId]);
            }

            $tenantIds = [];
            foreach ($backup['data']['tenants'] as $row) {
                if (!is_array($row) || !isset($row['backup_id'], $row['first_name'], $row['last_name'], $row['gender'],
                    $row['contact_number'], $row['address'], $row['date_registered'])
                    || !in_array($row['gender'], ['Male', 'Female', 'Other'], true)
                    || !is_numeric($row['backup_id']) || (int) $row['backup_id'] < 1
                    || !is_string($row['first_name']) || $row['first_name'] === '' || strlen($row['first_name']) > 50
                    || !is_string($row['last_name']) || $row['last_name'] === '' || strlen($row['last_name']) > 50
                    || !is_string($row['contact_number']) || strlen($row['contact_number']) > 20
                    || !is_string($row['address']) || strlen($row['address']) > 150
                    || !backup_valid_date($row['date_registered'])
                    || (isset($row['backup_id']) && isset($tenantIds[(string) $row['backup_id']]))
                    || (isset($row['account_email']) && !is_string($row['account_email']))) {
                    throw new DomainException('The backup contains an invalid tenant record.');
                }

                $userId = null;
                $accountEmail = trim($row['account_email'] ?? '');
                if ($accountEmail !== '') {
                    if (filter_var($accountEmail, FILTER_VALIDATE_EMAIL) && strlen($accountEmail) <= 190) {
                        $stmt = $pdo->prepare(
                            "SELECT u.user_id FROM app_users u
                             WHERE u.email = :email AND u.role = 'tenant' AND u.status = 'pending'
                               AND NOT EXISTS (SELECT 1 FROM tenants t WHERE t.user_id = u.user_id)
                             LIMIT 1"
                        );
                        $stmt->execute([':email' => $accountEmail]);
                        $matchedUserId = $stmt->fetchColumn();
                        if ($matchedUserId !== false) {
                            $userId = (int) $matchedUserId;
                            $stmt = $pdo->prepare("UPDATE app_users SET status = 'active' WHERE user_id = :user_id");
                            $stmt->execute([':user_id' => $userId]);
                        }
                    } else {
                        throw new DomainException('The backup contains an invalid tenant account email.');
                    }
                }
                $stmt = $pdo->prepare(
                    'INSERT INTO tenants
                        (owner_id, user_id, first_name, last_name, gender, contact_number, address, date_registered)
                     VALUES (:owner_id, :user_id, :first_name, :last_name, :gender, :contact_number, :address, :date_registered)'
                );
                $stmt->execute([
                    ':owner_id' => $ownerId,
                    ':user_id' => $userId,
                    ':first_name' => $row['first_name'],
                    ':last_name' => $row['last_name'],
                    ':gender' => $row['gender'],
                    ':contact_number' => $row['contact_number'],
                    ':address' => $row['address'],
                    ':date_registered' => $row['date_registered'],
                ]);
                $tenantIds[(string) $row['backup_id']] = (int) $pdo->lastInsertId();
            }

            $roomIds = [];
            foreach ($backup['data']['rooms'] as $row) {
                if (!is_array($row) || !isset($row['backup_id'], $row['room_number'], $row['floor'],
                    $row['capacity'], $row['monthly_rent'], $row['status'])
                    || !in_array($row['status'], ['available', 'maintenance'], true)
                    || !is_numeric($row['backup_id']) || (int) $row['backup_id'] < 1
                    || !is_string($row['room_number']) || $row['room_number'] === '' || strlen($row['room_number']) > 60
                    || !is_numeric($row['floor']) || (int) $row['floor'] < 0
                    || !is_numeric($row['capacity']) || (int) $row['capacity'] < 1
                    || !is_numeric($row['monthly_rent']) || (float) $row['monthly_rent'] < 0
                    || isset($roomIds[(string) $row['backup_id']])
                    || (isset($row['created_at']) && !backup_valid_datetime($row['created_at']))) {
                    throw new DomainException('The backup contains an invalid room record.');
                }
                $stmt = $pdo->prepare(
                    'INSERT INTO rooms (owner_id, room_number, floor, capacity, monthly_rent, status, created_at)
                     VALUES (:owner_id, :room_number, :floor, :capacity, :monthly_rent, :status, :created_at)'
                );
                $stmt->execute([
                    ':owner_id' => $ownerId,
                    ':room_number' => $row['room_number'],
                    ':floor' => (int) $row['floor'],
                    ':capacity' => (int) $row['capacity'],
                    ':monthly_rent' => $row['monthly_rent'],
                    ':status' => $row['status'],
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                ]);
                $roomIds[(string) $row['backup_id']] = (int) $pdo->lastInsertId();
            }

            $contractIds = [];
            foreach ($backup['data']['contracts'] as $row) {
                if (!is_array($row) || !isset($row['backup_id'], $row['tenant_id'], $row['room_id'],
                    $row['monthly_rent'], $row['start_date'], $row['contract_status'])
                    || !isset($tenantIds[(string) $row['tenant_id']], $roomIds[(string) $row['room_id']])
                    || !in_array($row['contract_status'], ['active', 'ended', 'cancelled'], true)
                    || !is_numeric($row['backup_id']) || (int) $row['backup_id'] < 1
                    || !is_numeric($row['monthly_rent']) || (float) $row['monthly_rent'] < 0
                    || !backup_valid_date($row['start_date'])
                    || !backup_valid_date($row['end_date'] ?? null, true)
                    || isset($contractIds[(string) $row['backup_id']])
                    || (isset($row['created_at']) && !backup_valid_datetime($row['created_at']))) {
                    throw new DomainException('The backup contains an invalid rental contract or references missing records.');
                }
                $stmt = $pdo->prepare(
                    'INSERT INTO rental_contracts
                        (owner_id, tenant_id, room_id, monthly_rent, start_date, end_date, contract_status, created_at)
                     VALUES (:owner_id, :tenant_id, :room_id, :monthly_rent, :start_date, :end_date, :contract_status, :created_at)'
                );
                $stmt->execute([
                    ':owner_id' => $ownerId,
                    ':tenant_id' => $tenantIds[(string) $row['tenant_id']],
                    ':room_id' => $roomIds[(string) $row['room_id']],
                    ':monthly_rent' => $row['monthly_rent'],
                    ':start_date' => $row['start_date'],
                    ':end_date' => $row['end_date'] ?? null,
                    ':contract_status' => $row['contract_status'],
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                ]);
                $contractIds[(string) $row['backup_id']] = (int) $pdo->lastInsertId();
            }

            foreach ($backup['data']['payments'] as $row) {
                if (!is_array($row) || !isset($row['contract_id'], $row['payment_date'], $row['billing_month'],
                    $row['amount'], $row['payment_method'], $row['payment_status'])
                    || !isset($contractIds[(string) $row['contract_id']])
                    || !in_array($row['payment_status'], ['paid', 'pending', 'overdue'], true)
                    || !backup_valid_date($row['payment_date']) || !backup_valid_date($row['billing_month'])
                    || substr((string) $row['billing_month'], 8, 2) !== '01'
                    || !is_numeric($row['amount']) || (float) $row['amount'] <= 0
                    || !is_string($row['payment_method']) || $row['payment_method'] === '' || strlen($row['payment_method']) > 50
                    || (isset($row['created_at']) && !backup_valid_datetime($row['created_at']))) {
                    throw new DomainException('The backup contains an invalid payment record.');
                }
                $stmt = $pdo->prepare(
                    'INSERT INTO payments
                        (owner_id, contract_id, payment_date, billing_month, amount, payment_method, payment_status, created_at)
                     VALUES (:owner_id, :contract_id, :payment_date, :billing_month, :amount, :payment_method, :payment_status, :created_at)'
                );
                $stmt->execute([
                    ':owner_id' => $ownerId,
                    ':contract_id' => $contractIds[(string) $row['contract_id']],
                    ':payment_date' => $row['payment_date'],
                    ':billing_month' => $row['billing_month'],
                    ':amount' => $row['amount'],
                    ':payment_method' => $row['payment_method'],
                    ':payment_status' => $row['payment_status'],
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            foreach ($backup['data']['utility_bills'] as $row) {
                if (!is_array($row) || !isset($row['contract_id'], $row['electricity_bill'], $row['water_bill'],
                    $row['billing_month'], $row['bill_status'])
                    || !isset($contractIds[(string) $row['contract_id']])
                    || !in_array($row['bill_status'], ['unpaid', 'paid'], true)
                    || !is_numeric($row['electricity_bill']) || (float) $row['electricity_bill'] < 0
                    || !is_numeric($row['water_bill']) || (float) $row['water_bill'] < 0
                    || !backup_valid_date($row['billing_month'])
                    || substr((string) $row['billing_month'], 8, 2) !== '01'
                    || !backup_valid_date($row['due_date'] ?? null, true)
                    || (isset($row['created_at']) && !backup_valid_datetime($row['created_at']))) {
                    throw new DomainException('The backup contains an invalid utility bill.');
                }
                $electricity = (float) $row['electricity_bill'];
                $water = (float) $row['water_bill'];
                $stmt = $pdo->prepare(
                    'INSERT INTO utility_bills
                        (owner_id, contract_id, electricity_bill, water_bill, billing_month, total_amount, due_date, bill_status, created_at)
                     VALUES (:owner_id, :contract_id, :electricity, :water, :billing_month, :total_amount, :due_date, :bill_status, :created_at)'
                );
                $stmt->execute([
                    ':owner_id' => $ownerId,
                    ':contract_id' => $contractIds[(string) $row['contract_id']],
                    ':electricity' => $electricity,
                    ':water' => $water,
                    ':billing_month' => $row['billing_month'],
                    ':total_amount' => round($electricity + $water, 2),
                    ':due_date' => $row['due_date'] ?? null,
                    ':bill_status' => $row['bill_status'],
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            $notificationIds = [];
            foreach ($backup['data']['notifications'] as $row) {
                if (!is_array($row) || !isset($row['backup_id'], $row['tenant_id'], $row['title'], $row['message'], $row['status'])
                    || !isset($tenantIds[(string) $row['tenant_id']])
                    || !is_numeric($row['backup_id']) || (int) $row['backup_id'] < 1
                    || !is_string($row['title']) || $row['title'] === '' || strlen($row['title']) > 150
                    || !is_string($row['message']) || strlen($row['message']) > 65535
                    || !in_array($row['status'], ['unread', 'read'], true)
                    || (isset($row['date_sent']) && !backup_valid_datetime($row['date_sent']))
                    || (isset($row['backup_id']) && isset($notificationIds[(string) $row['backup_id']]))) {
                    throw new DomainException('The backup contains an invalid notification.');
                }
                $sourceKey = isset($row['source_key']) && $row['source_key'] !== ''
                    ? 'restore-' . $ownerId . '-' . substr(hash('sha256', (string) $row['source_key']), 0, 64)
                    : null;
                $stmt = $pdo->prepare(
                    'INSERT INTO notifications (owner_id, tenant_id, title, message, source_key, status, date_sent)
                     VALUES (:owner_id, :tenant_id, :title, :message, :source_key, :status, :date_sent)'
                );
                $stmt->execute([
                    ':owner_id' => $ownerId,
                    ':tenant_id' => $tenantIds[(string) $row['tenant_id']],
                    ':title' => $row['title'],
                    ':message' => $row['message'],
                    ':source_key' => $sourceKey,
                    ':status' => $row['status'],
                    ':date_sent' => $row['date_sent'] ?? date('Y-m-d H:i:s'),
                ]);
                $notificationIds[(string) $row['backup_id']] = true;
            }

            foreach ($backup['data']['activity_logs'] as $row) {
                if (!is_array($row) || !isset($row['activity'])
                    || !is_string($row['activity']) || $row['activity'] === '' || strlen($row['activity']) > 255
                    || (isset($row['created_at']) && !backup_valid_datetime($row['created_at']))) {
                    throw new DomainException('The backup contains an invalid activity log.');
                }
                $userId = (int) $_SESSION['staywise_user']['id'];
                if (!empty($row['actor_email']) && is_string($row['actor_email'])) {
                    $stmt = $pdo->prepare(
                        "SELECT u.user_id FROM app_users u
                         WHERE u.email = :email AND (
                           (u.role = 'owner' AND u.user_id = :owner_id)
                           OR (u.role = 'tenant' AND EXISTS (
                             SELECT 1 FROM tenants t
                             WHERE t.user_id = u.user_id AND t.owner_id = :tenant_owner_id
                           ))
                         ) LIMIT 1"
                    );
                    $stmt->execute([
                        ':email' => $row['actor_email'],
                        ':owner_id' => $ownerId,
                        ':tenant_owner_id' => $ownerId,
                    ]);
                    $foundUserId = $stmt->fetchColumn();
                    if ($foundUserId !== false) {
                        $userId = (int) $foundUserId;
                    }
                }
                $stmt = $pdo->prepare(
                    'INSERT INTO activity_logs (owner_id, user_id, activity, created_at)
                     VALUES (:owner_id, :user_id, :activity, :created_at)'
                );
                $stmt->execute([
                    ':owner_id' => $ownerId,
                    ':user_id' => $userId,
                    ':activity' => $row['activity'],
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            $pdo->commit();
            log_staywise_activity('Restored a property backup');
            $message = 'Backup restored. Your previous property records were replaced.';
        } catch (JsonException $e) {
            $message = 'The backup file is not valid JSON.';
            $messageType = 'error';
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $e->getMessage();
            $messageType = 'error';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Backup restore failed: ' . $e->getMessage());
            $message = 'The backup could not be restored. Verify that its records are consistent, then try again.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Backup and restore · StayWise</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261022-no-sidebar-icons">
    <link rel="stylesheet" href="assets/css/theme.css?v=20261022-home-nav-theme">
    <script src="https://code.iconify.design/iconify-icon/2.3.0/iconify-icon.min.js" defer></script>
    <script src="assets/js/theme.js?v=20261022-iconify"></script>
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>
    <main class="home-content">
        <header class="topbar"><p class="topbar-label">StayWise <span aria-hidden="true">/</span> Backup &amp; restore</p><div class="topbar-actions"><span class="topbar-user"><?= htmlspecialchars($_SESSION['staywise_user']['name'], ENT_QUOTES, 'UTF-8') ?></span><button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to light mode"><iconify-icon class="theme-toggle-icon" icon="lucide:sun" aria-hidden="true"></iconify-icon></button></div></header>
        <div class="page-head"><div><p class="eyebrow">Data protection</p><h1>Backup &amp; restore</h1><p>Download a copy of your property records or restore from a StayWise backup.</p></div></div>
        <?php if ($message !== ''): ?><div class="alert <?= $messageType === 'error' ? 'alert-error' : '' ?>" role="<?= $messageType ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="panel backup-grid">
            <div>
                <span class="stat-icon backup-icon" aria-hidden="true">↓</span>
                <h2>Download backup</h2>
                <p>Export tenant records, rooms, contracts, payments, utilities, notifications, and activity logs to a JSON file. Passwords are never included.</p>
                <a class="button" href="backup.php?download=1">Download backup</a>
            </div>
            <div>
                <span class="stat-icon backup-icon" aria-hidden="true">↑</span>
                <h2>Restore backup</h2>
                <p>Restoring replaces this owner's current property records. The owner account remains unchanged. Maximum file size: 15 MB.</p>
                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Restore this backup and replace all current property records? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <label for="backup_file">StayWise backup JSON</label>
                    <input id="backup_file" type="file" name="backup_file" accept="application/json,.json" required>
                    <button type="submit">Restore and replace data</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
