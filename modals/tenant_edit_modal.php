<?php
$editModalValues = $_SESSION['tenant_edit_values'] ?? ($editTenant ?? []);
$editModalError = (string) ($_SESSION['tenant_edit_error'] ?? '');
$editModalTenantId = (int) ($_SESSION['tenant_edit_id'] ?? ($editTenant['tenant_id'] ?? 0));
$editModalMissing = isset($_GET['edit']) && !$editTenant && $editModalError === '';
unset($_SESSION['tenant_edit_values'], $_SESSION['tenant_edit_error'], $_SESSION['tenant_edit_id']);
?>
<dialog class="tenant-modal" id="tenant-edit-modal" aria-labelledby="tenant-edit-modal-title">
    <div class="tenant-modal-head">
        <div>
            <p class="eyebrow">Tenant directory</p>
            <h2 id="tenant-edit-modal-title">Edit tenant</h2>
            <p>Update tenant details or their linked account.</p>
        </div>
        <button class="tenant-modal-close" type="button" aria-label="Close edit tenant dialog" data-close-edit-tenant>&times;</button>
    </div>
    <?php if ($editModalError !== ''): ?><div class="alert alert-error" role="alert"><?= htmlspecialchars($editModalError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($editModalMissing): ?><div class="alert alert-error" role="alert">Tenant not found.</div><?php endif; ?>
    <form method="POST" action="edit_tenant.php" class="tenant-modal-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staywise_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="tenant_id" value="<?= $editModalTenantId ?>">
            <input type="hidden" name="return_to" value="view_tenants.php">
            <label for="edit_modal_first_name">First name</label>
            <input id="edit_modal_first_name" type="text" name="first_name" maxlength="50" value="<?= htmlspecialchars($editModalValues['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

            <label for="edit_modal_last_name">Last name</label>
            <input id="edit_modal_last_name" type="text" name="last_name" maxlength="50" value="<?= htmlspecialchars($editModalValues['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

            <label for="edit_modal_gender">Gender</label>
            <select id="edit_modal_gender" name="gender" required>
                <?php foreach (['Male', 'Female', 'Other'] as $gender): ?>
                    <option value="<?= $gender ?>" <?= ($editModalValues['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= $gender ?></option>
                <?php endforeach; ?>
            </select>

            <label for="edit_modal_contact_number">Contact number</label>
            <input id="edit_modal_contact_number" type="text" name="contact_number" maxlength="20" value="<?= htmlspecialchars($editModalValues['contact_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

            <label for="edit_modal_address">Address</label>
            <input id="edit_modal_address" type="text" name="address" maxlength="150" value="<?= htmlspecialchars($editModalValues['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

            <label for="edit_modal_account_email">Tenant account email (optional)</label>
            <input id="edit_modal_account_email" type="email" name="account_email" maxlength="190" placeholder="Link a pending tenant account" value="<?= htmlspecialchars($editModalValues['account_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="tenant-modal-actions">
                <button class="button secondary" type="button" data-close-edit-tenant>Cancel</button>
                <button type="submit">Save changes</button>
            </div>
    </form>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('tenant-edit-modal');
        if (!dialog) return;

        document.querySelectorAll('[data-open-edit-tenant]').forEach((button) => {
            button.addEventListener('click', () => {
                const form = dialog.querySelector('form');
                if (form) {
                    form.elements.tenant_id.value = button.dataset.tenantId;
                    form.elements.first_name.value = button.dataset.firstName;
                    form.elements.last_name.value = button.dataset.lastName;
                    form.elements.gender.value = button.dataset.gender;
                    form.elements.contact_number.value = button.dataset.contactNumber;
                    form.elements.address.value = button.dataset.address;
                    form.elements.account_email.value = button.dataset.accountEmail;
                }
                dialog.showModal();
            });
        });
        dialog.querySelectorAll('[data-close-edit-tenant]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
        if (new URLSearchParams(window.location.search).has('edit') || dialog.querySelector('[role="alert"]')) {
            dialog.showModal();
        }
    })();
</script>
