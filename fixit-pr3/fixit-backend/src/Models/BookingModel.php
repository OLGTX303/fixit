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
    public function listForUser(
        array $user,
        int $limit = 0,
        int $offset = 0,
        ?string $statusFilter = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array
    {
        $sql = "SELECT j.id, j.customer_id, j.provider_id, j.category_id, j.status,
                       j.scheduled_at, j.address, j.total, j.coupon_id, j.discount_amount, j.notes,
                       j.recurrence_type, j.recurrence_end_date,
                       cu.id AS cu_id, cu.name AS cu_name, cu.email AS cu_email, cu.role AS cu_role,
                       cu.phone AS cu_phone, cu.avatar_url AS cu_avatar_url,
                       COALESCE(cu.is_blocked, 0) AS cu_is_blocked,
                       sc.id AS sc_id, sc.name AS sc_name, sc.description AS sc_description, sc.icon_url AS sc_icon_url,
                       pp.id AS pp_id, pp.user_id AS pp_user_id, pp.bio AS pp_bio, pp.location AS pp_location,
                       pp.base_rate AS pp_base_rate, pp.rate_type AS pp_rate_type, pp.per_job_rate AS pp_per_job_rate,
                       pp.is_priority AS pp_is_priority, pp.is_verified AS pp_is_verified, pp.kyc_doc_url AS pp_kyc_doc_url,
                       pp.kyc_status AS pp_kyc_status, pp.kyc_id_type AS pp_kyc_id_type,
                       pp.kyc_id_confidence AS pp_kyc_id_confidence, pp.kyc_id_checks AS pp_kyc_id_checks,
                       pp.kyc_liveness_passed AS pp_kyc_liveness_passed, pp.kyc_liveness_score AS pp_kyc_liveness_score,
                       pp.kyc_color_sequence_hash AS pp_kyc_color_sequence_hash,
                       pp.kyc_liveness_checks AS pp_kyc_liveness_checks, pp.kyc_submitted_at AS pp_kyc_submitted_at,
                       pp.avg_rating AS pp_avg_rating, pp.latitude AS pp_latitude, pp.longitude AS pp_longitude,
                       pp.services_json AS pp_services_json, pp.cover_url AS pp_cover_url,
                       pu.name AS pp_name, pu.email AS pp_email, pu.phone AS pp_phone, pu.avatar_url AS pp_avatar_url,
                       (SELECT GROUP_CONCAT(pc.category_id ORDER BY pc.category_id)
                        FROM ProviderCategory pc WHERE pc.provider_id = pp.id) AS pp_category_ids_csv,
                       (SELECT COUNT(*) FROM Review r
                        INNER JOIN Job j2 ON j2.id = r.job_id WHERE j2.provider_id = pp.id) AS pp_review_count
                FROM Job j
                INNER JOIN User cu ON cu.id = j.customer_id
                INNER JOIN ProviderProfile pp ON pp.id = j.provider_id
                INNER JOIN User pu ON pu.id = pp.user_id
                INNER JOIN ServiceCategory sc ON sc.id = j.category_id
                WHERE j.status != 'inquiry'";
        $params = [];

        if ($user['role'] === 'customer') {
            $sql .= ' AND j.customer_id = :uid';
            $params['uid'] = $user['id'];
        } elseif ($user['role'] === 'provider') {
            $profile = $this->providers->findByUserId((int) $user['id']);
            if (!$profile) {
                return [];
            }
            $sql .= ' AND j.provider_id = :pid';
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
                $sql .= ' AND j.status IN (' . implode(',', $placeholders) . ')';
            }
        }

        if ($fromDate !== null && $fromDate !== '') {
            $sql .= ' AND j.scheduled_at >= :from_date';
            $params['from_date'] = $fromDate . ' 00:00:00';
        }
        if ($toDate !== null && $toDate !== '') {
            $sql .= ' AND j.scheduled_at <= :to_date';
            $params['to_date'] = $toDate . ' 23:59:59';
        }

        $sql .= ' ORDER BY j.scheduled_at DESC, j.id DESC';
        $limit = max(1, min(50, $limit > 0 ? $limit : 50));
        $offset = max(0, $offset);
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $jobs = array_map(fn ($row) => $this->mapJoinedRow($row, $this->categories->byId()), $rows);
        return $this->attachMessageSummaries($jobs);
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
             (customer_id, provider_id, category_id, status, scheduled_at, address, total,
              coupon_id, discount_amount, notes, recurrence_type, recurrence_end_date)
             VALUES (:cid, :pid, :cat, :status, :scheduled, :address, :total,
                     :coupon_id, :discount, :notes, :rec_type, :rec_end)'
        );
        $stmt->execute([
            'cid'      => $data['customer_id'],
            'pid'      => $data['provider_id'],
            'cat'      => $data['category_id'],
            'status'   => $data['status'] ?? 'requested',
            'scheduled'=> $data['scheduled_at'],
            'address'  => $data['address'],
            'total'    => $data['total'] ?? null,
            'coupon_id'=> $data['coupon_id'] ?? null,
            'discount' => $data['discount_amount'] ?? null,
            'notes'    => $data['notes'] ?? null,
            'rec_type' => $data['recurrence_type'] ?? 'none',
            'rec_end'  => $data['recurrence_end_date'] ?? null,
        ]);
        return $this->findEnriched((int) $pdo->lastInsertId()) ?? [];
    }

    public function updateStatus(int $id, string $status, ?string $expectedCurrent = null): ?array
    {
        // Stamp the per-status timestamp the first time a booking reaches it.
        // COALESCE keeps the original time if the status is re-applied. The
        // column name comes from this fixed whitelist, so it is safe to inline.
        $stampCols = [
            'accepted'    => 'accepted_at',
            'in_progress' => 'in_progress_at',
            'completed'   => 'completed_at',
            'cancelled'   => 'cancelled_at',
        ];
        $sql = 'UPDATE Job SET status = :status';
        if (isset($stampCols[$status])) {
            $col = $stampCols[$status];
            $sql .= ", {$col} = COALESCE({$col}, NOW())";
        }
        $sql .= ' WHERE id = :id';
        $params = ['id' => $id, 'status' => $status];
        if ($expectedCurrent !== null) {
            $sql .= ' AND status = :expected';
            $params['expected'] = $expectedCurrent;
        }
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            return null;
        }
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
    private function mapJoinedRow(array $row, array $catById): array
    {
        $customer = self::publicCustomerFields($row);

        $category = [
            'id' => (int) $row['sc_id'],
            'name' => $row['sc_name'],
            'description' => $row['sc_description'],
            'icon_url' => $row['sc_icon_url'],
        ];

        $providerRow = [
            'id' => (int) $row['pp_id'],
            'user_id' => (int) $row['pp_user_id'],
            'bio' => $row['pp_bio'],
            'location' => $row['pp_location'],
            'base_rate' => $row['pp_base_rate'],
            'rate_type' => $row['pp_rate_type'],
            'per_job_rate' => $row['pp_per_job_rate'],
            'is_priority' => $row['pp_is_priority'],
            'is_verified' => $row['pp_is_verified'],
            'kyc_doc_url' => $row['pp_kyc_doc_url'],
            'kyc_status' => $row['pp_kyc_status'],
            'kyc_id_type' => $row['pp_kyc_id_type'],
            'kyc_id_confidence' => $row['pp_kyc_id_confidence'],
            'kyc_id_checks' => $row['pp_kyc_id_checks'],
            'kyc_liveness_passed' => $row['pp_kyc_liveness_passed'],
            'kyc_liveness_score' => $row['pp_kyc_liveness_score'],
            'kyc_color_sequence_hash' => $row['pp_kyc_color_sequence_hash'],
            'kyc_liveness_checks' => $row['pp_kyc_liveness_checks'],
            'kyc_submitted_at' => $row['pp_kyc_submitted_at'],
            'avg_rating' => $row['pp_avg_rating'],
            'latitude' => $row['pp_latitude'],
            'longitude' => $row['pp_longitude'],
            'services_json' => $row['pp_services_json'],
            'cover_url' => $row['pp_cover_url'],
            'name' => $row['pp_name'],
            'email' => $row['pp_email'],
            'phone' => $row['pp_phone'],
            'avatar_url' => $row['pp_avatar_url'],
            'category_ids_csv' => $row['pp_category_ids_csv'],
            'review_count' => $row['pp_review_count'],
        ];

        return $this->jobPayload($row, $customer, $this->providers->enrichFromJoinRow($providerRow, $catById), $category);
    }

    /** Strip Stripe/payment PII from customer objects embedded in booking responses. */
    public static function sanitizeCustomer(?array $user): ?array
    {
        if (!$user) {
            return null;
        }
        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'phone' => $user['phone'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
            'is_blocked' => (int) ($user['is_blocked'] ?? 0),
        ];
    }

    /** @param array<string,mixed> $row */
    private static function publicCustomerFields(array $row): array
    {
        return self::sanitizeCustomer([
            'id' => $row['cu_id'],
            'name' => $row['cu_name'],
            'email' => $row['cu_email'],
            'role' => $row['cu_role'],
            'phone' => $row['cu_phone'],
            'avatar_url' => $row['cu_avatar_url'],
            'is_blocked' => $row['cu_is_blocked'],
        ]) ?? [];
    }

    /** @param array<string,mixed> $row */
    private function enrich(array $row): array
    {
        $customer = self::sanitizeCustomer($this->users->findById((int) $row['customer_id']));
        $provider = $this->providers->getEnriched((int) $row['provider_id']);
        $category = $this->categories->find((int) $row['category_id']);

        return $this->jobPayload($row, $customer, $provider, $category);
    }

    /** @param array<string,mixed> $row */
    private function jobPayload(array $row, ?array $customer, ?array $provider, ?array $category): array
    {
        return [
            'id'                  => (int) $row['id'],
            'customer_id'         => (int) $row['customer_id'],
            'provider_id'         => (int) $row['provider_id'],
            'category_id'         => (int) $row['category_id'],
            'status'              => $row['status'],
            'scheduled_at'        => str_replace(' ', 'T', $row['scheduled_at']),
            'address'             => $row['address'],
            'total'               => $row['total'] !== null ? (float) $row['total'] : null,
            'coupon_id'           => isset($row['coupon_id']) && $row['coupon_id'] !== null
                ? (int) $row['coupon_id'] : null,
            'discount_amount'     => isset($row['discount_amount']) && $row['discount_amount'] !== null
                ? (float) $row['discount_amount'] : null,
            'notes'               => $row['notes'],
            'recurrence_type'     => $row['recurrence_type'] ?? 'none',
            'recurrence_end_date' => $row['recurrence_end_date'] ?? null,
            // Order-history timestamps (null when the column isn't selected,
            // e.g. the list query, or the status hasn't been reached yet).
            'created_at'          => self::ts($row, 'created_at'),
            'accepted_at'         => self::ts($row, 'accepted_at'),
            'in_progress_at'      => self::ts($row, 'in_progress_at'),
            'completed_at'        => self::ts($row, 'completed_at'),
            'cancelled_at'        => self::ts($row, 'cancelled_at'),
            'customer'            => $customer,
            'provider'            => $provider,
            'category'            => $category,
        ];
    }

    /** @param list<array<string,mixed>> $jobs @return list<array<string,mixed>> */
    private function attachMessageSummaries(array $jobs): array
    {
        $latestByJob = (new MessageModel())->latestForJobs(array_column($jobs, 'id'));
        foreach ($jobs as &$job) {
            $latest = $latestByJob[(int) $job['id']] ?? null;
            $job['latest_message'] = $latest ? [
                'id' => $latest['id'],
                'job_id' => $latest['job_id'],
                'sender_id' => $latest['sender_id'],
                'body' => $latest['is_encrypted'] ? null : $latest['body'],
                'is_encrypted' => $latest['is_encrypted'],
                'is_system' => $latest['is_system'],
                'sent_at' => $latest['sent_at'],
            ] : null;
        }
        unset($job);
        return $jobs;
    }

    /** Format a nullable DATETIME row field as an ISO-ish 'YYYY-MM-DDTHH:MM:SS' string. */
    private static function ts(array $row, string $key): ?string
    {
        return isset($row[$key]) && $row[$key] !== null
            ? str_replace(' ', 'T', (string) $row[$key])
            : null;
    }
}
