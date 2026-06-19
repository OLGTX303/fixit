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

        if (isset($filters['minPrice'])) {
            $sql .= ' AND p.base_rate >= :minPrice';
            $params['minPrice'] = (float) $filters['minPrice'];
        }

        if (isset($filters['maxPrice'])) {
            $sql .= ' AND p.base_rate <= :maxPrice';
            $params['maxPrice'] = (float) $filters['maxPrice'];
        }

        if (isset($filters['minRating'])) {
            $sql .= ' AND p.avg_rating >= :minRating';
            $params['minRating'] = (float) $filters['minRating'];
        }

        $sql .= ' ORDER BY p.id';
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $lat = isset($filters['lat']) ? (float) $filters['lat'] : 51.5074;
        $lng = isset($filters['lng']) ? (float) $filters['lng'] : -0.1278;
        $maxDistance = isset($filters['maxDistance']) ? (float) $filters['maxDistance'] : null;

        $categoryModel = new CategoryModel();
        $categories = $categoryModel->all();
        $catById = [];
        foreach ($categories as $c) {
            $catById[(int) $c['id']] = $c;
        }

        $result = [];
        foreach ($rows as $row) {
            $enriched = $this->enrichRow($row, $catById);
            if ($maxDistance !== null) {
                $dist = $this->haversineKm($lat, $lng, (float) $row['latitude'], (float) $row['longitude']);
                if ($dist > $maxDistance) {
                    continue;
                }
                $enriched['_distance'] = round($dist, 2);
            }
            $result[] = $enriched;
        }

        return $result;
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

    public function listPending(): array
    {
        return $this->listEnriched(false, []);
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

    public function setKycUrl(int $id, string $url): ?array
    {
        $stmt = Connection::get()->prepare('UPDATE ProviderProfile SET kyc_doc_url = :url WHERE id = :id');
        $stmt->execute(['id' => $id, 'url' => $url]);
        return $this->getEnriched($id);
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

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}