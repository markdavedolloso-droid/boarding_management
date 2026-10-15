<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
$activePage = 'tenants';
require_once __DIR__ . '/classes/Tenant.php';

$keyword = trim($_GET['keyword'] ?? '');
$tenant = new Tenant($pdo);
$tenants = $keyword !== ''
    ? $tenant->searchTenants($keyword, (int) $_SESSION['staywise_user']['owner_id'])
    : $tenant->getTenants((int) $_SESSION['staywise_user']['owner_id']);
$message = (string) ($_SESSION['tenant_flash_success'] ?? $_SESSION['flash_error'] ?? '');
unset($_SESSION['tenant_flash_success'], $_SESSION['flash_error']);
$tenantModalReturn = 'view_tenants.php';
$editTenantId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$editTenant = $editTenantId !== false && $editTenantId > 0
    ? $tenant->getTenantById($editTenantId, (int) $_SESSION['staywise_user']['owner_id'])
    : false;
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>All Tenants</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261015">
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>

    <main class="home-content">
    <header class="topbar"><p class="topbar-label">StayWise <span aria-hidden="true">/</span> Tenants</p><span class="topbar-user"><?= htmlspecialchars($_SESSION['staywise_user']['name'], ENT_QUOTES, 'UTF-8') ?></span></header>
    <div class="page-head"><div><p class="eyebrow">Tenant directory</p><h1>All tenants</h1><p>Only records belonging to your boarding house are shown.</p></div><button class="button" type="button" data-open-tenant-modal>＋ Add tenant</button></div>
    <?php if ($message !== ''): ?><div class="alert" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <section class="panel">
        <form class="inline-form" method="GET" action="view_tenants.php" role="search">
            <input id="tenant-keyword" type="search" name="keyword" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search tenants by name, contact, or address" aria-label="Search tenants by name, contact, or address">
            <button type="submit">Search</button>
            <?php if ($keyword !== ''): ?><a class="button secondary" href="view_tenants.php">Clear</a><?php endif; ?>
        </form>
    </section>
    <?php if ($keyword !== ''): ?><p class="muted">Showing <?= count($tenants) ?> result(s) for “<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>”.</p><?php endif; ?>

    <table>
        <thead><tr>
            <th>ID</th>
            <th>Name</th>
            <th>Gender</th>
            <th>Contact</th>
            <th>Address</th>
            <th>Tenant login</th>
            <th>Date Registered</th>
            <th>Actions</th>
        </tr></thead><tbody>
        <?php foreach ($tenants as $row): ?>
            <tr>
                <td><?= (int) $row['tenant_id'] ?></td>
                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['contact_number'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['account_email'] ?: 'Not linked', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['date_registered'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <button type="button" class="text-button tenant-edit-trigger"
                        data-open-edit-tenant
                        data-tenant-id="<?= (int) $row['tenant_id'] ?>"
                        data-first-name="<?= htmlspecialchars($row['first_name'], ENT_QUOTES, 'UTF-8') ?>"
                        data-last-name="<?= htmlspecialchars($row['last_name'], ENT_QUOTES, 'UTF-8') ?>"
                        data-gender="<?= htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') ?>"
                        data-contact-number="<?= htmlspecialchars($row['contact_number'], ENT_QUOTES, 'UTF-8') ?>"
                        data-address="<?= htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8') ?>"
                        data-account-email="<?= htmlspecialchars($row['account_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">Edit</button> |
                    <form method="POST" action="delete_tenant.php" class="table-form" onsubmit="return confirm('Delete this tenant? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= (int) $row['tenant_id'] ?>">
                        <button type="submit" class="text-button">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($tenants)): ?>
            <tr><td colspan="8" class="empty-state">No tenants found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
        <?php require __DIR__ . '/modals/tenant_modal.php'; ?>
        <?php require __DIR__ . '/modals/tenant_edit_modal.php'; ?>
        </main>
</body>
</html>
