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

    /** @return list<array<string,mixed>> */
    public function listEnriched(bool $verifiedOnly = true, array $filters = []): array
    {
        $sql = 'SELECT p.*, u.name, u.email, u.phone
                FROM ProviderProfile p
                JOIN User u ON u.id = p.user_id
                WHERE 1=1';
        $params = [];

        if ($verifiedOnly) {
            $sql .= ' AND p.is_verified = 1';
        }

        if (!empty($filters['category'])) {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM ProviderCategory pc
                WHERE pc.provider_id = p.id AND pc.category_id = :category
            )';
            $params['category'] = (int) $filters['category'];
        }

        $sql .= ' ORDER BY p.id';
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
            'SELECT p.*, u.name, u.email, u.phone
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
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'bio' => $row['bio'],
            'location' => $row['location'],
            'base_rate' => (float) $row['base_rate'],
            'is_verified' => (bool) $row['is_verified'],
            'kyc_doc_url' => $row['kyc_doc_url'],
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
        $stmt = Connection::get()->prepare(
            'UPDATE ProviderProfile SET
             bio = :bio, location = :location, base_rate = :rate,
             latitude = :lat, longitude = :lng, services_json = :services
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'bio' => $data['bio'],
            'location' => $data['location'],
            'rate' => $data['base_rate'],
            'lat' => $data['latitude'],
            'lng' => $data['longitude'],
            'services' => json_encode($data['services'] ?? []),
        ]);
        if (!empty($data['category_ids'])) {
            $this->syncCategories($id, $data['category_ids']);
        }
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