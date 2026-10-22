<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
$activePage = 'activity';
$ownerId = (int) $_SESSION['staywise_user']['owner_id'];
$message = (string) ($_SESSION['activity_log_flash_success'] ?? '');
unset($_SESSION['activity_log_flash_success']);
$error = '';

archive_staywise_old_activity_logs($ownerId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_staywise_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'clear_archive') {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('DELETE FROM activity_logs_archive WHERE owner_id = :owner_id');
            $stmt->execute([':owner_id' => $ownerId]);
            $deletedCount = $stmt->rowCount();
            $pdo->commit();
            log_staywise_activity('Deleted all archived audit logs (' . $deletedCount . ' records)');
            $_SESSION['activity_log_flash_success'] = 'Archived audit logs deleted.';
            header('Location: activity_logs.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

$keyword = trim($_GET['q'] ?? '');
$pattern = '%' . $keyword . '%';

$sql = 'SELECT l.log_id, l.activity, l.created_at, u.full_name, u.email
        FROM activity_logs l JOIN app_users u ON u.user_id = l.user_id
        WHERE l.owner_id = :owner_id';
$params = [':owner_id' => $ownerId];
if ($keyword !== '') {
    $sql .= ' AND (l.activity LIKE :activity OR u.full_name LIKE :name OR u.email LIKE :email)';
    $params[':activity'] = $pattern;
    $params[':name'] = $pattern;
    $params[':email'] = $pattern;
}
$sql .= ' ORDER BY l.log_id DESC LIMIT 15';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$archiveSql = 'SELECT original_log_id, activity, created_at, actor_name AS full_name, actor_email AS email
               FROM activity_logs_archive WHERE owner_id = :owner_id';
$archiveParams = [':owner_id' => $ownerId];
if ($keyword !== '') {
    $archiveSql .= ' AND (activity LIKE :activity OR actor_name LIKE :name OR actor_email LIKE :email)';
    $archiveParams[':activity'] = $pattern;
    $archiveParams[':name'] = $pattern;
    $archiveParams[':email'] = $pattern;
}
$archiveSql .= ' ORDER BY created_at DESC, archive_id DESC LIMIT 200';
$stmt = $pdo->prepare($archiveSql);
$stmt->execute($archiveParams);
$archivedLogs = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM activity_logs_archive WHERE owner_id = :owner_id');
$stmt->execute([':owner_id' => $ownerId]);
$archiveCount = (int) $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity logs · StayWise</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261022-no-sidebar-icons">
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>
    <main class="home-content">
        <header class="topbar"><p class="topbar-label">StayWise <span aria-hidden="true">/</span> Activity logs</p><span class="topbar-user"><?= htmlspecialchars($_SESSION['staywise_user']['name'], ENT_QUOTES, 'UTF-8') ?></span></header>
        <div class="page-head"><div><p class="eyebrow">Audit trail</p><h1>Activity logs</h1><p>The latest 15 actions stay here; older actions are archived automatically.</p></div></div>
        <?php if ($message !== ''): ?><div class="alert" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        
            <form class="inline-form" method="GET" action="activity_logs.php" role="search">
                <input type="search" name="q" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search users, emails, or activities..." aria-label="Search audit logs">
                <button type="submit">Search logs</button>
                <?php if ($keyword !== ''): ?><a class="button secondary" href="activity_logs.php">Clear search</a><?php endif; ?>
            </form>
        

        <section class="panel">
            <div class="panel-head"><div><h2>Recent activity</h2><p>Up to 15 latest actions<?= $keyword !== '' ? ' matching your search' : '' ?>.</p></div></div>
            <table><thead><tr><th>Date and time</th><th>User</th><th>Email</th><th>Activity</th></tr></thead><tbody>
                <?php foreach ($logs as $log): ?><tr><td><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['full_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['email'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['activity'], ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?>
                <?php if (!$logs): ?><tr><td colspan="4" class="empty-state">No matching recent activity.</td></tr><?php endif; ?>
            </tbody></table>
        </section>

        <section class="panel audit-archive">
            <div class="panel-head">
                <div><h2>Audit archive</h2><p><?= number_format($archiveCount) ?> archived actions total; showing up to 200<?= $keyword !== '' ? ' matching your search' : '' ?>.</p></div>
                <form method="POST" class="table-form" onsubmit="return confirm('Permanently delete all archived audit logs for this property? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="clear_archive">
                    <button type="submit" class="danger-button" <?= $archiveCount === 0 ? 'disabled' : '' ?>>Delete all archive</button>
                </form>
            </div>
            <table><thead><tr><th>Date and time</th><th>User</th><th>Email</th><th>Activity</th></tr></thead><tbody>
                <?php foreach ($archivedLogs as $log): ?><tr><td><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['full_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['email'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['activity'], ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?>
                <?php if (!$archivedLogs): ?><tr><td colspan="4" class="empty-state">No matching archived audit logs.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
    </main>
</body>
</html>
