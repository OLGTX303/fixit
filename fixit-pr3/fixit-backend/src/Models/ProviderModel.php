<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;
use PDO;

final class ProviderModel
{
    public function findRaw(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM ProviderProfile WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM ProviderProfile WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array{pending:int,approved:int} for the admin verify dashboard */
    public function verificationCounts(): array
    {
        $out = ['pending' => 0, 'approved' => 0];
        foreach (Connection::get()->query('SELECT is_verified, COUNT(*) c FROM ProviderProfile GROUP BY is_verified') as $r) {
            if ((int) $r['is_verified'] === 1) $out['approved'] = (int) $r['c'];
            else $out['pending'] = (int) $r['c'];
        }
        return $out;
    }

    /** @return array<int,int> category_id => verified-provider count */
    public function categoryCounts(): array
    {
        $rows = Connection::get()->query(
            'SELECT pc.category_id, COUNT(*) c
             FROM ProviderCategory pc
             JOIN ProviderProfile pp ON pp.id = pc.provider_id
             WHERE pp.is_verified = 1
             GROUP BY pc.category_id'
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) $out[(int) $r['category_id']] = (int) $r['c'];
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function listEnriched(bool $verifiedOnly = true, array $filters = [], int $limit = 0, string $sort = ''): array
    {
        $sql = 'SELECT p.*, u.name, u.email, u.phone, u.avatar_url
                FROM ProviderProfile p
                JOIN User u ON u.id = p.user_id
                WHERE 1=1';
        $params = [];

        if ($verifiedOnly) {
            $sql .= ' AND p.is_verified = 1';
        } elseif (isset($filters['verified'])) {
            $sql .= ' AND p.is_verified = ' . ((int) $filters['verified'] === 1 ? '1' : '0');
        }

        if (!empty($filters['category'])) {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM ProviderCategory pc
                WHERE pc.provider_id = p.id AND pc.category_id = :category
            )';
            $params['category'] = (int) $filters['category'];
        }

        if (!empty($filters['priority'])) {
            $sql .= ' AND p.is_priority = 1';
        }

        if (!empty($filters['q'])) {
            // distinct placeholders — native PDO prepares can't reuse one name
            $sql .= ' AND (u.name LIKE :qa OR p.location LIKE :qb OR EXISTS (
                SELECT 1 FROM ProviderCategory pc2
                JOIN ServiceCategory sc ON sc.id = pc2.category_id
                WHERE pc2.provider_id = p.id AND sc.name LIKE :qc))';
            $like = '%' . $filters['q'] . '%';
            $params['qa'] = $like; $params['qb'] = $like; $params['qc'] = $like;
        }

