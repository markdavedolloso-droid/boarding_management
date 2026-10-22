<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
$activePage = 'utilities';
$ownerId = (int) $_SESSION['staywise_user']['owner_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_staywise_csrf()) {
        $message = 'Your session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'mark_paid') {
        $billId = (int) ($_POST['bill_id'] ?? 0);
        $stmt = $pdo->prepare(
            "UPDATE utility_bills SET bill_status = 'paid'
             WHERE bill_id = :bill_id AND owner_id = :owner_id AND bill_status = 'unpaid'"
        );
        $stmt->execute([':bill_id' => $billId, ':owner_id' => $ownerId]);
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare(
                "UPDATE notifications SET status = 'read'
                 WHERE owner_id = :owner_id AND source_key = :source_key"
            );
            $stmt->execute([':owner_id' => $ownerId, ':source_key' => 'bill-' . $billId]);
            log_staywise_activity('Marked utility bill #' . $billId . ' paid');
            $message = 'Utility bill marked as paid.';
        }
    } elseif (($_POST['action'] ?? '') === 'create') {
        $contractId = filter_var($_POST['contract_id'] ?? null, FILTER_VALIDATE_INT);
        $electricity = filter_var($_POST['electricity_bill'] ?? null, FILTER_VALIDATE_FLOAT);
        $water = filter_var($_POST['water_bill'] ?? null, FILTER_VALIDATE_FLOAT);
        $billingMonthInput = $_POST['billing_month'] ?? '';
        $dueDateInput = $_POST['due_date'] ?? '';
        $month = DateTimeImmutable::createFromFormat('!Y-m-d', $billingMonthInput);
        $dueDate = $dueDateInput === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $dueDateInput);

        if ($contractId === false || $contractId < 1 || $electricity === false || $electricity < 0
            || $water === false || $water < 0 || !$month || $month->format('Y-m-d') !== $billingMonthInput
            || $month->format('d') !== '01'
            || ($dueDateInput !== '' && (!$dueDate || $dueDate->format('Y-m-d') !== $dueDateInput))) {
            $message = 'Enter a valid contract, billing month, bill amounts, and optional due date.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT contract_id FROM rental_contracts
                 WHERE contract_id = :contract_id AND owner_id = :owner_id AND contract_status <> \'cancelled\''
            );
            $stmt->execute([':contract_id' => $contractId, ':owner_id' => $ownerId]);
            if ($stmt->fetchColumn() === false) {
                $message = 'Choose a contract belonging to your property.';
            } else {
                try {
                    $total = round($electricity + $water, 2);
                    $stmt = $pdo->prepare(
                        "INSERT INTO utility_bills
                            (owner_id, contract_id, electricity_bill, water_bill, billing_month, total_amount, due_date)
                         VALUES (:owner_id, :contract_id, :electricity, :water, :billing_month, :total, :due_date)"
                    );
                    $stmt->execute([
                        ':owner_id' => $ownerId,
                        ':contract_id' => $contractId,
                        ':electricity' => $electricity,
                        ':water' => $water,
                        ':billing_month' => $billingMonthInput,
                        ':total' => $total,
                        ':due_date' => $dueDateInput !== '' ? $dueDateInput : null,
                    ]);
                    $billId = (int) $pdo->lastInsertId();
                    log_staywise_activity('Created utility bill #' . $billId);
                    $message = 'Utility bill recorded.';
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        $message = 'A utility bill already exists for this contract and month.';
                    } else {
                        throw $e;
                    }
                }
            }
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT c.contract_id, t.first_name, t.last_name, r.room_number
     FROM rental_contracts c
     JOIN tenants t ON t.tenant_id = c.tenant_id AND t.owner_id = c.owner_id
     JOIN rooms r ON r.room_id = c.room_id AND r.owner_id = c.owner_id
     WHERE c.owner_id = :owner_id AND c.contract_status <> 'cancelled'
     ORDER BY c.contract_id DESC"
);
$stmt->execute([':owner_id' => $ownerId]);
$contracts = $stmt->fetchAll();
$stmt = $pdo->prepare(
    'SELECT b.*, t.first_name, t.last_name, r.room_number
     FROM utility_bills b
     JOIN rental_contracts c ON c.contract_id = b.contract_id AND c.owner_id = b.owner_id
     JOIN tenants t ON t.tenant_id = c.tenant_id AND t.owner_id = c.owner_id
     JOIN rooms r ON r.room_id = c.room_id AND r.owner_id = c.owner_id
     WHERE b.owner_id = :owner_id ORDER BY b.billing_month DESC, b.bill_id DESC'
);
$stmt->execute([':owner_id' => $ownerId]);
$bills = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Utility bills · StayWise</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261022-no-sidebar-icons">
    <link rel="stylesheet" href="assets/css/theme.css?v=20261022-home-nav-theme">
    <script src="https://code.iconify.design/iconify-icon/2.3.0/iconify-icon.min.js" defer></script>
    <script src="assets/js/theme.js?v=20261022-iconify"></script>
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>
    <main class="home-content">
        <header class="topbar"><p class="topbar-label">StayWise <span aria-hidden="true">/</span> Utility bills</p><div class="topbar-actions"><span class="topbar-user"><?= htmlspecialchars($_SESSION['staywise_user']['name'], ENT_QUOTES, 'UTF-8') ?></span><button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to light mode"><iconify-icon class="theme-toggle-icon" icon="lucide:sun" aria-hidden="true"></iconify-icon></button></div></header>
        <div class="page-head"><div><p class="eyebrow">Financial management</p><h1>Utility bills</h1><p>Track electricity and water charges for each rental.</p></div></div>
        <?php if ($message !== ''): ?><div class="alert" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="panel">
            <div class="panel-head"><div><h2>Record utility bill</h2><p>One bill per contract and billing month.</p></div></div>
            <?php if (!$contracts): ?><p class="alert">Create a rental contract before recording utility bills.</p><?php endif; ?>
            <form method="POST" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="create">
                <label>Tenant and room<select name="contract_id" required><option value="">Choose a contract</option><?php foreach ($contracts as $contract): ?><option value="<?= (int) $contract['contract_id'] ?>"><?= htmlspecialchars($contract['first_name'] . ' ' . $contract['last_name'] . ' · Room ' . $contract['room_number'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
                <label>Billing month<input type="date" name="billing_month" value="<?= date('Y-m-01') ?>" required></label>
                <label>Electricity bill<input type="number" name="electricity_bill" min="0" step="0.01" value="0" required></label>
                <label>Water bill<input type="number" name="water_bill" min="0" step="0.01" value="0" required></label>
                <label>Due date (optional)<input type="date" name="due_date"></label>
                <button type="submit" <?= !$contracts ? 'disabled' : '' ?>>Save bill</button>
            </form>
        </section>
        <section class="panel history-panel">
            <div class="panel-head"><div><h2>Bill history</h2><p><?= count($bills) ?> utility bills.</p></div></div>
            <table><thead><tr><th>Tenant</th><th>Room</th><th>Billing month</th><th>Electricity</th><th>Water</th><th>Total</th><th>Due date</th><th>Status</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($bills as $bill): ?>
                    <tr>
                        <td><?= htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($bill['room_number'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($bill['billing_month'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>₱<?= number_format((float) $bill['electricity_bill'], 2) ?></td><td>₱<?= number_format((float) $bill['water_bill'], 2) ?></td><td>₱<?= number_format((float) $bill['total_amount'], 2) ?></td>
                        <td><?= htmlspecialchars($bill['due_date'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(ucfirst($bill['bill_status']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?php if ($bill['bill_status'] === 'unpaid'): ?><form method="POST" class="table-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="bill_id" value="<?= (int) $bill['bill_id'] ?>"><button type="submit">Mark paid</button></form><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$bills): ?><tr><td colspan="9" class="empty-state">No utility bills recorded yet.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
    </main>
</body>
</html>
