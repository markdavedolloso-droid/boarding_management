<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_login();
$currentUser = $_SESSION['staywise_user'];
$ownerId = (int) $currentUser['owner_id'];
$activePage = 'notifications';
$message = '';

if ($currentUser['role'] === 'owner') {
    $stmt = $pdo->prepare(
        "SELECT c.contract_id, c.tenant_id, c.monthly_rent, c.start_date,
                t.first_name, t.last_name,
                COALESCE((SELECT SUM(p.amount) FROM payments p
                          WHERE p.contract_id = c.contract_id AND p.owner_id = c.owner_id
                            AND p.billing_month = DATE_FORMAT(CURDATE(), '%Y-%m-01')
                            AND p.payment_status = 'paid'), 0) AS paid_this_month
         FROM rental_contracts c
         JOIN tenants t ON t.tenant_id = c.tenant_id AND t.owner_id = c.owner_id
         WHERE c.owner_id = :owner_id AND c.contract_status = 'active'
           AND c.start_date <= CURDATE() AND (c.end_date IS NULL OR c.end_date >= CURDATE())"
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $activeContracts = $stmt->fetchAll();
    $today = new DateTimeImmutable('today');
    $monthStart = new DateTimeImmutable(date('Y-m-01'));
    foreach ($activeContracts as $contract) {
        $rentDueDay = (int) substr($contract['start_date'], 8, 2);
        $dueDate = $monthStart->setDate(
            (int) $monthStart->format('Y'),
            (int) $monthStart->format('m'),
            min($rentDueDay, (int) $monthStart->format('t'))
        );
        $remaining = round((float) $contract['monthly_rent'] - (float) $contract['paid_this_month'], 2);
        $rentSourceKey = 'rent-' . $contract['contract_id'] . '-' . $monthStart->format('Y-m');
        if ($remaining <= 0) {
            $stmt = $pdo->prepare("UPDATE notifications SET status = 'read' WHERE owner_id = :owner_id AND source_key = :source_key");
            $stmt->execute([':owner_id' => $ownerId, ':source_key' => $rentSourceKey]);
        } elseif ($today >= $dueDate->modify('-3 days')) {
            $dueText = $today > $dueDate ? 'was due on ' . $dueDate->format('M j') : 'is due on ' . $dueDate->format('M j');
            $stmt = $pdo->prepare(
                'INSERT INTO notifications (owner_id, tenant_id, title, message, source_key)
                 VALUES (:owner_id, :tenant_id, :title, :message, :source_key)
                 ON DUPLICATE KEY UPDATE message = VALUES(message)'
            );
            $stmt->execute([
                ':owner_id' => $ownerId,
                ':tenant_id' => $contract['tenant_id'],
                ':title' => 'Payment reminder',
                ':message' => 'Your rent balance of ₱' . number_format($remaining, 2) . ' ' . $dueText . '.',
                ':source_key' => 'rent-' . $contract['contract_id'] . '-' . $monthStart->format('Y-m'),
            ]);
        }
    }

    $stmt = $pdo->prepare(
        "SELECT b.bill_id, c.tenant_id, b.total_amount, b.due_date, t.first_name, t.last_name
         FROM utility_bills b
         JOIN rental_contracts c ON c.contract_id = b.contract_id AND c.owner_id = b.owner_id
         JOIN tenants t ON t.tenant_id = c.tenant_id AND t.owner_id = c.owner_id
         WHERE b.owner_id = :owner_id AND b.bill_status = 'unpaid' AND b.due_date IS NOT NULL
           AND b.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)"
    );
    $stmt->execute([':owner_id' => $ownerId]);
    foreach ($stmt->fetchAll() as $bill) {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (owner_id, tenant_id, title, message, source_key)
             VALUES (:owner_id, :tenant_id, :title, :message, :source_key)
             ON DUPLICATE KEY UPDATE message = VALUES(message)'
        );
        $stmt->execute([
            ':owner_id' => $ownerId,
            ':tenant_id' => $bill['tenant_id'],
            ':title' => 'Utility bill reminder',
            ':message' => 'Your utility bill of ₱' . number_format((float) $bill['total_amount'], 2)
                . ' is due on ' . $bill['due_date'] . '.',
            ':source_key' => 'bill-' . $bill['bill_id'],
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_staywise_csrf()) {
        $message = 'Your session expired. Please try again.';
    } elseif ($currentUser['role'] === 'tenant' && ($_POST['action'] ?? '') === 'read') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        $stmt = $pdo->prepare(
            "UPDATE notifications SET status = 'read'
             WHERE notification_id = :notification_id AND owner_id = :owner_id AND tenant_id = :tenant_id"
        );
        $stmt->execute([
            ':notification_id' => $notificationId,
            ':owner_id' => $ownerId,
            ':tenant_id' => $currentUser['tenant_id'],
        ]);
        $message = 'Notification marked as read.';
    } elseif ($currentUser['role'] === 'owner' && ($_POST['action'] ?? '') === 'send') {
        $tenantChoice = $_POST['tenant_id'] ?? 'all';
        $title = trim($_POST['title'] ?? '');
        $text = trim($_POST['message'] ?? '');
        if ($title === '' || strlen($title) > 150 || $text === '' || strlen($text) > 5000) {
            $message = 'Enter a title and message (up to 5,000 characters).';
        } else {
            $stmt = $pdo->prepare(
                'SELECT tenant_id FROM tenants WHERE owner_id = :owner_id'
                . ($tenantChoice === 'all' ? '' : ' AND tenant_id = :tenant_id')
            );
            $params = [':owner_id' => $ownerId];
            if ($tenantChoice !== 'all') {
                $params[':tenant_id'] = (int) $tenantChoice;
            }
            $stmt->execute($params);
            $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!$recipients) {
                $message = 'No matching tenant records were found.';
            } else {
                $pdo->beginTransaction();
                try {
                    $insert = $pdo->prepare(
                        'INSERT INTO notifications (owner_id, tenant_id, title, message)
                         VALUES (:owner_id, :tenant_id, :title, :message)'
                    );
                    foreach ($recipients as $tenantId) {
                        $insert->execute([
                            ':owner_id' => $ownerId,
                            ':tenant_id' => $tenantId,
                            ':title' => $title,
                            ':message' => $text,
                        ]);
                    }
                    $pdo->commit();
                    log_staywise_activity('Sent notification to ' . count($recipients) . ' tenant(s)');
                    $message = 'Notification sent to ' . count($recipients) . ' tenant(s).';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
        }
    }
}

if ($currentUser['role'] === 'tenant') {
    $stmt = $pdo->prepare(
        'SELECT * FROM notifications WHERE tenant_id = :tenant_id AND owner_id = :owner_id
         ORDER BY date_sent DESC LIMIT 100'
    );
    $stmt->execute([':tenant_id' => $currentUser['tenant_id'], ':owner_id' => $ownerId]);
    $notifications = $stmt->fetchAll();
    $tenantOptions = [];
} else {
    $stmt = $pdo->prepare(
        'SELECT n.*, t.first_name, t.last_name
         FROM notifications n JOIN tenants t ON t.tenant_id = n.tenant_id AND t.owner_id = n.owner_id
         WHERE n.owner_id = :owner_id ORDER BY n.date_sent DESC LIMIT 100'
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $notifications = $stmt->fetchAll();
    $stmt = $pdo->prepare(
        'SELECT tenant_id, first_name, last_name FROM tenants WHERE owner_id = :owner_id ORDER BY last_name, first_name'
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $tenantOptions = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifications · StayWise</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261015">
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>
    <main class="home-content">
        <header class="topbar"><p class="topbar-label">StayWise <span aria-hidden="true">/</span> Notifications</p><span class="topbar-user"><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></span></header>
        <div class="page-head"><div><p class="eyebrow">In-app updates</p><h1>Notifications</h1><p><?= $currentUser['role'] === 'owner' ? 'Send announcements and review automatic payment reminders.' : 'Announcements and payment reminders from your manager.' ?></p></div></div>
        <?php if ($message !== ''): ?><div class="alert" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="panel notifications-panel">
        <?php if ($currentUser['role'] === 'owner'): ?>
            <div class="panel-head"><div><h2>Send an announcement</h2><p>Notifications are delivered in the tenant portal.</p></div></div>
            <form method="POST" class="notification-compose-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="send">
                <label for="tenant_id">Recipients</label>
                <select id="tenant_id" name="tenant_id"><option value="all">All tenants</option><?php foreach ($tenantOptions as $tenant): ?><option value="<?= (int) $tenant['tenant_id'] ?>"><?= htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
                <label for="title">Title</label><input id="title" name="title" maxlength="150" required>
                <label for="message">Message</label><textarea id="message" name="message" rows="4" maxlength="5000" required></textarea>
                <button type="submit" <?= !$tenantOptions ? 'disabled' : '' ?>>Send notification</button>
            </form>
        <?php endif; ?>
            <div class="notification-list-head">
            <div class="panel-head"><div><h2><?= $currentUser['role'] === 'owner' ? 'Recent notifications' : 'Your notifications' ?></h2><p><?= count($notifications) ?> recent items.</p></div></div>
            </div>
            <?php foreach ($notifications as $notification): ?>
                <article class="notification-item <?= $notification['status'] === 'unread' ? 'is-unread' : '' ?>">
                    <div>
                        <div class="notification-heading">
                            <strong><?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($currentUser['role'] === 'owner'): ?><span><?= htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                        </div>
                        <p><?= nl2br(htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8')) ?></p>
                        <small><?= htmlspecialchars($notification['date_sent'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(ucfirst($notification['status']), ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                    <?php if ($currentUser['role'] === 'tenant' && $notification['status'] === 'unread'): ?>
                        <form method="POST" class="table-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="read">
                            <input type="hidden" name="notification_id" value="<?= (int) $notification['notification_id'] ?>">
                            <button type="submit">Mark as read</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if (!$notifications): ?><p class="empty-state">No notifications yet.</p><?php endif; ?>
        </section>
    </main>
</body>
</html>
