<?php
$activePage = $activePage ?? '';
$currentUser = $_SESSION['staywise_user'] ?? [];
?>
<button class="nav-toggle" type="button" aria-controls="main-navigation" aria-expanded="false" aria-label="Open navigation menu">
    <span></span>
    <span></span>
    <span></span>
</button>
<button class="nav-backdrop" type="button" aria-label="Close navigation menu" hidden></button>
<nav id="main-navigation" aria-label="Main navigation">
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
<script>
(() => {
    const navigation = document.getElementById('main-navigation');
    const toggle = document.querySelector('.nav-toggle');
    const backdrop = document.querySelector('.nav-backdrop');

    if (!navigation || !toggle || !backdrop) {
        return;
    }

    const closeMenu = () => {
        navigation.removeAttribute('data-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation menu');
        backdrop.hidden = true;
    };

    toggle.addEventListener('click', () => {
        const isOpen = navigation.hasAttribute('data-open');
        if (isOpen) {
            closeMenu();
            return;
        }

        navigation.setAttribute('data-open', 'true');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close navigation menu');
        backdrop.hidden = false;
        navigation.querySelector('a')?.focus();
    });

    backdrop.addEventListener('click', closeMenu);
    navigation.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            closeMenu();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && navigation.hasAttribute('data-open')) {
            closeMenu();
            toggle.focus();
        }
    });
})();
</script>
