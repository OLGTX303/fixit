<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class AvailabilityModel
{
    /** @return list<array<string,mixed>> */
    public function forProvider(int $providerId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, day_of_week, start_time, end_time, auto_confirm
             FROM ProviderAvailability
             WHERE provider_id = :pid
             ORDER BY day_of_week, start_time'
        );
        $stmt->execute(['pid' => $providerId]);
        return array_map(fn ($r) => $this->format($r), $stmt->fetchAll());
    }

    /**
     * Replace all slots for a provider in one transaction.
     * $slots = [['day_of_week'=>1,'start_time'=>'09:00','end_time'=>'17:00','auto_confirm'=>true], ...]
     *
     * @param list<array<string,mixed>> $slots
     * @return list<array<string,mixed>>
     */
    public function save(int $providerId, array $slots): array
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM ProviderAvailability WHERE provider_id = :pid');
            $del->execute(['pid' => $providerId]);

            $ins = $pdo->prepare(
                'INSERT INTO ProviderAvailability
                 (provider_id, day_of_week, start_time, end_time, auto_confirm)
                 VALUES (:pid, :dow, :start, :end, :ac)'
            );
            foreach ($slots as $slot) {
                $ins->execute([
                    'pid'   => $providerId,
                    'dow'   => (int) $slot['day_of_week'],
                    'start' => (string) $slot['start_time'],
                    'end'   => (string) $slot['end_time'],
                    'ac'    => isset($slot['auto_confirm']) && $slot['auto_confirm'] ? 1 : 0,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $this->forProvider($providerId);
    }

    /** @param array<string,mixed> $row */
    private function format(array $row): array
    {
        return [
            'id'           => (int) $row['id'],
            'day_of_week'  => (int) $row['day_of_week'],
            'start_time'   => $row['start_time'],
            'end_time'     => $row['end_time'],
            'auto_confirm' => (bool) $row['auto_confirm'],
        ];
    }
}
