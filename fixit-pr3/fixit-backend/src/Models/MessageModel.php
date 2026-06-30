<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class MessageModel
{
    /** @return list<array<string,mixed>> */
    public function forJob(int $jobId, int $limit = 200, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $stmt = Connection::get()->prepare(
            "SELECT id, job_id, sender_id, body, ciphertext, iv, is_encrypted, is_system,
                    harm_status, harm_categories, content_hash, sent_at
             FROM Message WHERE job_id = :jid ORDER BY sent_at ASC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute(['jid' => $jobId]);
        return array_map(fn ($row) => $this->format($row), $stmt->fetchAll());
    }

    /** @param array<string,mixed> $payload */
    public function create(int $jobId, int $senderId, array $payload): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO Message
             (job_id, sender_id, body, ciphertext, iv, is_encrypted, is_system, harm_status, harm_categories, content_hash)
             VALUES (:jid, :sid, :body, :ct, :iv, :enc, :system, :harm, :cats, :hash)'
        );
        $cats = $payload['harm_categories'] ?? null;
        $stmt->execute([
            'jid' => $jobId,
            'sid' => $senderId,
            'body' => $payload['body'] ?? null,
            'ct' => $payload['ciphertext'] ?? null,
            'iv' => $payload['iv'] ?? null,
            'enc' => !empty($payload['is_encrypted']) ? 1 : 0,
            'system' => !empty($payload['is_system']) ? 1 : 0,
            'harm' => $payload['harm_status'] ?? 'clear',
            'cats' => is_array($cats) ? json_encode($cats) : null,
            'hash' => $payload['content_hash'] ?? null,
        ]);
        $id = (int) $pdo->lastInsertId();
        $fetch = Connection::get()->prepare(
            'SELECT id, job_id, sender_id, body, ciphertext, iv, is_encrypted, is_system,
                    harm_status, harm_categories, content_hash, sent_at
             FROM Message WHERE id = :id'
        );
        $fetch->execute(['id' => $id]);
        $row = $fetch->fetch();
        return $row ? $this->format($row) : [];
    }

    /** @param list<int> $jobIds @return array<int,array<string,mixed>> */
    public function latestForJobs(array $jobIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $jobIds)));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Connection::get()->prepare(
            "SELECT m.id, m.job_id, m.sender_id, m.body, m.ciphertext, m.iv, m.is_encrypted, m.is_system,
                    m.harm_status, m.harm_categories, m.content_hash, m.sent_at
             FROM Message m
             INNER JOIN (
                SELECT job_id, MAX(id) AS latest_id
                FROM Message
                WHERE job_id IN ({$placeholders})
                GROUP BY job_id
             ) x ON x.latest_id = m.id"
        );
        $stmt->execute($ids);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $msg = $this->format($row);
            $out[(int) $msg['job_id']] = $msg;
        }
        return $out;
    }

    /** @param array<string,mixed> $row */
    private function format(array $row): array
    {
        $cats = json_decode((string) ($row['harm_categories'] ?? '[]'), true);
        return [
            'id' => (int) $row['id'],
            'job_id' => (int) $row['job_id'],
            'sender_id' => (int) $row['sender_id'],
            'body' => $row['body'],
            'ciphertext' => $row['ciphertext'],
            'iv' => $row['iv'],
            'is_encrypted' => (bool) $row['is_encrypted'],
            'is_system' => (bool) ($row['is_system'] ?? false),
            'harm_status' => $row['harm_status'],
            'harm_categories' => is_array($cats) ? $cats : [],
            'content_hash' => $row['content_hash'],
            'sent_at' => str_replace(' ', 'T', $row['sent_at']),
        ];
    }
}
