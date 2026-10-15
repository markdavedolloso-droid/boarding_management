<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
$activePage = 'reports';
$ownerId = (int) $_SESSION['staywise_user']['owner_id'];
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$error = '';
$startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
$endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $end);

if (!$startDate || $startDate->format('Y-m-d') !== $start
    || !$endDate || $endDate->format('Y-m-d') !== $end || $startDate > $endDate) {
    $error = 'Choose a valid date range where the start is before the end.';
    $start = date('Y-m-01');
    $end = date('Y-m-d');
    $startDate = new DateTimeImmutable($start);
    $endDate = new DateTimeImmutable($end);
}

if (isset($_GET['export']) && $_GET['export'] === 'payments' && $error === '') {
    $stmt = $pdo->prepare(
        'SELECT p.payment_date, t.first_name, t.last_name, r.room_number,
                p.billing_month, p.amount, p.payment_method, p.payment_status
         FROM payments p
         JOIN rental_contracts c ON c.contract_id = p.contract_id AND c.owner_id = p.owner_id
         JOIN tenants t ON t.tenant_id = c.tenant_id AND t.owner_id = c.owner_id
         JOIN rooms r ON r.room_id = c.room_id AND r.owner_id = c.owner_id
         WHERE p.owner_id = :owner_id AND p.payment_date BETWEEN :start_date AND :end_date
         ORDER BY p.payment_date, p.payment_id'
    );
    $stmt->execute([':owner_id' => $ownerId, ':start_date' => $start, ':end_date' => $end]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="staywise-payments-' . $start . '-to-' . $end . '.csv"');
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['Payment date', 'Tenant', 'Room', 'Billing month', 'Amount', 'Payment method', 'Status']);
    foreach ($stmt->fetchAll() as $row) {
        $tenantName = $row['first_name'] . ' ' . $row['last_name'];
        foreach (['tenantName', 'room_number', 'payment_method', 'payment_status'] as $field) {
            $value = $$field ?? $row[$field] ?? null;
            if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
                if ($field === 'tenantName') {
                    $tenantName = "'" . $tenantName;
                } else {
                    $row[$field] = "'" . $value;
                }
            }
        }
        fputcsv($output, [
            $row['payment_date'],
            $tenantName,
            $row['room_number'],
            $row['billing_month'],
            $row['amount'],
            $row['payment_method'],
            $row['payment_status'],
        ]);
    }
    fclose($output);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM payments
     WHERE owner_id = :owner_id AND payment_status = 'paid'
       AND payment_date BETWEEN :start_date AND :end_date"
);
$stmt->execute([':owner_id' => $ownerId, ':start_date' => $start, ':end_date' => $end]);
$income = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM rental_contracts
     WHERE owner_id = :owner_id AND contract_status = 'active'
       AND start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE())"
);
$stmt->execute([':owner_id' => $ownerId]);
$currentOccupants = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT COALESCE(SUM(capacity), 0) FROM rooms WHERE owner_id = :owner_id AND status = \'available\''
);
$stmt->execute([':owner_id' => $ownerId]);
$roomCapacity = (int) $stmt->fetchColumn();
$occupancyRate = $roomCapacity > 0 ? min(100, (int) round($currentOccupants * 100 / $roomCapacity)) : 0;

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_amount), 0) FROM utility_bills
     WHERE owner_id = :owner_id AND billing_month BETWEEN :start_month AND :end_month"
);
$stmt->execute([
    ':owner_id' => $ownerId,
    ':start_month' => $startDate->modify('first day of this month')->format('Y-m-d'),
    ':end_month' => $endDate->modify('first day of this month')->format('Y-m-d'),
]);
$billedUtilities = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS report_month, SUM(amount) AS total
     FROM payments
     WHERE owner_id = :owner_id AND payment_status = 'paid'
       AND payment_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
       AND payment_date < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')
     GROUP BY report_month ORDER BY report_month"
);
$stmt->execute([':owner_id' => $ownerId]);
$monthlyTotals = $stmt->fetchAll();
$maxMonthlyIncome = max(array_map(static fn(array $row): float => (float) $row['total'], $monthlyTotals) ?: [0]);

