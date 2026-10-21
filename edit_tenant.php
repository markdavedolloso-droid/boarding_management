<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
require_once __DIR__ . '/classes/Tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    header('Location: view_tenants.php' . ($id !== false && $id > 0 ? '?edit=' . $id : ''));
    exit;
}

$id = filter_var($_POST['tenant_id'] ?? null, FILTER_VALIDATE_INT);
$id = $id !== false && $id > 0 ? $id : 0;
$ownerId = (int) $_SESSION['staywise_user']['owner_id'];
$data = [
    'first_name' => trim($_POST['first_name'] ?? ''),
    'last_name' => trim($_POST['last_name'] ?? ''),
    'gender' => $_POST['gender'] ?? '',
    'contact_number' => trim($_POST['contact_number'] ?? ''),
    'address' => trim($_POST['address'] ?? ''),
    'account_email' => trim($_POST['account_email'] ?? ''),
];
$error = '';

if (!verify_staywise_csrf()) {
    $error = 'Your session expired. Please try again.';
} elseif ($id < 1 || $data['first_name'] === '' || strlen($data['first_name']) > 50
    || $data['last_name'] === '' || strlen($data['last_name']) > 50
    || $data['contact_number'] === '' || strlen($data['contact_number']) > 20
    || $data['address'] === '' || strlen($data['address']) > 150
    || ($data['account_email'] !== '' && !filter_var($data['account_email'], FILTER_VALIDATE_EMAIL))) {
    $error = 'Enter valid tenant details. Names, contact, or address may exceed their allowed length.';
} elseif (!in_array($data['gender'], ['Male', 'Female', 'Other'], true)) {
    $error = 'Select a valid gender.';
} else {
    $tenant = new Tenant();
    if ($tenant->updateTenant($id, $ownerId, $data)) {
        log_staywise_activity('Updated tenant #' . $id);
        $_SESSION['tenant_flash_success'] = 'Tenant updated successfully.';
        unset($_SESSION['tenant_edit_values'], $_SESSION['tenant_edit_error'], $_SESSION['tenant_edit_id']);
        header('Location: view_tenants.php');
        exit;
    }
    $error = 'Update failed. Verify the tenant account email belongs to an unlinked tenant account.';
}

$_SESSION['tenant_edit_values'] = $data;
$_SESSION['tenant_edit_error'] = $error;
$_SESSION['tenant_edit_id'] = $id;
header('Location: view_tenants.php?edit=' . $id);
exit;
