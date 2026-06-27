<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;
use PDO;

/** Storage for the per-interaction encryption channel: sessions + replay nonces. */
final class SecureSessionModel
{
    public function create(string $sessionId, int $userId, string $master, string $mac, string $salt, int $ttl): void
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO enc_session (session_id, user_id, master_secret, mac_key, salt, expires_at)
             VALUES (:sid, :uid, :master, :mac, :salt, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
        );
        $stmt->bindValue(':sid', $sessionId);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':master', $master, PDO::PARAM_LOB);
        $stmt->bindValue(':mac', $mac, PDO::PARAM_LOB);
        $stmt->bindValue(':salt', $salt, PDO::PARAM_LOB);
        $stmt->bindValue(':ttl', $ttl, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** @return array{master_secret:string,mac_key:string,salt:string}|null */
    public function find(string $sessionId, int $userId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT master_secret, mac_key, salt FROM enc_session
             WHERE session_id = :sid AND user_id = :uid AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['sid' => $sessionId, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Atomic test-and-set replay guard (§5): false if the nonce was already seen. */
    public function claimNonce(string $sessionId, string $nonce, int $ttl): bool
    {
        $pdo = Connection::get();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO enc_nonce (session_id, nonce, expires_at)
                 VALUES (:sid, :nonce, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
            );
            $stmt->bindValue(':sid', $sessionId);
            $stmt->bindValue(':nonce', $nonce);
            $stmt->bindValue(':ttl', $ttl, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (\PDOException) {
            return false; // duplicate primary key → replay
        }
    }

    /** Opportunistic TTL cleanup — cheap, keeps both tables bounded. */
    public function purgeExpired(): void
    {
        $pdo = Connection::get();
        $pdo->query('DELETE FROM enc_nonce WHERE expires_at < NOW()');
        $pdo->query('DELETE FROM enc_session WHERE expires_at < NOW()');
    }
}