$stmt = $pdo->prepare(
    'SELECT p.payment_date, t.first_name, t.last_name, r.room_number,
            p.billing_month, p.amount, p.payment_method, p.payment_status
     FROM payments p
     JOIN rental_contracts c ON c.contract_id = p.contract_id AND c.owner_id = p.owner_id
     JOIN tenants t ON t.tenant_id = c.tenant_id AND t.owner_id = c.owner_id
     JOIN rooms r ON r.room_id = c.room_id AND r.owner_id = c.owner_id
     WHERE p.owner_id = :owner_id AND p.payment_date BETWEEN :start_date AND :end_date
     ORDER BY p.payment_date DESC LIMIT 100'
);
$stmt->execute([':owner_id' => $ownerId, ':start_date' => $start, ':end_date' => $end]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports · StayWise</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261015">
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>
    <main class="home-content">
        <header class="topbar"><p class="topbar-label">StayWise <span aria-hidden="true">/</span> Reports</p><span class="topbar-user"><?= htmlspecialchars($_SESSION['staywise_user']['name'], ENT_QUOTES, 'UTF-8') ?></span></header>
        <div class="page-head"><div><p class="eyebrow">Property insights</p><h1>Reports</h1><p>Review rental income, occupancy, and utility billing.</p></div></div>
        <?php if ($error !== ''): ?><div class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="panel">
            <form method="GET" class="report-filter">
                <label>From<input type="date" name="start" value="<?= htmlspecialchars($start, ENT_QUOTES, 'UTF-8') ?>" required></label>
                <label>To<input type="date" name="end" value="<?= htmlspecialchars($end, ENT_QUOTES, 'UTF-8') ?>" required></label>
                <button type="submit">Apply range</button>
                <a class="button secondary" href="?start=<?= rawurlencode($start) ?>&amp;end=<?= rawurlencode($end) ?>&amp;export=payments">Download payment CSV</a>
            </form>
        </section>
        <section class="stats">
            <div class="stat"><span class="stat-icon" aria-hidden="true">₱</span><div class="stat-label">Rental income</div><div class="stat-value">₱<?= number_format($income, 2) ?></div><div class="stat-foot"><?= htmlspecialchars($start, ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars($end, ENT_QUOTES, 'UTF-8') ?></div></div>
            <div class="stat"><span class="stat-icon" aria-hidden="true">⌂</span><div class="stat-label">Current occupancy</div><div class="stat-value"><?= $occupancyRate ?>%</div><div class="stat-foot"><?= $currentOccupants ?> occupied spaces / <?= $roomCapacity ?> capacity</div></div>
            <div class="stat"><span class="stat-icon" aria-hidden="true">▤</span><div class="stat-label">Utilities billed</div><div class="stat-value">₱<?= number_format($billedUtilities, 2) ?></div><div class="stat-foot">Bills in selected months</div></div>
            <div class="stat"><span class="stat-icon" aria-hidden="true">↗</span><div class="stat-label">Payments in range</div><div class="stat-value"><?= count($payments) ?></div><div class="stat-foot">Latest 100 listed below</div></div>
        </section>
        <section class="panel">
            <div class="panel-head"><div><h2>Monthly rental income</h2><p>Paid rent over the last six months.</p></div></div>
            <?php foreach ($monthlyTotals as $monthRow): $barWidth = $maxMonthlyIncome > 0 ? (int) round((float) $monthRow['total'] * 100 / $maxMonthlyIncome) : 0; ?>
                <div class="report-bar-row">
                    <span><?= htmlspecialchars(date('M Y', strtotime($monthRow['report_month'] . '-01')), ENT_QUOTES, 'UTF-8') ?></span>
                    <div class="report-bar-track"><div class="report-bar" style="width: <?= $barWidth ?>%"></div></div>
                    <strong>₱<?= number_format((float) $monthRow['total'], 2) ?></strong>
                </div>
            <?php endforeach; ?>
            <?php if (!$monthlyTotals): ?><p class="empty-state">No paid rent recorded in the past six months.</p><?php endif; ?>
        </section>
        <section class="panel history-panel">
            <div class="panel-head"><div><h2>Payments in selected period</h2><p>Up to 100 most recent results.</p></div></div>
            <table><thead><tr><th>Date</th><th>Tenant</th><th>Room</th><th>Billing month</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($payments as $payment): ?><tr><td><?= htmlspecialchars($payment['payment_date'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($payment['room_number'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($payment['billing_month'], ENT_QUOTES, 'UTF-8') ?></td><td>₱<?= number_format((float) $payment['amount'], 2) ?></td><td><?= htmlspecialchars($payment['payment_method'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(ucfirst($payment['payment_status']), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?>
                <?php if (!$payments): ?><tr><td colspan="7" class="empty-state">No payments found for this date range.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
    </main>
</body>
</html>
