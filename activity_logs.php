<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
$activePage = 'activity';
$stmt = $pdo->prepare(
    'SELECT l.activity, l.created_at, u.full_name, u.email
     FROM activity_logs l JOIN app_users u ON u.user_id = l.user_id
     WHERE l.owner_id = :owner_id ORDER BY l.created_at DESC LIMIT 200'
);
$stmt->execute([':owner_id' => $_SESSION['staywise_user']['owner_id']]);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity logs · StayWise</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261015">
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>
    <main class="home-content">
        <header class="topbar"><p class="topbar-label">StayWise <span aria-hidden="true">/</span> Activity logs</p><span class="topbar-user"><?= htmlspecialchars($_SESSION['staywise_user']['name'], ENT_QUOTES, 'UTF-8') ?></span></header>
        <div class="page-head"><div><p class="eyebrow">Audit trail</p><h1>Activity logs</h1><p>Recent actions recorded for your boarding house.</p></div></div>
        <section class="panel">
            <div class="panel-head"><div><h2>Recent activity</h2><p>Latest 200 actions.</p></div></div>
            <table><thead><tr><th>Date and time</th><th>User</th><th>Email</th><th>Activity</th></tr></thead><tbody>
                <?php foreach ($logs as $log): ?><tr><td><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['full_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['email'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['activity'], ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?>
                <?php if (!$logs): ?><tr><td colspan="4" class="empty-state">No activity has been recorded yet.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
    </main>
</body>
</html>
