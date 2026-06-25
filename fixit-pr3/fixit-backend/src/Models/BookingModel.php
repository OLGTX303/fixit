<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;
use PDO;

final class BookingModel
{
    private ProviderModel $providers;
    private CategoryModel $categories;
    private UserModel $users;

    public function __construct()
    {
        $this->providers = new ProviderModel();
        $this->categories = new CategoryModel();
        $this->users = new UserModel();
    }

    /**
     * Paginated booking list for the authenticated user. Inquiry threads are
     * excluded — they are conversations, not orders.
     *
     * @return list<array<string,mixed>>
     */
    public function listForUser(array $user, int $limit = 0, int $offset = 0, ?string $statusFilter = null): array
    {
        $sql = "SELECT * FROM Job WHERE status != 'inquiry'";
        $params = [];

        if ($user['role'] === 'customer') {
            $sql .= ' AND customer_id = :uid';
            $params['uid'] = $user['id'];
        } elseif ($user['role'] === 'provider') {
            $profile = (new ProviderModel())->findByUserId((int) $user['id']);
            if (!$profile) {
                return [];
            }
            $sql .= ' AND provider_id = :pid';
            $params['pid'] = (int) $profile['id'];
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $groups = [
                'pending'   => ['requested'],
                'active'    => ['accepted', 'in_progress'],
                'done'      => ['completed', 'reviewed'],
                'cancelled' => ['cancelled'],
            ];
            if (isset($groups[$statusFilter])) {
                $placeholders = [];
                foreach ($groups[$statusFilter] as $i => $st) {
                    $key = 'st' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $st;
                }
                $sql .= ' AND status IN (' . implode(',', $placeholders) . ')';
            }
        }

        $sql .= ' ORDER BY scheduled_at DESC, id DESC';
        $limit = max(1, min(50, $limit > 0 ? $limit : 50));
        $offset = max(0, $offset);
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(fn ($row) => $this->enrich($row), $rows);
    }

    public function findEnriched(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM Job WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->enrich($row) : null;
    }

    /** Existing pre-order inquiry thread between a customer and provider, if any. */
    public function findInquiry(int $customerId, int $providerId): ?array
    {
        $stmt = Connection::get()->prepare(
            "SELECT * FROM Job WHERE customer_id = :c AND provider_id = :p AND status = 'inquiry'
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['c' => $customerId, 'p' => $providerId]);
        $row = $stmt->fetch();
        return $row ? $this->enrich($row) : null;
    }

    public function create(array $data): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO Job
             (customer_id, provider_id, category_id, status, scheduled_at, address, total, notes,
              recurrence_type, recurrence_end_date)
             VALUES (:cid, :pid, :cat, :status, :scheduled, :address, :total, :notes, :rec_type, :rec_end)'
        );
        $stmt->execute([
            'cid'      => $data['customer_id'],
            'pid'      => $data['provider_id'],
            'cat'      => $data['category_id'],
            'status'   => $data['status'] ?? 'requested',
            'scheduled'=> $data['scheduled_at'],
            'address'  => $data['address'],
            'total'    => $data['total'] ?? null,
            'notes'    => $data['notes'] ?? null,
            'rec_type' => $data['recurrence_type'] ?? 'none',
            'rec_end'  => $data['recurrence_end_date'] ?? null,
        ]);
        return $this->findEnriched((int) $pdo->lastInsertId()) ?? [];
    }

    public function updateStatus(int $id, string $status): ?array
    {
        $stmt = Connection::get()->prepare('UPDATE Job SET status = :status WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
        return $this->findEnriched($id);
    }

    public function delete(int $id): bool
    {
        $stmt = Connection::get()->prepare('DELETE FROM Job WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function userCanAccess(array $user, array $booking): bool
    {
        if ($user['role'] === 'admin') {
            return true;
        }
        if ($user['role'] === 'customer' && (int) $booking['customer_id'] === (int) $user['id']) {
            return true;
        }
        if ($user['role'] === 'provider') {
            $profile = $this->providers->findByUserId((int) $user['id']);
            return $profile && (int) $booking['provider_id'] === (int) $profile['id'];
        }
        return false;
    }

    /** @param array<string,mixed> $row */
    private function enrich(array $row): array
    {
        $customer = $this->users->findById((int) $row['customer_id']);
        $provider = $this->providers->getEnriched((int) $row['provider_id']);
        $category = $this->categories->find((int) $row['category_id']);

        return [
            'id'                   => (int) $row['id'],
            'customer_id'          => (int) $row['customer_id'],
            'provider_id'          => (int) $row['provider_id'],
            'category_id'          => (int) $row['category_id'],
            'status'               => $row['status'],
            'scheduled_at'         => str_replace(' ', 'T', $row['scheduled_at']),
            'address'              => $row['address'],
            'total'                => $row['total'] !== null ? (float) $row['total'] : null,
            'notes'                => $row['notes'],
            'recurrence_type'      => $row['recurrence_type'] ?? 'none',
            'recurrence_end_date'  => $row['recurrence_end_date'] ?? null,
            'customer'             => $customer,
            'provider'             => $provider,
            'category'             => $category,
        ];
    }
}