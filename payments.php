<?php
require_once __DIR__ . '/backend/auth_init.php';
require_once __DIR__ . '/Transaction.php';
require_staywise_owner();
$activePage = 'payments';
$ownerId = (int) $_SESSION['staywise_user']['owner_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_staywise_csrf()) {
        $message = 'Your session expired. Please try again.';
    } else {
        $contractId = filter_var($_POST['contract_id'] ?? null, FILTER_VALIDATE_INT);
        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $paymentDate = $_POST['payment_date'] ?? '';
        $billingMonthInput = $_POST['billing_month'] ?? '';
        $paymentMethod = $_POST['payment_method'] ?? '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
        $month = DateTimeImmutable::createFromFormat('!Y-m-d', $billingMonthInput);
        $allowedMethods = ['Cash', 'Bank transfer', 'E-wallet', 'Other'];

        if ($contractId === false || $contractId < 1 || $amount === false || $amount <= 0
            || !$date || $date->format('Y-m-d') !== $paymentDate
            || !$month || $month->format('Y-m-d') !== $billingMonthInput
            || $month->format('d') !== '01' || !in_array($paymentMethod, $allowedMethods, true)) {
            $message = 'Enter a valid contract, payment date, billing month, amount, and payment method.';
        } else {
            try {
                $transaction = new Transaction($pdo);
                $paymentId = $transaction->run(
                    function (PDO $connection) use (
                        $contractId,
                        $ownerId,
                        $billingMonthInput,
                        $amount,
                        $paymentDate,
                        $paymentMethod
                    ): int {
                        $stmt = $connection->prepare(
                            "SELECT c.contract_id, c.monthly_rent
                             FROM rental_contracts c
                             WHERE c.contract_id = :contract_id AND c.owner_id = :owner_id
                               AND c.contract_status IN ('active', 'ended') FOR UPDATE"
                        );
                        $stmt->execute([':contract_id' => $contractId, ':owner_id' => $ownerId]);
                        $contract = $stmt->fetch();
                        if (!$contract) {
                            throw new DomainException('Choose a contract belonging to your property.');
                        }

                        $stmt = $connection->prepare(
                            "SELECT COALESCE(SUM(amount), 0) FROM payments
                             WHERE owner_id = :owner_id AND contract_id = :contract_id
                               AND billing_month = :billing_month AND payment_status = 'paid'"
                        );
                        $stmt->execute([
                            ':owner_id' => $ownerId,
                            ':contract_id' => $contractId,
                            ':billing_month' => $billingMonthInput,
                        ]);
                        $paid = (float) $stmt->fetchColumn();
                        if ($paid + $amount > (float) $contract['monthly_rent'] + 0.001) {
                            throw new DomainException('This payment exceeds the remaining rent for the selected month.');
                        }

                        $stmt = $connection->prepare(
                            "INSERT INTO payments
                                (owner_id, contract_id, payment_date, billing_month, amount, payment_method, payment_status)
                             VALUES (:owner_id, :contract_id, :payment_date, :billing_month, :amount, :payment_method, 'paid')"
                        );
                        $stmt->execute([
                            ':owner_id' => $ownerId,
                            ':contract_id' => $contractId,
                            ':payment_date' => $paymentDate,
                            ':billing_month' => $billingMonthInput,
                            ':amount' => $amount,
                            ':payment_method' => $paymentMethod,
                        ]);

                        return (int) $connection->lastInsertId();
                    }
                );
                log_staywise_activity('Recorded payment #' . $paymentId . ' for contract #' . $contractId);
                $message = 'Payment recorded successfully.';
            } catch (DomainException $e) {
                $message = $e->getMessage();
            }
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT c.contract_id, c.monthly_rent, t.first_name, t.last_name, r.room_number
     FROM rental_contracts c
     JOIN tenants t ON t.tenant_id = c.tenant_id AND t.owner_id = c.owner_id
     JOIN rooms r ON r.room_id = c.room_id AND r.owner_id = c.owner_id
     WHERE c.owner_id = :owner_id AND c.contract_status IN ('active', 'ended')
     ORDER BY c.contract_id DESC"
);
$stmt->execute([':owner_id' => $ownerId]);
$contracts = $stmt->fetchAll();
$stmt = $pdo->prepare(
    'SELECT p.*, t.first_name, t.last_name, r.room_number
     FROM payments p
     JOIN rental_contracts c ON c.contract_id = p.contract_id AND c.owner_id = p.owner_id
     JOIN tenants t ON t.tenant_id = c.tenant_id AND t.owner_id = c.owner_id
     JOIN rooms r ON r.room_id = c.room_id AND r.owner_id = c.owner_id
     WHERE p.owner_id = :owner_id ORDER BY p.payment_date DESC, p.payment_id DESC LIMIT 100'
);
$stmt->execute([':owner_id' => $ownerId]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payments · StayWise</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261022-no-sidebar-icons">
    <link rel="stylesheet" href="assets/css/theme.css?v=20261022-home-nav-theme">
    <script src="https://code.iconify.design/iconify-icon/2.3.0/iconify-icon.min.js" defer></script>
    <script src="assets/js/theme.js?v=20261022-iconify"></script>
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>
    <main class="home-content">
        <header class="topbar"><p class="topbar-label">StayWise <span aria-hidden="true">/</span> Payments</p><div class="topbar-actions"><span class="topbar-user"><?= htmlspecialchars($_SESSION['staywise_user']['name'], ENT_QUOTES, 'UTF-8') ?></span><button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to light mode"><iconify-icon class="theme-toggle-icon" icon="lucide:sun" aria-hidden="true"></iconify-icon></button></div></header>
        <div class="page-head"><div><p class="eyebrow">Financial management</p><h1>Rental payments</h1><p>Record full or partial rent payments by contract and billing month.</p></div></div>
        <?php if ($message !== ''): ?><div class="alert" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="panel">
            <div class="panel-head"><div><h2>Record a payment</h2><p>Payments cannot exceed the remaining rent for that month.</p></div></div>
            <?php if (!$contracts): ?><p class="alert">No rental contracts are available for this property yet. Add a room, then create a contract for the tenant to make them appear here. <a href="contracts.php">Go to Contracts</a>.</p><?php endif; ?>
            <form method="POST" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <label>Tenant and room (from rental contracts)<select name="contract_id" required <?= !$contracts ? 'disabled' : '' ?>><option value="">Choose a contract</option><?php foreach ($contracts as $contract): ?><option value="<?= (int) $contract['contract_id'] ?>"><?= htmlspecialchars($contract['first_name'] . ' ' . $contract['last_name'] . ' · Room ' . $contract['room_number'], ENT_QUOTES, 'UTF-8') ?> · ₱<?= number_format((float) $contract['monthly_rent'], 2) ?>/month</option><?php endforeach; ?></select></label>
                <label>Payment date<input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required></label>
                <label>Billing month<input type="date" name="billing_month" value="<?= date('Y-m-01') ?>" required></label>
                <label>Amount<input type="number" name="amount" min="0.01" step="0.01" required></label>
                <label>Payment method<select name="payment_method" required><option>Cash</option><option>Bank transfer</option><option>E-wallet</option><option>Other</option></select></label>
                <button type="submit" <?= !$contracts ? 'disabled' : '' ?>>Save payment</button>
            </form>
        </section>
        <section class="panel history-panel">
            <div class="panel-head"><div><h2>Payment history</h2><p>Latest 100 recorded payments.</p></div></div>
            <table><thead><tr><th>Date</th><th>Tenant</th><th>Room</th><th>Billing month</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr><td><?= htmlspecialchars($payment['payment_date'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($payment['room_number'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($payment['billing_month'], ENT_QUOTES, 'UTF-8') ?></td><td>₱<?= number_format((float) $payment['amount'], 2) ?></td><td><?= htmlspecialchars($payment['payment_method'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(ucfirst($payment['payment_status']), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$payments): ?><tr><td colspan="7" class="empty-state">No payments recorded yet.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
    </main>
</body>
</html>
