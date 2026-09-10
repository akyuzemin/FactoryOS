<?php

class AuditService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Records an audit log entry in a fail-safe manner.
     */
    public function log(
        string $action,
        ?string $module = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): bool {
        try {
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = (int)$_SESSION['user_id'];
            }

            $ipAddress = $this->getClientIp();
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
            $tableName = $entityType ?: ($module ?: 'system');

            $sql = "
                INSERT INTO audit_logs (
                    user_id, action, module, entity_type, entity_id,
                    description, table_name, record_id,
                    old_values, new_values, ip_address, user_agent, created_at
                ) VALUES (
                    :user_id, :action, :module, :entity_type, :entity_id,
                    :description, :table_name, :record_id,
                    :old_values, :new_values, :ip_address, :user_agent, NOW()
                )
            ";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':user_id'     => $userId ?: null,
                ':action'      => substr($action, 0, 100),
                ':module'      => $module ? substr($module, 0, 50) : null,
                ':entity_type' => $entityType ? substr($entityType, 0, 100) : null,
                ':entity_id'   => $entityId,
                ':description' => $description,
                ':table_name'  => substr($tableName, 0, 100),
                ':record_id'   => $entityId,
                ':old_values'  => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                ':new_values'  => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                ':ip_address'  => $ipAddress,
                ':user_agent'  => $userAgent
            ]);
        } catch (Throwable $e) {
            // Fail-safe: Audit logging failure should not crash main business logic
            error_log('[AuditService Error] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Convenience method for authentication events.
     */
    public function logAuth(string $action, string $description, ?int $userId = null, ?string $username = null, bool $success = true): bool
    {
        $newValues = [
            'username' => $username,
            'success'  => $success
        ];
        return $this->log(
            action: $action,
            module: 'AUTH',
            entityType: 'users',
            entityId: $userId,
            description: $description,
            oldValues: null,
            newValues: $newValues,
            userId: $userId
        );
    }

    /**
     * Convenience method for business entity actions.
     */
    public function logAction(
        string $module,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): bool {
        return $this->log(
            action: $action,
            module: strtoupper($module),
            entityType: $entityType ?: strtolower($module),
            entityId: $entityId,
            description: $description,
            oldValues: $oldValues,
            newValues: $newValues
        );
    }

    /**
     * Query audit logs with flexible filtering.
     */
    public function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'a.user_id = :user_id';
            $params[':user_id'] = (int)$filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $conditions[] = 'a.module = :module';
            $params[':module'] = $filters['module'];
        }
        if (!empty($filters['action'])) {
            $conditions[] = 'a.action LIKE :action';
            $params[':action'] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['start_date'])) {
            $conditions[] = 'DATE(a.created_at) >= :start_date';
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $conditions[] = 'DATE(a.created_at) <= :end_date';
            $params[':end_date'] = $filters['end_date'];
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT 
                a.*,
                u.username,
                u.first_name,
                u.last_name,
                r.name as role_name
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            {$whereClause}
            ORDER BY a.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Total count of audit logs matching filters.
     */
    public function getLogCount(array $filters = []): int
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params[':user_id'] = (int)$filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $conditions[] = 'module = :module';
            $params[':module'] = $filters['module'];
        }
        if (!empty($filters['action'])) {
            $conditions[] = 'action LIKE :action';
            $params[':action'] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['start_date'])) {
            $conditions[] = 'DATE(created_at) >= :start_date';
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $conditions[] = 'DATE(created_at) <= :end_date';
            $params[':end_date'] = $filters['end_date'];
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT COUNT(*) FROM audit_logs {$whereClause}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Resolves the client IP address safely.
     */
    private function getClientIp(): ?string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return substr($_SERVER['HTTP_CLIENT_IP'], 0, 45);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return substr(trim($ips[0]), 0, 45);
        }
        return !empty($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '127.0.0.1';
    }
}

