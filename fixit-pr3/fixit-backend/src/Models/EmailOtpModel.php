<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class EmailOtpModel
{
    /** Replace any existing pending OTP for this user with a fresh one. */
    public function create(int $userId, string $newEmail, string $otp, int $ttlSeconds = 600): void
    {
        $pdo = Connection::get();
        $pdo->prepare('DELETE FROM EmailOtp WHERE user_id = :id')->execute(['id' => $userId]);
        $stmt = $pdo->prepare(
            'INSERT INTO EmailOtp (user_id, new_email, otp_hash, expires_at)
             VALUES (:id, :email, :hash, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
        );
        $stmt->bindValue('id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('email', $newEmail);
        $stmt->bindValue('hash', password_hash($otp, PASSWORD_BCRYPT));
        $stmt->bindValue('ttl', $ttlSeconds, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /** Pre-registration OTP (no user yet) — keyed on email only, user_id NULL. */
    public function createForEmail(string $email, string $otp, int $ttlSeconds = 600): void
    {
        $pdo = Connection::get();
        $pdo->prepare('DELETE FROM EmailOtp WHERE user_id IS NULL AND new_email = :email')
            ->execute(['email' => $email]);
        $stmt = $pdo->prepare(
            'INSERT INTO EmailOtp (user_id, new_email, otp_hash, expires_at)
             VALUES (NULL, :email, :hash, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
        );
        $stmt->bindValue('email', $email);
        $stmt->bindValue('hash', password_hash($otp, PASSWORD_BCRYPT));
        $stmt->bindValue('ttl', $ttlSeconds, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function findPendingForEmail(string $email): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM EmailOtp WHERE user_id IS NULL AND new_email = :email
             AND expires_at > NOW() ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function findPending(int $userId, string $newEmail): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM EmailOtp WHERE user_id = :id AND new_email = :email
             AND expires_at > NOW() ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['id' => $userId, 'email' => $newEmail]);
        return $stmt->fetch() ?: null;
    }

    public function incrementAttempts(int $id): void
    {
        Connection::get()->prepare('UPDATE EmailOtp SET attempts = attempts + 1 WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public function delete(int $id): void
    {
        Connection::get()->prepare('DELETE FROM EmailOtp WHERE id = :id')->execute(['id' => $id]);
    }
}
