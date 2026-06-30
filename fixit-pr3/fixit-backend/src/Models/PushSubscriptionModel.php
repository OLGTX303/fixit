<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class PushSubscriptionModel
{
    /**
     * Upsert a device's push target. $data:
     *   web     => ['endpoint','p256dh','auth']
     *   android => ['fcm_token']
     */
    public function upsert(int $userId, string $platform, array $data): void
    {
        $dedupe = $platform === 'android'
            ? (string) ($data['fcm_token'] ?? '')
            : (string) ($data['endpoint'] ?? '');
        if ($dedupe === '') {
            return;
        }

        Connection::get()->prepare(
            'INSERT INTO PushSubscription (user_id, platform, dedupe_key, endpoint, p256dh, auth, fcm_token)
             VALUES (:uid, :plat, :key, :endpoint, :p256dh, :auth, :token)
             ON DUPLICATE KEY UPDATE
               platform = VALUES(platform), endpoint = VALUES(endpoint),
               p256dh = VALUES(p256dh), auth = VALUES(auth), fcm_token = VALUES(fcm_token)'
        )->execute([
            'uid'      => $userId,
            'plat'     => $platform,
            'key'      => substr($dedupe, 0, 191),
            'endpoint' => $data['endpoint'] ?? null,
            'p256dh'   => $data['p256dh'] ?? null,
            'auth'     => $data['auth'] ?? null,
            'token'    => $data['fcm_token'] ?? null,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function forUser(int $userId): array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM PushSubscription WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function remove(int $userId, string $dedupeKey): void
    {
        Connection::get()->prepare(
            'DELETE FROM PushSubscription WHERE user_id = :uid AND dedupe_key = :key'
        )->execute(['uid' => $userId, 'key' => substr($dedupeKey, 0, 191)]);
    }

    /** A dead endpoint/token (410/404 from the push service) should be pruned. */
    public function removeById(int $id): void
    {
        Connection::get()->prepare('DELETE FROM PushSubscription WHERE id = :id')
            ->execute(['id' => $id]);
    }
}
