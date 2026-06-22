<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;
use PDO;

final class ReviewModel
{
    public function create(int $jobId, int $rating, ?string $comment, ?float $tipAmount = null, array $imageUrls = []): array
    {
        $pdo = Connection::get();
        $imageJson = $imageUrls ? json_encode(array_values($imageUrls)) : null;

        // Add image_urls column if it doesn't exist yet (safe lazy migration)
        try {
            $pdo->exec("ALTER TABLE Review ADD COLUMN image_urls TEXT NULL");
        } catch (\Throwable) {}

        $stmt = $pdo->prepare(
            'INSERT INTO Review (job_id, rating, comment, tip_amount, image_urls)
             VALUES (:job, :rating, :comment, :tip, :imgs)'
        );
        $stmt->execute([
            'job'     => $jobId,
            'rating'  => $rating,
            'comment' => $comment,
            'tip'     => $tipAmount,
            'imgs'    => $imageJson,
        ]);
        $id = (int) $pdo->lastInsertId();
        $this->recalculateProviderRating($jobId);
        return $this->find($id) ?? [];
    }

    public function find(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM Review WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->format($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public function forProvider(int $providerId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT r.*, u.name AS customer_name, u.avatar_url AS customer_avatar
             FROM Review r
             JOIN Job j ON j.id = r.job_id
             JOIN User u ON u.id = j.customer_id
             WHERE j.provider_id = :pid
             ORDER BY r.created_at DESC'
        );
        $stmt->execute(['pid' => $providerId]);
        return array_map(fn ($row) => $this->format($row), $stmt->fetchAll());
    }

    public function all(): array
    {
        $stmt = Connection::get()->query('SELECT * FROM Review ORDER BY id');
        return array_map(fn ($row) => $this->format($row), $stmt->fetchAll());
    }

    private function recalculateProviderRating(int $jobId): void
    {
        $stmt = Connection::get()->prepare(
            'SELECT j.provider_id, AVG(r.rating) AS avg_rating
             FROM Job j
             JOIN Review r ON r.job_id = j.id
             WHERE j.provider_id = (SELECT provider_id FROM Job WHERE id = :jid)
             GROUP BY j.provider_id'
        );
        $stmt->execute(['jid' => $jobId]);
        $row = $stmt->fetch();
        if ($row) {
            $upd = Connection::get()->prepare(
                'UPDATE ProviderProfile SET avg_rating = :avg WHERE id = :pid'
            );
            $upd->execute([
                'avg' => round((float) $row['avg_rating'], 2),
                'pid' => (int) $row['provider_id'],
            ]);
        }
    }

    /** @param array<string,mixed> $row */
    private function format(array $row): array
    {
        $imgs = [];
        if (!empty($row['image_urls'])) {
            $decoded = json_decode((string) $row['image_urls'], true);
            if (is_array($decoded)) $imgs = $decoded;
        }
        return [
            'id'              => (int) $row['id'],
            'job_id'          => (int) $row['job_id'],
            'rating'          => (int) $row['rating'],
            'comment'         => $row['comment'],
            'tip_amount'      => isset($row['tip_amount']) ? (float) $row['tip_amount'] : null,
            'image_urls'      => $imgs,
            'customer_name'   => $row['customer_name'] ?? null,
            'customer_avatar' => $row['customer_avatar'] ?? null,
            'created_at'      => str_replace(' ', 'T', $row['created_at']),
        ];
    }
}