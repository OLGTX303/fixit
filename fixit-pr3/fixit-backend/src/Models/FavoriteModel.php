<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class FavoriteModel
{
    /** @return list<array<string,mixed>> enriched provider cards, newest first */
    public function listForUser(int $userId, int $limit, int $offset): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $stmt = Connection::get()->prepare(
            "SELECT provider_id FROM Favorite
             WHERE user_id = :uid
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute(['uid' => $userId]);
        $ids = array_column($stmt->fetchAll(), 'provider_id');

        $providerModel = new ProviderModel();
        $out = [];
        foreach ($ids as $pid) {
            $p = $providerModel->getEnriched((int) $pid);
            if ($p) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** @return list<int> */
    public function providerIdsForUser(int $userId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT provider_id FROM Favorite WHERE user_id = :uid ORDER BY created_at DESC'
        );
        $stmt->execute(['uid' => $userId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'provider_id'));
    }

    public function isFavorite(int $userId, int $providerId): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT 1 FROM Favorite WHERE user_id = :uid AND provider_id = :pid LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'pid' => $providerId]);
        return (bool) $stmt->fetchColumn();
    }

    public function add(int $userId, int $providerId): void
    {
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT IGNORE INTO Favorite (user_id, provider_id) VALUES (:uid, :pid)'
        )->execute(['uid' => $userId, 'pid' => $providerId]);
    }

    public function remove(int $userId, int $providerId): bool
    {
        $stmt = Connection::get()->prepare(
            'DELETE FROM Favorite WHERE user_id = :uid AND provider_id = :pid'
        );
        $stmt->execute(['uid' => $userId, 'pid' => $providerId]);
        return $stmt->rowCount() > 0;
    }
}