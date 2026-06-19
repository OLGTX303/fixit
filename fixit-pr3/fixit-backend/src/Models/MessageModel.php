<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class MessageModel
{
    /** @return list<array<string,mixed>> */
    public function forJob(int $jobId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, job_id, sender_id, body, ciphertext, iv, is_encrypted,
                    harm_status, harm_categories, content_hash, sent_at
             FROM Message WHERE job_id = :jid ORDER BY sent_at ASC'
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
             (job_id, sender_id, body, ciphertext, iv, is_encrypted, harm_status, harm_categories, content_hash)
             VALUES (:jid, :sid, :body, :ct, :iv, :enc, :harm, :cats, :hash)'
        );
        $cats = $payload['harm_categories'] ?? null;
        $stmt->execute([
            'jid' => $jobId,
            'sid' => $senderId,
            'body' => $payload['body'] ?? null,
            'ct' => $payload['ciphertext'] ?? null,
            'iv' => $payload['iv'] ?? null,
            'enc' => !empty($payload['is_encrypted']) ? 1 : 0,
            'harm' => $payload['harm_status'] ?? 'clear',
            'cats' => is_array($cats) ? json_encode($cats) : null,
            'hash' => $payload['content_hash'] ?? null,
        ]);
        $id = (int) $pdo->lastInsertId();
        $fetch = Connection::get()->prepare(
            'SELECT id, job_id, sender_id, body, ciphertext, iv, is_encrypted,
                    harm_status, harm_categories, content_hash, sent_at
             FROM Message WHERE id = :id'
        );
        $fetch->execute(['id' => $id]);
        $row = $fetch->fetch();
        return $row ? $this->format($row) : [];
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
            'harm_status' => $row['harm_status'],
            'harm_categories' => is_array($cats) ? $cats : [],
            'content_hash' => $row['content_hash'],
            'sent_at' => str_replace(' ', 'T', $row['sent_at']),
        ];
    }
}