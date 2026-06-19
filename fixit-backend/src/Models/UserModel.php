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
        $stmt = Connection::get()->prepare('SELECT id, name, email, role, phone FROM User WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
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