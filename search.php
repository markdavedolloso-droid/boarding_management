<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
$activePage = 'search';
require_once __DIR__ . '/classes/Tenant.php';

$tenant = new Tenant();
$results = [];
$keyword = "";

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['keyword'])) {
    $keyword = trim($_GET['keyword']);
    if ($keyword !== "") {
        $results = $tenant->searchTenants($keyword, (int) $_SESSION['staywise_user']['owner_id']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Search Tenants</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261022-no-sidebar-icons">
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>

    <main class="home-content">
    <header class="topbar"><p class="topbar-label">StayWise <span aria-hidden="true">/</span> Search</p><span class="topbar-user"><?= htmlspecialchars($_SESSION['staywise_user']['name'], ENT_QUOTES, 'UTF-8') ?></span></header>
    <div class="page-head"><div><p class="eyebrow">Tenant directory</p><h1>Search tenants</h1><p>Find records by name, contact number, or address.</p></div></div>

    <form method="GET" action="search.php">
        <label for="keyword">Search by name, contact number, or address</label>
        <input id="keyword" type="text" name="keyword" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Juan or 0917...">
        <button type="submit">Search</button>
    </form>

    <?php if ($keyword !== ""): ?>
        <h2>Results for "<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Gender</th>
                <th>Contact</th>
                <th>Address</th>
                <th>Date Registered</th>
            </tr>
            <?php foreach ($results as $row): ?>
                <tr>
                    <td><?= (int) $row['tenant_id'] ?></td>
                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['contact_number'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['date_registered'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($results)): ?>
                <tr><td colspan="6">No matching tenants found.</td></tr>
            <?php endif; ?>
        </table>
    <?php endif; ?>
    </main>
</body>
</html>
