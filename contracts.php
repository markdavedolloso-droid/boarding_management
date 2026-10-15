<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
$activePage = 'contracts';
$ownerId = (int) $_SESSION['staywise_user']['owner_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_staywise_csrf()) {
        $message = 'Your session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'end') {
        $contractId = (int) ($_POST['contract_id'] ?? 0);
        $stmt = $pdo->prepare(
            "UPDATE rental_contracts
             SET contract_status = CASE WHEN start_date > CURDATE() THEN 'cancelled' ELSE 'ended' END,
                    end_date = CASE
                        WHEN start_date > CURDATE() THEN end_date
                        WHEN end_date IS NULL OR end_date > CURDATE() THEN CURDATE()
                        ELSE end_date
                    END
             WHERE contract_id = :contract_id AND owner_id = :owner_id AND contract_status = 'active'"
        );
        $stmt->execute([':contract_id' => $contractId, ':owner_id' => $ownerId]);
        if ($stmt->rowCount() > 0) {
            log_staywise_activity('Ended contract #' . $contractId);
            $message = 'Contract ended.';
        } else {
            $message = 'The active contract could not be found.';
        }
    } elseif (($_POST['action'] ?? '') === 'create') {
        $tenantId = filter_var($_POST['tenant_id'] ?? null, FILTER_VALIDATE_INT);
        $roomId = filter_var($_POST['room_id'] ?? null, FILTER_VALIDATE_INT);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = ($_POST['end_date'] ?? '') !== '' ? $_POST['end_date'] : null;
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = $endDate === null ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);

        if ($tenantId === false || $tenantId < 1 || $roomId === false || $roomId < 1
            || !$start || $start->format('Y-m-d') !== $startDate
            || ($endDate !== null && (!$end || $end->format('Y-m-d') !== $endDate || $end < $start))) {
            $message = 'Choose a tenant, room, and valid contract dates.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'SELECT room_id, monthly_rent, capacity, status FROM rooms
                     WHERE room_id = :room_id AND owner_id = :owner_id FOR UPDATE'
                );
                $stmt->execute([':room_id' => $roomId, ':owner_id' => $ownerId]);
                $room = $stmt->fetch();
                $stmt = $pdo->prepare('SELECT tenant_id FROM tenants WHERE tenant_id = :tenant_id AND owner_id = :owner_id FOR UPDATE');
                $stmt->execute([':tenant_id' => $tenantId, ':owner_id' => $ownerId]);
                $tenantExists = $stmt->fetchColumn() !== false;

                if (!$room || !$tenantExists || $room['status'] !== 'available') {
                    throw new DomainException('Choose a room and tenant belonging to this property. The room must be available.');
                }

                $overlapEnd = $endDate ?? '9999-12-31';
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM rental_contracts
                     WHERE owner_id = :owner_id AND room_id = :room_id
                       AND contract_status = 'active'
                       AND (end_date IS NULL OR end_date >= :start_date)
                       AND start_date <= :end_date"
                );
                $stmt->execute([
                    ':owner_id' => $ownerId,
                    ':room_id' => $roomId,
                    ':start_date' => $startDate,
                    ':end_date' => $overlapEnd,
                ]);
                if ((int) $stmt->fetchColumn() >= (int) $room['capacity']) {
                    throw new DomainException('This room has no available spaces during the selected contract dates.');
                }

                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM rental_contracts
                     WHERE owner_id = :owner_id AND tenant_id = :tenant_id
                       AND contract_status = 'active'
                       AND (end_date IS NULL OR end_date >= :start_date)
                       AND start_date <= :end_date"
                );
                $stmt->execute([
                    ':owner_id' => $ownerId,
                    ':tenant_id' => $tenantId,
                    ':start_date' => $startDate,
                    ':end_date' => $overlapEnd,
                ]);
                if ((int) $stmt->fetchColumn() > 0) {
                    throw new DomainException('This tenant already has an overlapping active contract.');
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO rental_contracts
                        (owner_id, tenant_id, room_id, monthly_rent, start_date, end_date, contract_status)
                     VALUES (:owner_id, :tenant_id, :room_id, :monthly_rent, :start_date, :end_date, 'active')"
                );
                $stmt->execute([
                    ':owner_id' => $ownerId,
                    ':tenant_id' => $tenantId,
                    ':room_id' => $roomId,
                    ':monthly_rent' => $room['monthly_rent'],
                    ':start_date' => $startDate,
                    ':end_date' => $endDate,
                ]);
                $pdo->commit();
                log_staywise_activity('Created rental contract #' . $pdo->lastInsertId());
                $message = 'Rental contract created.';
            } catch (DomainException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = $e->getMessage();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT tenant_id, first_name, last_name FROM tenants WHERE owner_id = :owner_id ORDER BY last_name, first_name');
$stmt->execute([':owner_id' => $ownerId]);
$tenants = $stmt->fetchAll();
$stmt = $pdo->prepare(
    "SELECT r.room_id, r.room_number, r.floor, r.capacity, r.monthly_rent, r.status,
            (SELECT COUNT(*) FROM rental_contracts c
             WHERE c.room_id = r.room_id AND c.owner_id = r.owner_id
               AND c.contract_status = 'active' AND c.start_date <= CURDATE()
               AND (c.end_date IS NULL OR c.end_date >= CURDATE())) AS occupants
     FROM rooms r WHERE r.owner_id = :owner_id AND r.status = 'available'
     ORDER BY r.floor, r.room_number"
);
$stmt->execute([':owner_id' => $ownerId]);
$rooms = array_values(array_filter($stmt->fetchAll(), static fn(array $room): bool => (int) $room['occupants'] < (int) $room['capacity']));
$stmt = $pdo->prepare(
    'SELECT c.*, t.first_name, t.last_name, r.room_number
     FROM rental_contracts c
     JOIN tenants t ON t.tenant_id = c.tenant_id AND t.owner_id = c.owner_id
     JOIN rooms r ON r.room_id = c.room_id AND r.owner_id = c.owner_id
     WHERE c.owner_id = :owner_id ORDER BY c.contract_id DESC'
);
$stmt->execute([':owner_id' => $ownerId]);
$contracts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rental contracts · StayWise</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261015">
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>
    <main class="home-content">
        <header class="topbar"><p class="topbar-label">StayWise <span aria-hidden="true">/</span> Contracts</p><span class="topbar-user"><?= htmlspecialchars($_SESSION['staywise_user']['name'], ENT_QUOTES, 'UTF-8') ?></span></header>
        <div class="page-head"><div><p class="eyebrow">Leases</p><h1>Rental contracts</h1><p>Create agreements and prevent overlapping room assignments.</p></div></div>
        <?php if ($message !== ''): ?><div class="alert" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="panel">
            <div class="panel-head"><div><h2>Create contract</h2><p>Rent is recorded at the current room rate.</p></div></div>
            <?php if (!$tenants || !$rooms): ?><p class="alert">Add at least one tenant and one room with available capacity before creating a contract.</p><?php endif; ?>
            <form method="POST" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="create">
                <label>Tenant<select name="tenant_id" required><option value="">Choose a tenant</option><?php foreach ($tenants as $tenant): ?><option value="<?= (int) $tenant['tenant_id'] ?>"><?= htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
                <label>Available room<select name="room_id" required><option value="">Choose a room</option><?php foreach ($rooms as $room): ?><option value="<?= (int) $room['room_id'] ?>"><?= htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8') ?> · <?= max(0, (int) $room['capacity'] - (int) $room['occupants']) ?> space(s) available · ₱<?= number_format((float) $room['monthly_rent'], 2) ?></option><?php endforeach; ?></select></label>
                <label>Start date<input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required></label>
                <label>End date (optional)<input type="date" name="end_date"></label>
                <button type="submit" <?= (!$tenants || !$rooms) ? 'disabled' : '' ?>>Create contract</button>
            </form>
        </section>
        <section class="panel">
            <div class="panel-head"><div><h2>Contract records</h2><p><?= count($contracts) ?> agreements.</p></div></div>
            <table><thead><tr><th>Tenant</th><th>Room</th><th>Monthly rent</th><th>Start</th><th>End</th><th>Status</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($contracts as $contract): ?>
                    <tr>
                        <td><?= htmlspecialchars($contract['first_name'] . ' ' . $contract['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($contract['room_number'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>₱<?= number_format((float) $contract['monthly_rent'], 2) ?></td>
                        <td><?= htmlspecialchars($contract['start_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($contract['end_date'] ?: 'Open-ended', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(ucfirst($contract['contract_status']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?php if ($contract['contract_status'] === 'active'): ?><form method="POST" class="table-form" onsubmit="return confirm('End this rental contract?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="end"><input type="hidden" name="contract_id" value="<?= (int) $contract['contract_id'] ?>"><button type="submit">End</button></form><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$contracts): ?><tr><td colspan="7" class="empty-state">No rental contracts yet.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
    </main>
</body>
</html>
