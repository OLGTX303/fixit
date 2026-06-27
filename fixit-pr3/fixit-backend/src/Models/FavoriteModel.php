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
            "SELECT p.*, u.name, u.email, u.phone, u.avatar_url,
                    (SELECT GROUP_CONCAT(pc.category_id ORDER BY pc.category_id)
                     FROM ProviderCategory pc WHERE pc.provider_id = p.id) AS category_ids_csv,
                    (SELECT COUNT(*) FROM Review r
                     INNER JOIN Job j ON j.id = r.job_id WHERE j.provider_id = p.id) AS review_count
             FROM Favorite f
             INNER JOIN ProviderProfile p ON p.id = f.provider_id
             INNER JOIN User u ON u.id = p.user_id
             WHERE f.user_id = :uid
             ORDER BY f.created_at DESC, f.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return [];
        }

        $catById = (new CategoryModel())->byId();
        $providerModel = new ProviderModel();
        return array_map(
            fn ($row) => ProviderModel::stripContact($providerModel->enrichFromJoinRow($row, $catById)),
            $rows
        );
    }

    /** @return list<int> */
    public function providerIdsForUser(int $userId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT provider_id FROM Favorite WHERE user_id = :uid ORDER BY created_at DESC LIMIT 500'
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