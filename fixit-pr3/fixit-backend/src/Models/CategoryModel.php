<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class CategoryModel
{
    public function all(): array
    {
        $stmt = Connection::get()->query('SELECT id, name, description, icon_url FROM ServiceCategory ORDER BY id');
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function byId(): array
    {
        $out = [];
        foreach ($this->all() as $c) {
            $out[(int) $c['id']] = $c;
        }
        return $out;
    }

    public function find(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT id, name, description, icon_url FROM ServiceCategory WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}