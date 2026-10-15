<?php
$activePage = $activePage ?? '';
$currentUser = $_SESSION['staywise_user'] ?? [];
?>
<nav aria-label="Main navigation">
    <a href="index.php" class="<?= $activePage === 'overview' ? 'active' : '' ?>" <?= $activePage === 'overview' ? 'aria-current="page"' : '' ?>>Overview</a>
    <?php if (($currentUser['role'] ?? '') === 'owner'): ?>
        <a href="view_tenants.php" class="<?= $activePage === 'tenants' ? 'active' : '' ?>" <?= $activePage === 'tenants' ? 'aria-current="page"' : '' ?>>Tenants</a>
        <a href="rooms.php" class="<?= $activePage === 'rooms' ? 'active' : '' ?>" <?= $activePage === 'rooms' ? 'aria-current="page"' : '' ?>>Rooms</a>
        <a href="contracts.php" class="<?= $activePage === 'contracts' ? 'active' : '' ?>" <?= $activePage === 'contracts' ? 'aria-current="page"' : '' ?>>Contracts</a>
        <a href="payments.php" class="<?= $activePage === 'payments' ? 'active' : '' ?>" <?= $activePage === 'payments' ? 'aria-current="page"' : '' ?>>Payments</a>
        <a href="utility_bills.php" class="<?= $activePage === 'utilities' ? 'active' : '' ?>" <?= $activePage === 'utilities' ? 'aria-current="page"' : '' ?>>Utility bills</a>
        <a href="notifications.php" class="<?= $activePage === 'notifications' ? 'active' : '' ?>" <?= $activePage === 'notifications' ? 'aria-current="page"' : '' ?>>Notifications</a>
        <a href="reports.php" class="<?= $activePage === 'reports' ? 'active' : '' ?>" <?= $activePage === 'reports' ? 'aria-current="page"' : '' ?>>Reports</a>
        <a href="activity_logs.php" class="<?= $activePage === 'activity' ? 'active' : '' ?>" <?= $activePage === 'activity' ? 'aria-current="page"' : '' ?>>Activity logs</a>
        <a href="backup.php" class="<?= $activePage === 'backup' ? 'active' : '' ?>" <?= $activePage === 'backup' ? 'aria-current="page"' : '' ?>>Backup &amp; restore</a>
    <?php else: ?>
        <a href="notifications.php" class="<?= $activePage === 'notifications' ? 'active' : '' ?>" <?= $activePage === 'notifications' ? 'aria-current="page"' : '' ?>>Notifications</a>
    <?php endif; ?>
    <div class="sidebar-account">
        <span class="sidebar-user"><?= htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></span>
        <form class="sidebar-form" method="POST" action="logout.php" onsubmit="return confirm('Are you sure you want to log out?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <button class="sidebar-logout" type="submit">Log out</button>
        </form>
    </div>
</nav>
