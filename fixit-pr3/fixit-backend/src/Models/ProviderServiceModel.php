<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

/** Per-service catalog rows for a provider (name, price, photo, etc.). */
final class ProviderServiceModel
{
    /** @return array<int,array> */
    public function listForProvider(int $providerId, int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = Connection::get()->prepare(
            "SELECT * FROM ProviderService WHERE provider_id = :pid
             ORDER BY sort_order ASC, id ASC LIMIT {$limit}"
        );
        $stmt->execute(['pid' => $providerId]);
        return array_map([$this, 'map'], $stmt->fetchAll());
    }

    public function find(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM ProviderService WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->map($row) : null;
    }

    /** Owner user id for a service (for authorization checks). */
    public function ownerUserId(int $serviceId): ?int
    {
        $stmt = Connection::get()->prepare(
            'SELECT pp.user_id FROM ProviderService ps
             JOIN ProviderProfile pp ON pp.id = ps.provider_id
             WHERE ps.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $serviceId]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (int) $v;
    }

    public function create(int $providerId, array $d): array
    {
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO ProviderService
             (provider_id, name, price, description, image_url, sku, is_active, sort_order)
             VALUES (:pid, :name, :price, :desc, :img, :sku, :active, :sort)'
        )->execute([
            'pid' => $providerId,
            'name' => $d['name'],
            'price' => $d['price'],
            'desc' => $d['description'],
            'img' => $d['image_url'],
            'sku' => $d['sku'],
            'active' => $d['is_active'] ? 1 : 0,
            'sort' => $d['sort_order'] ?? 0,
        ]);
        return $this->find((int) $pdo->lastInsertId());
    }

    public function update(int $id, array $d): ?array
    {
        Connection::get()->prepare(
            'UPDATE ProviderService SET
             name = :name, price = :price, description = :desc, image_url = :img,
             sku = :sku, is_active = :active, sort_order = :sort
             WHERE id = :id'
        )->execute([
            'id' => $id,
            'name' => $d['name'],
            'price' => $d['price'],
            'desc' => $d['description'],
            'img' => $d['image_url'],
            'sku' => $d['sku'],
            'active' => $d['is_active'] ? 1 : 0,
            'sort' => $d['sort_order'] ?? 0,
        ]);
        return $this->find($id);
    }

    public function delete(int $id): void
    {
        Connection::get()->prepare('DELETE FROM ProviderService WHERE id = :id')->execute(['id' => $id]);
    }

    private function map(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'provider_id' => (int) $r['provider_id'],
            'name' => $r['name'],
            'price' => (float) $r['price'],
            'description' => $r['description'],
            'image_url' => $r['image_url'],
            'sku' => $r['sku'],
            'is_active' => (bool) $r['is_active'],
            'sort_order' => (int) $r['sort_order'],
        ];
    }
}
