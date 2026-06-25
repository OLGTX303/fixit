<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class BrowsingHistoryModel
{
    public function upsert(int $userId, int $providerId): void
    {
        Connection::get()->prepare(
            'INSERT INTO BrowsingHistory (user_id, provider_id, viewed_at)
             VALUES (:uid, :pid, NOW())
             ON DUPLICATE KEY UPDATE viewed_at = NOW()'
        )->execute(['uid' => $userId, 'pid' => $providerId]);
    }

    /** @return list<array<string,mixed>> enriched provider cards, recent first */
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
             FROM BrowsingHistory h
             INNER JOIN ProviderProfile p ON p.id = h.provider_id
             INNER JOIN User u ON u.id = p.user_id
             WHERE h.user_id = :uid
             ORDER BY h.viewed_at DESC, h.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return [];
        }

        $categoryModel = new CategoryModel();
        $catById = [];
        foreach ($categoryModel->all() as $c) {
            $catById[(int) $c['id']] = $c;
        }

        $providerModel = new ProviderModel();
        return array_map(
            fn ($row) => ProviderModel::stripContact($providerModel->enrichFromJoinRow($row, $catById)),
            $rows
        );
    }

    public function clear(int $userId): void
    {
        Connection::get()->prepare('DELETE FROM BrowsingHistory WHERE user_id = :uid')
            ->execute(['uid' => $userId]);
    }
}