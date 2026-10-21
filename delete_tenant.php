<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
require_once __DIR__ . '/classes/Tenant.php';

$tenant = new Tenant();

$id = (int) ($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_staywise_csrf()) {
    http_response_code(403);
    exit('Invalid delete request.');
}

if ($id > 0 && $tenant->deleteTenant($id, (int) $_SESSION['staywise_user']['owner_id'])) {
    log_staywise_activity('Deleted tenant #' . $id);
} elseif ($id > 0) {
    $_SESSION['flash_error'] = 'This tenant could not be deleted while contract or payment history is attached.';
}

header('Location: view_tenants.php');
exit;
