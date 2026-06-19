<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;
use PDO;

final class UserModel
{
    public function findByEmail(string $email): ?array
    {
        $stmt = Connection::get()->prepare('SELECT id, name, email, password_hash, role, phone FROM User WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, name, email, role, phone,
                    stripe_test_customer_id,
                    stripe_test_default_payment_method_id,
                    stripe_test_payment_method_last4,
                    stripe_test_payment_method_brand,
                    stripe_test_payment_method_created_at
             FROM User WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveStripeCustomerId(int $userId, string $customerId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE User SET stripe_test_customer_id = :cid WHERE id = :id'
        );
        $stmt->execute(['id' => $userId, 'cid' => $customerId]);
    }

    /** @param array{payment_method_id:string,last4:?string,brand:?string} $data */
    public function saveStripePaymentMethod(int $userId, array $data): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE User SET
             stripe_test_default_payment_method_id = :pm,
             stripe_test_payment_method_last4 = :last4,
             stripe_test_payment_method_brand = :brand,
             stripe_test_payment_method_created_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'pm' => $data['payment_method_id'],
            'last4' => $data['last4'],
            'brand' => $data['brand'],
        ]);
    }

    public function clearStripePaymentMethod(int $userId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE User SET
             stripe_test_default_payment_method_id = NULL,
             stripe_test_payment_method_last4 = NULL,
             stripe_test_payment_method_brand = NULL,
             stripe_test_payment_method_created_at = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
    }

    public function create(string $name, string $email, string $passwordHash, string $role, ?string $phone): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO User (name, email, password_hash, role, phone) VALUES (:name, :email, :hash, :role, :phone)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'hash' => $passwordHash,
            'role' => $role,
            'phone' => $phone,
        ]);
        return $this->findById((int) $pdo->lastInsertId()) ?? [];
    }

    public function listAll(): array
    {
        $stmt = Connection::get()->query('SELECT id, name, email, role, phone FROM User ORDER BY id');
        return $stmt->fetchAll();
    }
}