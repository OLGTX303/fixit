<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;
use PDO;

final class UserModel
{
    public function findByEmail(string $email): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, name, email, password_hash, role, phone, location_label, region, latitude, longitude,
                    avatar_url, COALESCE(is_blocked,0) AS is_blocked
             FROM User WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Strip Stripe identifiers from user objects returned to clients. */
    public static function toPublic(?array $user): ?array
    {
        if (!$user) {
            return null;
        }
        unset(
            $user['stripe_test_customer_id'],
            $user['stripe_test_default_payment_method_id'],
            $user['stripe_test_payment_method_last4'],
            $user['stripe_test_payment_method_brand'],
            $user['stripe_test_payment_method_created_at'],
            $user['password_hash']
        );
        return $user;
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, name, email, role, phone, location_label, region, latitude, longitude,
                    avatar_url, COALESCE(is_blocked, 0) AS is_blocked,
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

    public function create(
        string $name,
        string $email,
        string $passwordHash,
        string $role,
        ?string $phone,
        ?string $legalPolicyVersion = null
    ): array {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO User
             (name, email, password_hash, role, phone, terms_accepted_at, privacy_accepted_at, legal_policy_version)
             VALUES (:name, :email, :hash, :role, :phone, NOW(), NOW(), :legal_version)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'hash' => $passwordHash,
            'role' => $role,
            'phone' => $phone,
            'legal_version' => $legalPolicyVersion,
        ]);
        return $this->findById((int) $pdo->lastInsertId()) ?? [];
    }

    public function listAll(): array
    {
        // ponytail: lazy column — add once, silently ignored if already exists
        try { Connection::get()->exec('ALTER TABLE User ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0'); } catch (\Throwable) {}
        $stmt = Connection::get()->query('SELECT id, name, email, role, phone, avatar_url, is_blocked FROM User ORDER BY id');
        return $stmt->fetchAll();
    }

    /** One page of users (search + sort), with provider verification joined in. */
    public function listPaged(string $q, int $limit, int $offset, string $sort): array
    {
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = ' WHERE (u.name LIKE :qa OR u.email LIKE :qb)';
            $params['qa'] = '%' . $q . '%'; $params['qb'] = '%' . $q . '%';
        }
        $order = match ($sort) {
            'role'    => 'u.role ASC, u.name ASC',
            'recent'  => 'u.id DESC',
            'blocked' => 'u.is_blocked DESC, u.name ASC',
            default   => 'u.name ASC',
        };
        $limit = max(1, $limit); $offset = max(0, $offset);
        $stmt = Connection::get()->prepare(
            "SELECT u.id, u.name, u.email, u.role, u.phone, u.location_label, u.region,
                    u.latitude, u.longitude, u.avatar_url, u.is_blocked,
                    pp.is_verified AS provider_verified
             FROM User u
             LEFT JOIN ProviderProfile pp ON pp.user_id = u.id
             $where ORDER BY $order LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countFiltered(string $q): int
    {
        if ($q === '') return (int) Connection::get()->query('SELECT COUNT(*) FROM User')->fetchColumn();
        $stmt = Connection::get()->prepare('SELECT COUNT(*) FROM User WHERE name LIKE :qa OR email LIKE :qb');
        $stmt->execute(['qa' => '%' . $q . '%', 'qb' => '%' . $q . '%']);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{total:int,customers:int,providers:int,blocked:int} */
    public function roleCounts(): array
    {
        $out = ['total' => 0, 'customers' => 0, 'providers' => 0, 'blocked' => 0];
        foreach (Connection::get()->query('SELECT role, COUNT(*) c FROM User GROUP BY role') as $r) {
            $out['total'] += (int) $r['c'];
            if ($r['role'] === 'customer') $out['customers'] = (int) $r['c'];
            if ($r['role'] === 'provider') $out['providers'] = (int) $r['c'];
        }
        $out['blocked'] = (int) Connection::get()->query('SELECT COUNT(*) FROM User WHERE is_blocked = 1')->fetchColumn();
        return $out;
    }

    public function setBlocked(int $id, bool $blocked): void
    {
        Connection::get()->prepare('UPDATE User SET is_blocked = :b WHERE id = :id')
            ->execute(['id' => $id, 'b' => (int) $blocked]);
    }

    /** True if another user (not $excludeId) already uses this email. */
    public function emailTakenByOther(string $email, int $excludeId): bool
    {
        $stmt = Connection::get()->prepare('SELECT id FROM User WHERE email = :email AND id <> :id LIMIT 1');
        $stmt->execute(['email' => $email, 'id' => $excludeId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Update editable profile fields. Only keys present in $fields are written.
     * @param array{name?:string,email?:string,phone?:?string,avatar_url?:string} $fields
     */
    public function updateProfile(int $userId, array $fields): ?array
    {
        $allowed = ['name', 'email', 'phone', 'avatar_url', 'location_label', 'region', 'latitude', 'longitude'];
        $set = [];
        $params = ['id' => $userId];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $set[] = "$col = :$col";
                $params[$col] = $fields[$col];
            }
        }
        if ($set) {
            $sql = 'UPDATE User SET ' . implode(', ', $set) . ' WHERE id = :id';
            Connection::get()->prepare($sql)->execute($params);
        }
        return $this->findById($userId);
    }
}