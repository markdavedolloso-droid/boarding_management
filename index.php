<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_login();
$currentUser = $_SESSION['staywise_user'];
$ownerId = (int) $currentUser['owner_id'];
$activePage = 'overview';
$tenantFlashSuccess = (string) ($_SESSION['tenant_flash_success'] ?? '');
unset($_SESSION['tenant_flash_success']);

if ($currentUser['role'] === 'tenant') {
    $tenantId = (int) $currentUser['tenant_id'];
    $stmt = $pdo->prepare(
        "SELECT c.contract_id, c.start_date, c.end_date, r.room_number, r.floor, r.monthly_rent
         FROM rental_contracts c
         JOIN rooms r ON r.room_id = c.room_id AND r.owner_id = c.owner_id
         WHERE c.tenant_id = :tenant_id AND c.owner_id = :owner_id
           AND c.contract_status = 'active'
           AND c.start_date <= CURDATE()
           AND (c.end_date IS NULL OR c.end_date >= CURDATE())
         ORDER BY c.start_date DESC LIMIT 1"
    );
    $stmt->execute([':tenant_id' => $tenantId, ':owner_id' => $ownerId]);
    $contract = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT p.* FROM payments p
         JOIN rental_contracts c ON c.contract_id = p.contract_id AND c.owner_id = p.owner_id
         WHERE p.owner_id = :owner_id AND c.tenant_id = :tenant_id
         ORDER BY p.payment_date DESC, p.payment_id DESC LIMIT 8'
    );
    $stmt->execute([':owner_id' => $ownerId, ':tenant_id' => $tenantId]);
    $payments = $stmt->fetchAll();

    $rentBalance = 0.0;
    $utilityBalance = 0.0;
    if ($contract) {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE owner_id = :owner_id AND contract_id = :contract_id
               AND billing_month = DATE_FORMAT(CURDATE(), '%Y-%m-01') AND payment_status = 'paid'"
        );
        $stmt->execute([':owner_id' => $ownerId, ':contract_id' => $contract['contract_id']]);
        $rentBalance = max(0, (float) $contract['monthly_rent'] - (float) $stmt->fetchColumn());

    }
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(b.total_amount), 0) FROM utility_bills b
         JOIN rental_contracts c ON c.contract_id = b.contract_id AND c.owner_id = b.owner_id
         WHERE b.owner_id = :owner_id AND c.tenant_id = :tenant_id AND b.bill_status = 'unpaid'"
    );
    $stmt->execute([':owner_id' => $ownerId, ':tenant_id' => $tenantId]);
    $utilityBalance = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT * FROM notifications WHERE tenant_id = :tenant_id AND owner_id = :owner_id
         ORDER BY date_sent DESC LIMIT 5'
    );
    $stmt->execute([':tenant_id' => $tenantId, ':owner_id' => $ownerId]);
    $notifications = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE owner_id = :owner_id');
    $stmt->execute([':owner_id' => $ownerId]);
    $totalTenants = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE owner_id = :owner_id AND date_registered = CURDATE()');
    $stmt->execute([':owner_id' => $ownerId]);
    $registeredToday = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM rental_contracts
         WHERE owner_id = :owner_id AND contract_status = 'active'
           AND start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE())"
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $activeContracts = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) FROM payments
         WHERE owner_id = :owner_id AND payment_status = \'paid\'
           AND payment_date >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')
           AND payment_date < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, \'%Y-%m-01\')'
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $monthlyIncome = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT r.room_id, r.room_number, r.capacity,
                COUNT(c.contract_id) AS occupant_count
         FROM rooms r
         LEFT JOIN rental_contracts c
           ON c.room_id = r.room_id AND c.owner_id = r.owner_id
          AND c.contract_status = \'active\' AND c.start_date <= CURDATE()
          AND (c.end_date IS NULL OR c.end_date >= CURDATE())
         WHERE r.owner_id = :owner_id AND r.status = \'available\'
         GROUP BY r.room_id, r.room_number, r.capacity'
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $roomRows = $stmt->fetchAll();
    $availableSpaces = 0;
    $totalCapacity = 0;
    $occupiedSpaces = 0;
    foreach ($roomRows as $room) {
        $totalCapacity += (int) $room['capacity'];
        $occupiedSpaces += (int) $room['occupant_count'];
        $availableSpaces += max(0, (int) $room['capacity'] - (int) $room['occupant_count']);
    }
    $occupancyRate = $totalCapacity > 0 ? (int) round($occupiedSpaces * 100 / $totalCapacity) : 0;

    $stmt = $pdo->prepare('SELECT * FROM tenants WHERE owner_id = :owner_id ORDER BY tenant_id DESC LIMIT 5');
    $stmt->execute([':owner_id' => $ownerId]);
    $recentTenants = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT l.activity, l.created_at, u.full_name
         FROM activity_logs l JOIN app_users u ON u.user_id = l.user_id
         WHERE l.owner_id = :owner_id ORDER BY l.created_at DESC LIMIT 5'
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $recentActivity = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications
         WHERE owner_id = :owner_id AND date_sent >= DATE_SUB(NOW(), INTERVAL 3 DAY)
           AND title = 'Payment reminder'"
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $recentReminderCount = (int) $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>StayWise · Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261015">
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>

    <main class="home-content">
        <header class="topbar">
            <p class="topbar-label">StayWise <span aria-hidden="true">/</span> Overview</p>
            <div class="topbar-actions">
                <?php if ($currentUser['role'] === 'owner'): ?>
                    <a class="button secondary" href="view_tenants.php">⌕ Search records</a>
                <?php endif; ?>
                <span class="topbar-user"><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </header>

        <?php if ($currentUser['role'] === 'tenant'): ?>
            <div class="page-head">
                <div>
                    <p class="eyebrow">Tenant portal</p>
                    <h1>Welcome, <?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <p style="margin:5px 0 0">Your room, rent, and recent account updates.</p>
                </div>
            </div>
            <section class="stats" aria-label="Your rental summary">
                <div class="stat">
                    <span class="stat-icon" aria-hidden="true">⌂</span>
                    <div class="stat-label">Room</div>
                    <div class="stat-value"><?= $contract ? htmlspecialchars($contract['room_number'], ENT_QUOTES, 'UTF-8') : 'Unassigned' ?></div>
                    <div class="stat-foot"><?= $contract ? 'Floor ' . (int) $contract['floor'] : 'Contact your manager' ?></div>
                </div>
                <div class="stat">
                    <span class="stat-icon" aria-hidden="true">₱</span>
                    <div class="stat-label">Current balance</div>
                    <div class="stat-value">₱<?= number_format($rentBalance + $utilityBalance, 2) ?></div>
                    <div class="stat-foot">Rent plus unpaid utilities</div>
                </div>
                <div class="stat">
                    <span class="stat-icon" aria-hidden="true">▤</span>
                    <div class="stat-label">Recent payments</div>
                    <div class="stat-value"><?= count($payments) ?></div>
                    <div class="stat-foot">Latest payment records</div>
                </div>
                <div class="stat">
                    <span class="stat-icon" aria-hidden="true">◉</span>
                    <div class="stat-label">Notifications</div>
                    <div class="stat-value"><?= count($notifications) ?></div>
                    <div class="stat-foot">Recent account updates</div>
                </div>
            </section>
            <section class="dashboard-grid">
                <div class="panel">
                    <div class="panel-head"><div><h2>Payment history</h2><p>Your latest rent payments.</p></div></div>
                    <table>
                        <thead><tr><th>Payment date</th><th>Billing month</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['payment_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($payment['billing_month'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>₱<?= number_format((float) $payment['amount'], 2) ?></td>
                                <td><?= htmlspecialchars(ucfirst($payment['payment_status']), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$payments): ?><tr><td colspan="4" class="empty-state">No payments recorded yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="panel">
                    <div class="panel-head"><div><h2>Current rental</h2><p>Your agreement and outstanding charges.</p></div></div>
                    <?php if ($contract): ?>
                        <p><strong>Room <?= htmlspecialchars($contract['room_number'], ENT_QUOTES, 'UTF-8') ?></strong> · Floor <?= (int) $contract['floor'] ?></p>
                        <p>Rent: ₱<?= number_format((float) $contract['monthly_rent'], 2) ?> per month</p>
                        <p>Contract: <?= htmlspecialchars($contract['start_date'], ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars($contract['end_date'] ?: 'open-ended', ENT_QUOTES, 'UTF-8') ?></p>
                        <p>Unpaid utilities: ₱<?= number_format($utilityBalance, 2) ?></p>
                    <?php else: ?>
                        <p>No active rental agreement is linked to your tenant account.</p>
                    <?php endif; ?>
                </div>
                <div class="panel">
                    <div class="panel-head"><div><h2>Notifications</h2><p>Updates from your manager.</p></div><a href="notifications.php">View all →</a></div>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="activity-item">
                            <strong><?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <p><?= nl2br(htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8')) ?></p>
                            <small><?= htmlspecialchars($notification['date_sent'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$notifications): ?><p class="empty-state">No notifications yet.</p><?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <div class="page-head">
                <div>
                    <p class="eyebrow">Boarding house management</p>
                    <h1>Your property, clearly managed</h1>
                    <p style="margin:5px 0 0">Keep tenant information organized in one simple workspace.</p>
                </div>
                <button class="button" type="button" data-open-tenant-modal>＋ Add tenant</button>
            </div>

            <section class="stats" aria-label="Property statistics">
                <div class="stat"><span class="stat-icon" aria-hidden="true">♙</span><div class="stat-label">Total tenants</div><div class="stat-value"><?= number_format($totalTenants) ?></div><div class="stat-foot">● Registered tenant records</div></div>
                <div class="stat"><span class="stat-icon" aria-hidden="true">⌂</span><div class="stat-label">Vacant spaces</div><div class="stat-value"><?= number_format($availableSpaces) ?></div><div class="stat-foot"><?= number_format($occupancyRate) ?>% occupancy · updates automatically</div></div>
                <div class="stat"><span class="stat-icon" aria-hidden="true">₱</span><div class="stat-label">Monthly income</div><div class="stat-value">₱<?= number_format($monthlyIncome, 2) ?></div><div class="stat-foot">Paid this month</div></div>
                <div class="stat"><span class="stat-icon" aria-hidden="true">▤</span><div class="stat-label">Active contracts</div><div class="stat-value"><?= number_format($activeContracts) ?></div><div class="stat-foot"><?= number_format($registeredToday) ?> tenants added today</div></div>
            </section>

            <section class="dashboard-grid">
                <div class="panel recent-panel">
                    <div class="panel-head"><div><h2>Tenant management</h2><p>Keep every resident record up to date.</p></div></div>
                    <p>Register tenants, update their details, search contact information, and review existing records from one workspace.</p>
                    <div class="quick-actions">
                        <button class="button" type="button" data-open-tenant-modal>＋ Register tenant</button>
                        <a class="button secondary" href="view_tenants.php">View all tenants →</a>
                    </div>
                    <form class="inline-form" method="GET" action="view_tenants.php" role="search">
                        <input type="search" name="keyword" placeholder="Search tenant records..." aria-label="Search tenant records">
                        <button type="submit">Search</button>
                    </form>
                </div>
                <div class="panel recent-panel">
                    <div class="panel-head"><div><h2>Recently added tenants</h2><p>Your latest tenant records.</p></div><a href="view_tenants.php">View all →</a></div>
                    <table>
                        <thead><tr><th>Tenant</th><th>Gender</th><th>Contact</th><th>Date registered</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentTenants as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><?= htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['contact_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['date_registered'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><a href="view_tenants.php?edit=<?= (int) $row['tenant_id'] ?>">Edit record</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentTenants): ?><tr><td colspan="5" class="empty-state">No tenant records yet. Register your first tenant to get started.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="panel recent-panel">
                    <div class="panel-head"><div><h2>Recent activity</h2><p>Latest changes to your records.</p></div><a href="activity_logs.php">View logs →</a></div>
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <strong><?= htmlspecialchars($activity['activity'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <p><?= htmlspecialchars($activity['full_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($activity['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$recentActivity): ?><p class="empty-state">No activity recorded yet.</p><?php endif; ?>
                    <?php if ($recentReminderCount > 0): ?><p class="alert"><?= number_format($recentReminderCount) ?> payment reminders sent in the last 3 days.</p><?php endif; ?>
                </div>
            </section>
            <?php if ($tenantFlashSuccess !== ''): ?><div class="alert" role="status"><?= htmlspecialchars($tenantFlashSuccess, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php $tenantModalReturn = 'index.php'; require __DIR__ . '/modals/tenant_modal.php'; ?>
        <?php endif; ?>
    </main>
</body>
</html>
