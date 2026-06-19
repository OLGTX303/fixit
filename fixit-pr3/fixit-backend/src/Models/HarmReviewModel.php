<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class HarmReviewModel
{
    public function create(int $messageId, int $jobId, int $senderId, string $status, ?array $categories, ?string $contentHash): int
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO HarmMessageReview (message_id, job_id, sender_id, harm_status, harm_categories, content_hash)
             VALUES (:mid, :jid, :sid, :status, :cats, :hash)'
        );
        $stmt->execute([
            'mid' => $messageId,
            'jid' => $jobId,
            'sid' => $senderId,
            'status' => $status,
            'cats' => $categories ? json_encode($categories) : null,
            'hash' => $contentHash,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** @return list<array<string,mixed>> */
    public function listPending(): array
    {
        $stmt = Connection::get()->query(
            "SELECT h.*, u.name AS sender_name, m.sent_at AS message_sent_at
             FROM HarmMessageReview h
             JOIN User u ON u.id = h.sender_id
             JOIN Message m ON m.id = h.message_id
             WHERE h.harm_status IN ('flagged', 'blocked')
             ORDER BY h.created_at DESC"
        );
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->format($r), $rows);
    }

    public function review(int $id, int $adminId, string $status, ?string $notes): ?array
    {
        $allowed = ['reviewed_clear', 'reviewed_action'];
        if (!in_array($status, $allowed, true)) {
            return null;
        }
        $stmt = Connection::get()->prepare(
            'UPDATE HarmMessageReview SET harm_status = :status, admin_notes = :notes,
             reviewed_by = :aid, reviewed_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'status' => $status, 'notes' => $notes, 'aid' => $adminId]);
        $fetch = Connection::get()->prepare('SELECT * FROM HarmMessageReview WHERE id = :id');
        $fetch->execute(['id' => $id]);
        $row = $fetch->fetch();
        return $row ? $this->format($row) : null;
    }

    /** @param array<string,mixed> $row */
    private function format(array $row): array
    {
        $cats = json_decode((string) ($row['harm_categories'] ?? '[]'), true);
        return [
            'id' => (int) $row['id'],
            'message_id' => (int) $row['message_id'],
            'job_id' => (int) $row['job_id'],
            'sender_id' => (int) $row['sender_id'],
            'sender_name' => $row['sender_name'] ?? null,
            'harm_status' => $row['harm_status'],
            'harm_categories' => is_array($cats) ? $cats : [],
            'content_hash' => $row['content_hash'],
            'admin_notes' => $row['admin_notes'],
            'reviewed_by' => $row['reviewed_by'] ? (int) $row['reviewed_by'] : null,
            'reviewed_at' => $row['reviewed_at'] ? str_replace(' ', 'T', $row['reviewed_at']) : null,
            'created_at' => str_replace(' ', 'T', $row['created_at']),
            'message_sent_at' => isset($row['message_sent_at'])
                ? str_replace(' ', 'T', $row['message_sent_at']) : null,
        ];
    }
}