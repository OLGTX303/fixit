<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class CryptoModel
{
    public function hasPinSetup(int $userId): bool
    {
        $stmt = Connection::get()->prepare('SELECT 1 FROM UserCrypto WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function getPinSalt(int $userId): ?string
    {
        $stmt = Connection::get()->prepare('SELECT pin_salt FROM UserCrypto WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $salt = $stmt->fetchColumn();
        return $salt ? (string) $salt : null;
    }

    public function setupPin(int $userId, string $pinSalt, string $pinVerifier, string $publicKeyJwk, string $wrappedPrivate, string $privateIv): void
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO UserCrypto (user_id, pin_salt, pin_verifier, public_key_jwk, wrapped_private_key, private_key_iv)
             VALUES (:uid, :salt, :verifier, :pub, :priv, :piv)
             ON DUPLICATE KEY UPDATE pin_salt = :salt2, pin_verifier = :verifier2,
             public_key_jwk = :pub2, wrapped_private_key = :priv2, private_key_iv = :piv2'
        );
        $stmt->execute([
            'uid' => $userId, 'salt' => $pinSalt, 'verifier' => $pinVerifier,
            'pub' => $publicKeyJwk, 'priv' => $wrappedPrivate, 'piv' => $privateIv,
            'salt2' => $pinSalt, 'verifier2' => $pinVerifier,
            'pub2' => $publicKeyJwk, 'priv2' => $wrappedPrivate, 'piv2' => $privateIv,
        ]);
    }

    public function getUserCrypto(int $userId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT pin_salt, pin_verifier, public_key_jwk, wrapped_private_key, private_key_iv
             FROM UserCrypto WHERE user_id = :uid LIMIT 1'
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function verifyPin(int $userId, string $pinVerifier): bool
    {
        $stmt = Connection::get()->prepare('SELECT pin_verifier FROM UserCrypto WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $stored = $stmt->fetchColumn();
        return $stored && hash_equals((string) $stored, $pinVerifier);
    }

    public function getPublicKeyJwk(int $userId): ?array
    {
        $stmt = Connection::get()->prepare('SELECT public_key_jwk FROM UserCrypto WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $jwk = $stmt->fetchColumn();
        if (!$jwk) return null;
        $decoded = json_decode((string) $jwk, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function getJobKey(int $jobId, int $userId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT encrypted_job_key FROM JobCryptoKey WHERE job_id = :jid AND user_id = :uid LIMIT 1'
        );
        $stmt->execute(['jid' => $jobId, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveJobKey(int $jobId, int $userId, string $encryptedJobKey): void
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO JobCryptoKey (job_id, user_id, encrypted_job_key)
             VALUES (:jid, :uid, :ek)
             ON DUPLICATE KEY UPDATE encrypted_job_key = :ek2, updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute(['jid' => $jobId, 'uid' => $userId, 'ek' => $encryptedJobKey, 'ek2' => $encryptedJobKey]);
    }
}