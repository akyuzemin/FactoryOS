<?php

class User
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUsername(string $username): ?array
    {
        $sql = "
            SELECT
                u.id,
                u.username,
                u.email,
                u.password_hash,
                u.first_name,
                u.last_name,
                u.is_active,
                r.id AS role_id,
                r.name AS role_name
            FROM users u
            INNER JOIN roles r
                ON r.id = u.role_id
            WHERE u.username = :username
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'username' => $username
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function getAllWithRoles(): array
    {
        $sql = "
            SELECT
                u.id,
                u.username,
                u.first_name,
                u.last_name,
                r.name AS role_name,
                u.is_active,
                u.created_at
            FROM users u
            INNER JOIN roles r
                ON r.id = u.role_id
            ORDER BY u.created_at DESC, u.username ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Bakım modülü ve görev atamaları için aktif teknisyen/kullanıcı listesini döner.
     */
    public function getActiveTechnicians(): array
    {
        $sql = "
            SELECT id, username, first_name, last_name, role_id 
            FROM users 
            WHERE is_active = 1 
            ORDER BY first_name ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoles(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, description FROM roles ORDER BY name ASC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findByIdForManagement(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, email, password_hash, first_name, last_name, role_id, is_active
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function create(array $data): void
    {
        $roleStmt = $this->pdo->prepare('SELECT id FROM roles WHERE id = :id LIMIT 1');
        $roleStmt->execute(['id' => $data['role_id']]);

        if (!$roleStmt->fetchColumn()) {
            throw new InvalidArgumentException('Seçilen rol bulunamadı.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO users
                (username, email, password_hash, first_name, last_name, role_id, is_active)
             VALUES
                (:username, :email, :password_hash, :first_name, :last_name, :role_id, :is_active)'
        );
        $stmt->execute($data);
    }

    public function update(int $id, array $data, ?string $passwordHash, int $currentUserId): void
    {
        $data['id'] = $id;
        $data['current_user_id'] = $currentUserId;

        unset($data['password_hash']);

        $this->assertStatusChangeAllowed(
            $id,
            (int) $data['role_id'],
            (int) $data['is_active'],
            $currentUserId
        );

        if ($passwordHash === null) {
            $stmt = $this->pdo->prepare(
                'UPDATE users
                 SET username = :username,
                     email = :email,
                     first_name = :first_name,
                     last_name = :last_name,
                     role_id = :role_id,
                     is_active = :is_active
                 WHERE id = :id'
            );
        } else {
            $data['password_hash'] = $passwordHash;
            $stmt = $this->pdo->prepare(
                'UPDATE users
                 SET username = :username,
                     email = :email,
                     password_hash = :password_hash,
                     first_name = :first_name,
                     last_name = :last_name,
                     role_id = :role_id,
                     is_active = :is_active
                 WHERE id = :id'
            );
        }

        unset($data['current_user_id']);
        $stmt->execute($data);
    }

    public function deactivate(int $id, int $currentUserId): void
    {
        $user = $this->findByIdForManagement($id);

        if ($user === null) {
            throw new InvalidArgumentException('Kullanıcı bulunamadı.');
        }

        $this->assertStatusChangeAllowed($id, (int) $user['role_id'], 0, $currentUserId);

        $stmt = $this->pdo->prepare(
            'UPDATE users SET is_active = 0 WHERE id = :id AND is_active = 1'
        );
        $stmt->execute(['id' => $id]);
    }

    private function assertStatusChangeAllowed(
        int $userId,
        int $roleId,
        int $isActive,
        int $currentUserId
    ): void {
        if ($isActive === 0 && $userId === $currentUserId) {
            throw new InvalidArgumentException('Kullanıcı kendi hesabını pasifleştiremez.');
        }

        $roleStmt = $this->pdo->prepare('SELECT name FROM roles WHERE id = :id LIMIT 1');
        $roleStmt->execute(['id' => $roleId]);
        $roleName = $roleStmt->fetchColumn();

        if ($roleName === false) {
            throw new InvalidArgumentException('Seçilen rol bulunamadı.');
        }

        $currentRoleStmt = $this->pdo->prepare(
            'SELECT r.name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :user_id AND u.is_active = 1
             LIMIT 1'
        );
        $currentRoleStmt->execute(['user_id' => $userId]);
        $currentRoleName = $currentRoleStmt->fetchColumn();

        $removesActiveAdmin = $currentRoleName === 'Admin' && ($isActive === 0 || $roleName !== 'Admin');

        if ($removesActiveAdmin) {
            $adminCountStmt = $this->pdo->prepare(
                'SELECT COUNT(*)
                 FROM users u
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE u.is_active = 1 AND r.name = :role_name AND u.id <> :user_id'
            );
            $adminCountStmt->execute([
                'role_name' => 'Admin',
                'user_id' => $userId,
            ]);

            if ((int) $adminCountStmt->fetchColumn() === 0) {
                throw new InvalidArgumentException('Sistemde en az bir aktif Admin bulunmalıdır.');
            }
        }
    }
}