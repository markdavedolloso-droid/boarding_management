<?php
$tenantModalReturn = $tenantModalReturn ?? 'view_tenants.php';
$tenantModalValues = $_SESSION['tenant_form_values'] ?? [];
$tenantModalError = (string) ($_SESSION['tenant_form_error'] ?? '');
unset($_SESSION['tenant_form_values'], $_SESSION['tenant_form_error']);
?>
<dialog class="tenant-modal" id="tenant-modal" aria-labelledby="tenant-modal-title">
    <div class="tenant-modal-head">
        <div>
            <p class="eyebrow">Tenant directory</p>
            <h2 id="tenant-modal-title">Add new tenant</h2>
            <p>Create a tenant profile and optionally link their pending account.</p>
        </div>
        <button class="tenant-modal-close" type="button" aria-label="Close add tenant dialog" data-close-tenant-modal>&times;</button>
    </div>
    <?php if ($tenantModalError !== ''): ?><div class="alert alert-error" role="alert"><?= htmlspecialchars($tenantModalError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="POST" action="add_tenant.php" class="tenant-modal-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($tenantModalReturn, ENT_QUOTES, 'UTF-8') ?>">
        <label for="modal_first_name">First name</label>
        <input id="modal_first_name" type="text" name="first_name" maxlength="50" value="<?= htmlspecialchars($tenantModalValues['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

        <label for="modal_last_name">Last name</label>
        <input id="modal_last_name" type="text" name="last_name" maxlength="50" value="<?= htmlspecialchars($tenantModalValues['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

        <label for="modal_gender">Gender</label>
        <select id="modal_gender" name="gender" required>
            <?php foreach (['Male', 'Female', 'Other'] as $gender): ?>
                <option value="<?= $gender ?>" <?= ($tenantModalValues['gender'] ?? 'Male') === $gender ? 'selected' : '' ?>><?= $gender ?></option>
            <?php endforeach; ?>
        </select>

        <label for="modal_contact_number">Contact number</label>
        <input id="modal_contact_number" type="text" name="contact_number" maxlength="20" value="<?= htmlspecialchars($tenantModalValues['contact_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

        <label for="modal_address">Address</label>
        <input id="modal_address" type="text" name="address" maxlength="150" value="<?= htmlspecialchars($tenantModalValues['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

        <label for="modal_account_email">Tenant account email (optional)</label>
        <input id="modal_account_email" type="email" name="account_email" maxlength="190" placeholder="Link a pending tenant account" value="<?= htmlspecialchars($tenantModalValues['account_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <div class="tenant-modal-actions">
            <button class="button secondary" type="button" data-close-tenant-modal>Cancel</button>
            <button type="submit">Add tenant</button>
        </div>
    </form>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('tenant-modal');
        if (!dialog) return;

        document.querySelectorAll('[data-open-tenant-modal]').forEach((button) => {
            button.addEventListener('click', () => dialog.showModal());
        });
        dialog.querySelectorAll('[data-close-tenant-modal]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
        if (new URLSearchParams(window.location.search).has('add') || dialog.querySelector('[role="alert"]')) {
            dialog.showModal();
        }
    })();
</script>
