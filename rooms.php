<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
$activePage = 'rooms';
$ownerId = (int) $_SESSION['staywise_user']['owner_id'];
$message = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_staywise_csrf()) {
        $message = 'Your session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'add') {
        $roomNumber = trim($_POST['room_number'] ?? '');
        $floor = filter_var($_POST['floor'] ?? null, FILTER_VALIDATE_INT);
        $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT);
        $rent = filter_var($_POST['monthly_rent'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($roomNumber === '' || strlen($roomNumber) > 60 || $floor === false || $floor < 0
            || $capacity === false || $capacity < 1 || $rent === false || $rent < 0) {
            $message = 'Enter a room number, floor, capacity of at least one, and a valid non-negative rent.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO rooms (owner_id, room_number, floor, capacity, monthly_rent)
                     VALUES (:owner_id, :room_number, :floor, :capacity, :monthly_rent)'
                );
                $stmt->execute([
                    ':owner_id' => $ownerId,
                    ':room_number' => $roomNumber,
                    ':floor' => $floor,
                    ':capacity' => $capacity,
                    ':monthly_rent' => $rent,
                ]);
                log_staywise_activity('Added room ' . $roomNumber);
                $message = 'Room added successfully.';
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $message = 'A room with that number already exists.';
                } else {
                    throw $e;
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'status') {
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($roomId < 1 || !in_array($status, ['available', 'maintenance'], true)) {
            $message = 'Select a valid room status.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE rooms SET status = :status WHERE room_id = :room_id AND owner_id = :owner_id'
            );
            $stmt->execute([':status' => $status, ':room_id' => $roomId, ':owner_id' => $ownerId]);
            if ($stmt->rowCount() === 1) {
                log_staywise_activity('Changed room #' . $roomId . ' status to ' . $status);
                $message = 'Room status updated.';
            }
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT r.*, COUNT(c.contract_id) AS occupant_count
     FROM rooms r
     LEFT JOIN rental_contracts c
       ON c.room_id = r.room_id AND c.owner_id = r.owner_id
      AND c.contract_status = 'active' AND c.start_date <= CURDATE()
      AND (c.end_date IS NULL OR c.end_date >= CURDATE())
     WHERE r.owner_id = :owner_id
     GROUP BY r.room_id
     ORDER BY r.floor, r.room_number"
);
$stmt->execute([':owner_id' => $ownerId]);
$rooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rooms · StayWise</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261022-no-sidebar-icons">
    <link rel="stylesheet" href="assets/css/theme.css?v=20261022-home-nav-theme">
    <script src="https://code.iconify.design/iconify-icon/2.3.0/iconify-icon.min.js" defer></script>
    <script src="assets/js/theme.js?v=20261022-iconify"></script>
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>
    <main class="home-content">
        <header class="topbar"><p class="topbar-label">StayWise <span aria-hidden="true">/</span> Rooms</p><div class="topbar-actions"><span class="topbar-user"><?= htmlspecialchars($_SESSION['staywise_user']['name'], ENT_QUOTES, 'UTF-8') ?></span><button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to light mode"><iconify-icon class="theme-toggle-icon" icon="lucide:sun" aria-hidden="true"></iconify-icon></button></div></header>
        <div class="page-head"><div><p class="eyebrow">Property management</p><h1>Rooms</h1><p>Manage capacity, rent, and room availability.</p></div></div>
        <?php if ($message !== ''): ?><div class="alert" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="panel">
            <div class="panel-head"><div><h2>Add a room</h2><p>Room numbers must be unique within your property.</p></div></div>
            <form method="POST" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="add">
                <label>Room number<input name="room_number" maxlength="60" required></label>
                <label>Floor<input type="number" name="floor" min="0" value="1" required></label>
                <label>Capacity<input type="number" name="capacity" min="1" value="1" required></label>
                <label>Monthly rent<input type="number" name="monthly_rent" min="0" step="0.01" required></label>
                <button type="submit">Add room</button>
            </form>
        </section>
        <section class="panel room-directory">
            <div class="panel-head"><div><h2>Room directory</h2><p><?= count($rooms) ?> rooms registered. Occupancy and availability update automatically from rental contracts.</p></div></div>
            <table>
                <thead><tr><th>Room</th><th>Floor</th><th>Capacity</th><th>Occupancy</th><th>Available spaces</th><th>Monthly rent</th><th>Availability</th><th>Room condition</th></tr></thead>
                <tbody>
                <?php foreach ($rooms as $room): ?>
                    <?php
                    $occupied = (int) $room['occupant_count'];
                    $capacity = (int) $room['capacity'];
                    $available = max(0, $capacity - $occupied);
                    $availability = $room['status'] === 'maintenance'
                        ? 'Maintenance'
                        : ($available === 0 ? 'Full' : ($occupied > 0 ? 'Partially occupied' : 'Available'));
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= (int) $room['floor'] ?></td>
                        <td><?= $capacity ?></td>
                        <td><?= $occupied ?> / <?= $capacity ?></td>
                        <td><?= $room['status'] === 'maintenance' ? '—' : $available ?></td>
                        <td>₱<?= number_format((float) $room['monthly_rent'], 2) ?></td>
                        <td><?= htmlspecialchars($availability, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <form method="POST" class="table-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="room_id" value="<?= (int) $room['room_id'] ?>">
                                <select name="status" aria-label="Condition for room <?= htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8') ?>">
                                    <option value="available" <?= $room['status'] === 'available' ? 'selected' : '' ?>>In service</option>
                                    <option value="maintenance" <?= $room['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                </select>
                                <button type="submit">Save condition</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rooms): ?><tr><td colspan="8" class="empty-state">No rooms added yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
