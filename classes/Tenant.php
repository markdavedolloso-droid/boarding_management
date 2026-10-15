<?php
class Tenant
{
    private PDO $conn;
    private string $table = 'tenants';

    public function __construct(PDO $pdo)
    {
        $this->conn = $pdo;
    }

    public function addTenant(int $ownerId, array $data): bool
    {
        try {
            $this->conn->beginTransaction();
            $userId = $this->findPendingTenantUser($data['account_email'] ?? '');
            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table}
                    (owner_id, user_id, first_name, last_name, gender, contact_number, address, date_registered)
                 VALUES
                    (:owner_id, :user_id, :first_name, :last_name, :gender, :contact_number, :address, CURDATE())"
            );
            $stmt->execute([
                ':owner_id' => $ownerId,
                ':user_id' => $userId,
                ':first_name' => $data['first_name'],
                ':last_name' => $data['last_name'],
                ':gender' => $data['gender'],
                ':contact_number' => $data['contact_number'],
                ':address' => $data['address'],
            ]);
            if ($userId !== null) {
                $this->activateTenantUser($userId);
            }
            $this->conn->commit();

            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('addTenant error: ' . $e->getMessage());

            return false;
        } catch (DomainException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return false;
        }
    }

    public function getTenants(int $ownerId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT t.*, u.email AS account_email
             FROM {$this->table} t
             LEFT JOIN app_users u ON u.user_id = t.user_id
             WHERE t.owner_id = :owner_id
             ORDER BY t.tenant_id DESC"
        );
        $stmt->execute([':owner_id' => $ownerId]);

        return $stmt->fetchAll();
    }

    public function getTenantById(int $id, int $ownerId): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT t.*, u.email AS account_email
             FROM {$this->table} t
             LEFT JOIN app_users u ON u.user_id = t.user_id
             WHERE t.tenant_id = :id AND t.owner_id = :owner_id"
        );
        $stmt->execute([':id' => $id, ':owner_id' => $ownerId]);

        return $stmt->fetch();
    }

    public function updateTenant(int $id, int $ownerId, array $data): bool
    {
        try {
            $this->conn->beginTransaction();
            $record = $this->getTenantById($id, $ownerId);
            if (!$record) {
                $this->conn->rollBack();
                return false;
            }
            $userId = $this->findPendingTenantUser($data['account_email'] ?? '', $record['user_id'] ?? null);
            $stmt = $this->conn->prepare(
                "UPDATE {$this->table}
                 SET user_id = :user_id,
                     first_name = :first_name,
                     last_name = :last_name,
                     gender = :gender,
                     contact_number = :contact_number,
                     address = :address
                 WHERE tenant_id = :id AND owner_id = :owner_id"
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':first_name' => $data['first_name'],
                ':last_name' => $data['last_name'],
                ':gender' => $data['gender'],
                ':contact_number' => $data['contact_number'],
                ':address' => $data['address'],
                ':id' => $id,
                ':owner_id' => $ownerId,
            ]);
            if (!empty($record['user_id']) && (int) $record['user_id'] !== $userId) {
                $this->setTenantUserStatus((int) $record['user_id'], 'pending');
            }
            if ($userId !== null) {
                $this->activateTenantUser($userId);
            }
            $this->conn->commit();

            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('updateTenant error: ' . $e->getMessage());

            return false;
        } catch (DomainException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return false;
        }
    }

    public function deleteTenant(int $id, int $ownerId): bool
    {
        try {
            $this->conn->beginTransaction();
            $record = $this->getTenantById($id, $ownerId);
            if (!$record) {
                $this->conn->rollBack();
                return false;
            }

            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) FROM rental_contracts
                 WHERE owner_id = :owner_id AND tenant_id = :tenant_id"
            );
            $stmt->execute([':owner_id' => $ownerId, ':tenant_id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $this->conn->rollBack();
                return false;
            }

            if (!empty($record['user_id'])) {
                $this->setTenantUserStatus((int) $record['user_id'], 'pending');
            }
            $stmt = $this->conn->prepare(
                "DELETE FROM {$this->table} WHERE tenant_id = :id AND owner_id = :owner_id"
            );
            $stmt->execute([':id' => $id, ':owner_id' => $ownerId]);
            $this->conn->commit();

            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('deleteTenant error: ' . $e->getMessage());

            return false;
        }
    }

    public function searchTenants(string $keyword, int $ownerId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT t.*, u.email AS account_email
             FROM {$this->table} t
             LEFT JOIN app_users u ON u.user_id = t.user_id
             WHERE t.owner_id = :owner_id
               AND (t.first_name LIKE :kw OR t.last_name LIKE :kw
                    OR CONCAT(t.first_name, ' ', t.last_name) LIKE :kw
                    OR t.contact_number LIKE :kw OR t.address LIKE :kw)
             ORDER BY t.tenant_id DESC"
        );
        $stmt->execute([':owner_id' => $ownerId, ':kw' => "%$keyword%"]);

        return $stmt->fetchAll();
    }

    private function findPendingTenantUser(string $email, int|string|null $currentUserId = null): ?int
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return $currentUserId === null ? null : (int) $currentUserId;
        }

        $stmt = $this->conn->prepare(
            "SELECT u.user_id FROM app_users u
             WHERE u.email = :email AND u.role = 'tenant'
               AND (u.status = 'pending' OR u.user_id = :allowed_user_id)
               AND NOT EXISTS (
                    SELECT 1 FROM tenants t
                    WHERE t.user_id = u.user_id
                      AND (:check_existing_link IS NULL OR t.tenant_id <> :current_tenant_id)
               )
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([
            ':email' => $email,
            ':allowed_user_id' => $currentUserId,
            ':check_existing_link' => $currentUserId,
            ':current_tenant_id' => $this->currentTenantIdForUser($currentUserId),
        ]);
        $userId = $stmt->fetchColumn();

        if ($userId === false) {
            throw new DomainException('That email is not an unlinked tenant account.');
        }

        return (int) $userId;
    }

    private function currentTenantIdForUser(int|string|null $userId): ?int
    {
        if ($userId === null) {
            return null;
        }
        $stmt = $this->conn->prepare('SELECT tenant_id FROM tenants WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $tenantId = $stmt->fetchColumn();

        return $tenantId === false ? null : (int) $tenantId;
    }

    private function activateTenantUser(int $userId): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE app_users SET status = 'active' WHERE user_id = :user_id AND role = 'tenant'"
        );
        $stmt->execute([':user_id' => $userId]);
    }

    private function setTenantUserStatus(int $userId, string $status): void
    {
        $stmt = $this->conn->prepare(
            'UPDATE app_users SET status = :status WHERE user_id = :user_id AND role = \'tenant\''
        );
        $stmt->execute([':status' => $status, ':user_id' => $userId]);
    }
}
