<?php

class Warehouse
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllActive(): array
    {
        $sql = "
            SELECT
                id,
                code,
                name,
                description,
                is_active,
                created_at
            FROM warehouses
            WHERE is_active = 1
            ORDER BY name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findActiveById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, name, description
             FROM warehouses
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $warehouse = $stmt->fetch();

        return $warehouse ?: null;
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO warehouses (code, name, description, is_active)
             VALUES (:code, :name, :description, 1)'
        );
        $stmt->execute($data);
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;

        $stmt = $this->pdo->prepare(
            'UPDATE warehouses
             SET code = :code,
                 name = :name,
                 description = :description
             WHERE id = :id AND is_active = 1'
        );
        $stmt->execute($data);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE warehouses SET is_active = 0 WHERE id = :id AND is_active = 1'
        );
        $stmt->execute(['id' => $id]);
    }
}
