<?php

class PermissionService
{
    private PDO $pdo;
    private static array $rolePermissionsCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Belirtilen role ait tüm izin adlarını döner (Tek sorgu + in-memory static cache).
     *
     * @param int $roleId
     * @return array<string>
     */
    public function getPermissionsForRole(int $roleId): array
    {
        if ($roleId < 1) {
            return [];
        }

        if (!isset(self::$rolePermissionsCache[$roleId])) {
            $sql = "
                SELECT p.name
                FROM role_permissions rp
                INNER JOIN permissions p
                    ON p.id = rp.permission_id
                WHERE rp.role_id = :role_id
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['role_id' => $roleId]);
            self::$rolePermissionsCache[$roleId] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return self::$rolePermissionsCache[$roleId];
    }

    /**
     * Rolün belirtilen izne sahip olup olmadığını doğrular.
     *
     * @param int $roleId
     * @param string $permissionName
     * @return bool
     */
    public function hasPermission(int $roleId, string $permissionName): bool
    {
        $permissions = $this->getPermissionsForRole($roleId);
        return in_array($permissionName, $permissions, true);
    }
}