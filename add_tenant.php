<?php
require_once __DIR__ . '/backend/auth_init.php';
require_staywise_owner();
require_once __DIR__ . '/classes/Tenant.php';

$returnTo = ($_POST['return_to'] ?? $_GET['return_to'] ?? '') === 'index.php' ? 'index.php' : 'view_tenants.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $returnTo . '?add=1');
    exit;
}

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
} elseif ($data['first_name'] === '' || strlen($data['first_name']) > 50
    || $data['last_name'] === '' || strlen($data['last_name']) > 50
    || $data['contact_number'] === '' || strlen($data['contact_number']) > 20
    || $data['address'] === '' || strlen($data['address']) > 150
    || ($data['account_email'] !== '' && !filter_var($data['account_email'], FILTER_VALIDATE_EMAIL))) {
    $error = 'Enter valid tenant details. Names, contact, or address may exceed their allowed length.';
} elseif (!in_array($data['gender'], ['Male', 'Female', 'Other'], true)) {
    $error = 'Select a valid gender.';
} else {
    $tenant = new Tenant($pdo);
    if ($tenant->addTenant((int) $_SESSION['staywise_user']['owner_id'], $data)) {
        log_staywise_activity('Added tenant ' . $data['first_name'] . ' ' . $data['last_name']);
        $_SESSION['tenant_flash_success'] = 'Tenant added successfully.';
        unset($_SESSION['tenant_form_values'], $_SESSION['tenant_form_error']);
        header('Location: ' . $returnTo);
        exit;
    }
    $error = 'Could not add tenant. Check that the account email belongs to an unlinked tenant account.';
}

$_SESSION['tenant_form_values'] = $data;
$_SESSION['tenant_form_error'] = $error;
header('Location: ' . $returnTo . '?add=1');
exit;
