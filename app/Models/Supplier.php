<?php

class Supplier
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
                contact_name,
                phone,
                email,
                address,
                is_active
            FROM suppliers
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
            'SELECT id, code, name, contact_name, phone, email, address
             FROM suppliers
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $supplier = $stmt->fetch();

        return $supplier ?: null;
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO suppliers
                (code, name, contact_name, phone, email, address, is_active)
             VALUES
                (:code, :name, :contact_name, :phone, :email, :address, 1)'
        );
        $stmt->execute($data);
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;

        $stmt = $this->pdo->prepare(
            'UPDATE suppliers
             SET code = :code,
                 name = :name,
                 contact_name = :contact_name,
                 phone = :phone,
                 email = :email,
                 address = :address
             WHERE id = :id AND is_active = 1'
        );
        $stmt->execute($data);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE suppliers SET is_active = 0 WHERE id = :id AND is_active = 1'
        );
        $stmt->execute(['id' => $id]);
    }
}