        if ($sort === 'distance' && isset($filters['lat'], $filters['lng'])) {
            // squared-euclidean nearest (good enough to rank by proximity)
            $sql .= ' ORDER BY (POW(p.latitude - :lat, 2) + POW(p.longitude - :lng, 2)) ASC, p.id';
            $params['lat'] = (float) $filters['lat'];
            $params['lng'] = (float) $filters['lng'];
        } elseif ($sort === 'rating') {
            $sql .= ' ORDER BY p.avg_rating DESC, p.id';
        } elseif ($sort === 'price') {
            $sql .= ' ORDER BY p.base_rate ASC, p.id';
        } elseif ($sort === 'recommended') {
            $sql .= ' ORDER BY p.is_priority DESC, p.avg_rating DESC, p.id DESC';
        } else {
            $sql .= ' ORDER BY p.id';
        }

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;                       // already an int, safe to inline
            $off = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
            if ($off > 0) $sql .= ' OFFSET ' . $off;
        }
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $categoryModel = new CategoryModel();
        $categories = $categoryModel->all();
        $catById = [];
        foreach ($categories as $c) {
            $catById[(int) $c['id']] = $c;
        }

        return array_map(fn ($row) => $this->enrichRow($row, $catById), $rows);
    }

    public function getEnriched(int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT p.*, u.name, u.email, u.phone, u.avatar_url
             FROM ProviderProfile p
             JOIN User u ON u.id = p.user_id
             WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $categoryModel = new CategoryModel();
        $categories = $categoryModel->all();
        $catById = [];
        foreach ($categories as $c) {
            $catById[(int) $c['id']] = $c;
        }
        return $this->enrichRow($row, $catById);
    }

    /** @param array<string,mixed> $row */
    private function enrichRow(array $row, array $catById): array
    {
        $categoryIds = $this->categoryIdsForProvider((int) $row['id']);
        $cats = array_values(array_filter(array_map(fn ($id) => $catById[$id] ?? null, $categoryIds)));
        $services = json_decode((string) ($row['services_json'] ?? '[]'), true);
        if (!is_array($services)) {
            $services = [];
        }

        return [
            'id'           => (int) $row['id'],
            'user_id'      => (int) $row['user_id'],
            'bio'          => $row['bio'],
            'location'     => $row['location'],
            'base_rate'    => (float) $row['base_rate'],
            'rate_type'    => $row['rate_type']    ?? 'hourly',
            'per_job_rate' => isset($row['per_job_rate']) ? (float) $row['per_job_rate'] : null,
            'is_priority'  => (bool) ($row['is_priority'] ?? false),
            'is_verified'  => (bool) $row['is_verified'],
            'kyc_doc_url'  => $row['kyc_doc_url'],
            'kyc_status' => $row['kyc_status'] ?? 'none',
            'kyc_id_type' => $row['kyc_id_type'] ?? null,
            'kyc_id_confidence' => isset($row['kyc_id_confidence']) ? (float) $row['kyc_id_confidence'] : null,
            'kyc_id_checks' => $this->decodeJson($row['kyc_id_checks'] ?? null),
            'kyc_liveness_passed' => (bool) ($row['kyc_liveness_passed'] ?? false),
            'kyc_liveness_score' => isset($row['kyc_liveness_score']) ? (float) $row['kyc_liveness_score'] : null,
            'kyc_color_sequence_hash' => $row['kyc_color_sequence_hash'] ?? null,
            'kyc_liveness_checks' => $this->decodeJson($row['kyc_liveness_checks'] ?? null),
            'kyc_submitted_at' => $row['kyc_submitted_at'] ?? null,
            'avg_rating' => (float) $row['avg_rating'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'category_ids' => $categoryIds,
            'services' => $services,
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'categories' => $cats,
            'category_names' => array_map(fn ($c) => $c['name'], $cats),
            'review_count' => $this->reviewCountForProvider((int) $row['id']),
            'cover_url'    => $row['cover_url'] ?? null,
            'avatar_url'   => $row['avatar_url'] ?? null,
        ];
    }

    /** @return list<int> */
    private function categoryIdsForProvider(int $providerId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT category_id FROM ProviderCategory WHERE provider_id = :pid ORDER BY category_id'
        );
        $stmt->execute(['pid' => $providerId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function reviewCountForProvider(int $providerId): int
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM Review r
             JOIN Job j ON j.id = r.job_id
             WHERE j.provider_id = :pid'
        );
        $stmt->execute(['pid' => $providerId]);
        return (int) $stmt->fetchColumn();
    }

    public function create(int $userId, array $data): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO ProviderProfile
             (user_id, bio, location, base_rate, is_verified, kyc_doc_url, avg_rating, latitude, longitude, services_json)
             VALUES (:uid, :bio, :location, :rate, 0, NULL, 0, :lat, :lng, :services)'
        );
        $stmt->execute([
            'uid' => $userId,
            'bio' => $data['bio'],
            'location' => $data['location'],
            'rate' => $data['base_rate'],
            'lat' => $data['latitude'],
            'lng' => $data['longitude'],
            'services' => json_encode($data['services'] ?? []),
        ]);
        $id = (int) $pdo->lastInsertId();
        $this->syncCategories($id, $data['category_ids'] ?? []);
        return $this->getEnriched($id) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        // Lazy-add cover_url column if not yet present
        try {
            Connection::get()->exec("ALTER TABLE ProviderProfile ADD COLUMN cover_url VARCHAR(512) NULL");
        } catch (\Throwable) {}

        $stmt = Connection::get()->prepare(
            'UPDATE ProviderProfile SET
             bio = :bio, location = :location, base_rate = :rate,
             rate_type = :rate_type, per_job_rate = :per_job_rate,
             latitude = :lat, longitude = :lng, services_json = :services,
             cover_url = :cover_url
             WHERE id = :id'
        );
        $stmt->execute([
            'id'           => $id,
            'bio'          => $data['bio'],
            'location'     => $data['location'],
            'rate'         => $data['base_rate'],
            'rate_type'    => in_array($data['rate_type'] ?? 'hourly', ['hourly','per_job'], true)
                                  ? ($data['rate_type'] ?? 'hourly') : 'hourly',
            'per_job_rate' => isset($data['per_job_rate']) ? (float) $data['per_job_rate'] : null,
            'lat'          => $data['latitude'],
            'lng'          => $data['longitude'],
            'services'     => json_encode($data['services'] ?? []),
            'cover_url'    => $data['cover_url'] ?? null,
        ]);
        if (!empty($data['category_ids'])) {
            $this->syncCategories($id, $data['category_ids']);
        }
        return $this->getEnriched($id);
    }

    public function setPriority(int $id, bool $isPriority): ?array
    {
        $stmt = Connection::get()->prepare('UPDATE ProviderProfile SET is_priority = :p WHERE id = :id');
        $stmt->execute(['id' => $id, 'p' => $isPriority ? 1 : 0]);
        return $this->getEnriched($id);
    }

    public function setVerification(int $id, bool $verified): ?array
    {
        $stmt = Connection::get()->prepare('UPDATE ProviderProfile SET is_verified = :v WHERE id = :id');
        $stmt->execute(['id' => $id, 'v' => $verified ? 1 : 0]);
        return $this->getEnriched($id);
    }

    /** @param array<string,mixed> $data */
    public function saveIdRecognition(int $id, array $data): ?array
    {
        $status = $data['valid'] ? 'id_passed' : 'failed';
        $checks = $data['checks'];
        if (!empty($data['image_hash'])) {
            $checks['image_hash'] = $data['image_hash'];
        }
        if (!empty($data['extracted_preview'])) {
            $checks['extracted_preview'] = $data['extracted_preview'];
        }
        if (isset($data['fraud_score'])) {
            $checks['fraud_score'] = (float) $data['fraud_score'];
        }
        if (isset($data['ocr_confidence'])) {
            $checks['ocr_confidence'] = (float) $data['ocr_confidence'];
        }
        if (!empty($data['rejection_reasons']) && is_array($data['rejection_reasons'])) {
            $checks['rejection_reasons'] = $data['rejection_reasons'];
        }
        if (!empty($data['module_version'])) {
            $checks['module_version'] = (string) $data['module_version'];
        }

        $stmt = Connection::get()->prepare(
            'UPDATE ProviderProfile SET
             kyc_doc_url = :url,
             kyc_status = :status,
             kyc_id_type = :type,
             kyc_id_confidence = :confidence,
             kyc_id_checks = :checks,
             kyc_liveness_passed = 0,
             kyc_liveness_score = NULL,
             kyc_color_sequence_hash = NULL,
             kyc_liveness_checks = NULL,
             kyc_submitted_at = NULL
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'url' => $data['doc_url'],
            'status' => $status,
            'type' => $data['document_type'],
            'confidence' => $data['confidence'],
            'checks' => json_encode($checks),
        ]);

        return $this->getEnriched($id);
    }

    /** @param array<string,mixed> $data */
    public function saveLivenessCheck(int $id, array $data): ?array
    {
        $status = $data['passed'] ? 'submitted' : 'failed';
        $submittedAt = $data['passed'] ? date('Y-m-d H:i:s') : null;

        $stmt = Connection::get()->prepare(
            'UPDATE ProviderProfile SET
             kyc_status = :status,
             kyc_liveness_passed = :passed,
             kyc_liveness_score = :score,
             kyc_color_sequence_hash = :hash,
             kyc_liveness_checks = :checks,
             kyc_submitted_at = :submitted
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'passed' => $data['passed'] ? 1 : 0,
            'score' => $data['score'],
            'hash' => $data['color_sequence_hash'],
            'checks' => json_encode($data['checks']),
            'submitted' => $submittedAt,
        ]);

        return $this->getEnriched($id);
    }

    private function decodeJson(?string $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function delete(int $id): bool
    {
        $stmt = Connection::get()->prepare('DELETE FROM ProviderProfile WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** @param list<int> $categoryIds */
    private function syncCategories(int $providerId, array $categoryIds): void
    {
        $pdo = Connection::get();
        $del = $pdo->prepare('DELETE FROM ProviderCategory WHERE provider_id = :pid');
        $del->execute(['pid' => $providerId]);
        $ins = $pdo->prepare('INSERT INTO ProviderCategory (provider_id, category_id) VALUES (:pid, :cid)');
        foreach ($categoryIds as $cid) {
            $ins->execute(['pid' => $providerId, 'cid' => (int) $cid]);
        }
    }

}