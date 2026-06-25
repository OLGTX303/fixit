<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;
use PDO;
use PDOException;

final class CouponModel
{
    /** @return array{valid:bool,discount_amount:float,final_total:float,message:string,coupon_id?:int,coupon?:array} */
    public function validate(string $code, int $providerId, float $subtotal, ?int $userId = null): array
    {
        $coupon = $this->findByCode($code);
        if (!$coupon) {
            return $this->invalid('Coupon not found');
        }

        $now = date('Y-m-d H:i:s');
        if (!(bool) $coupon['is_active']) {
            return $this->invalid('Coupon is not active');
        }
        if ($coupon['starts_at'] > $now) {
            return $this->invalid('Coupon is not yet valid');
        }
        if ($coupon['expires_at'] < $now) {
            return $this->invalid('Coupon has expired');
        }

        if ($coupon['scope'] === 'provider') {
            if ($coupon['provider_id'] === null || (int) $coupon['provider_id'] !== $providerId) {
                return $this->invalid('Coupon is not valid for this provider');
            }
        }

        if ($subtotal < (float) $coupon['min_spend']) {
            return $this->invalid('Order does not meet minimum spend');
        }

        if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
            return $this->invalid('Coupon usage limit reached');
        }

        if ($userId !== null) {
            $userUses = $this->countUserRedemptions((int) $coupon['id'], $userId);
            if ($userUses >= (int) $coupon['per_user_limit']) {
                return $this->invalid('You have already used this coupon');
            }
        }

        $discount = $this->computeDiscount($coupon, $subtotal);
        $final = max(0.0, round($subtotal - $discount, 2));

        return [
            'valid' => true,
            'discount_amount' => $discount,
            'final_total' => $final,
            'message' => 'Coupon applied',
            'coupon_id' => (int) $coupon['id'],
            'coupon' => $this->format($coupon),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function listAvailable(int $providerId): array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Connection::get()->prepare(
            "SELECT * FROM Coupon
             WHERE is_active = 1
               AND starts_at <= :now AND expires_at >= :now
               AND (usage_limit IS NULL OR used_count < usage_limit)
               AND (scope = 'system' OR (scope = 'provider' AND provider_id = :pid))
             ORDER BY scope ASC, discount_value DESC, id DESC"
        );
        $stmt->execute(['now' => $now, 'pid' => $providerId]);
        return array_map(fn ($row) => $this->format($row), $stmt->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function listForProvider(int $providerId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT * FROM Coupon WHERE scope = 'provider' AND provider_id = :pid ORDER BY created_at DESC, id DESC"
        );
        $stmt->execute(['pid' => $providerId]);
        return array_map(fn ($row) => $this->format($row), $stmt->fetchAll());
    }

    /** @return array{items:list<array<string,mixed>>,total:int} */
    public function listAdmin(int $limit, int $offset): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $pdo = Connection::get();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM Coupon')->fetchColumn();
        $stmt = $pdo->prepare(
            "SELECT * FROM Coupon ORDER BY created_at DESC, id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute();
        return [
            'items' => array_map(fn ($row) => $this->format($row), $stmt->fetchAll()),
            'total' => $total,
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM Coupon WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->format($row) : null;
    }

    public function findByCode(string $code): ?array
    {
        $code = $this->normalizeCode($code);
        $stmt = Connection::get()->prepare('SELECT * FROM Coupon WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO Coupon
             (code, scope, provider_id, discount_type, discount_value, min_spend, max_discount,
              usage_limit, per_user_limit, starts_at, expires_at, is_active, created_by)
             VALUES
             (:code, :scope, :pid, :dtype, :dval, :min_spend, :max_disc, :usage_limit,
              :per_user, :starts, :expires, :active, :created_by)'
        );
        $stmt->execute([
            'code' => $data['code'],
            'scope' => $data['scope'],
            'pid' => $data['provider_id'],
            'dtype' => $data['discount_type'],
            'dval' => $data['discount_value'],
            'min_spend' => $data['min_spend'],
            'max_disc' => $data['max_discount'],
            'usage_limit' => $data['usage_limit'],
            'per_user' => $data['per_user_limit'],
            'starts' => $data['starts_at'],
            'expires' => $data['expires_at'],
            'active' => $data['is_active'] ? 1 : 0,
            'created_by' => $data['created_by'],
        ]);
        return $this->find((int) $pdo->lastInsertId()) ?? [];
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): ?array
    {
        $stmt = Connection::get()->prepare(
            'UPDATE Coupon SET
               code = :code,
               discount_type = :dtype,
               discount_value = :dval,
               min_spend = :min_spend,
               max_discount = :max_disc,
               usage_limit = :usage_limit,
               per_user_limit = :per_user,
               starts_at = :starts,
               expires_at = :expires,
               is_active = :active
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'code' => $data['code'],
            'dtype' => $data['discount_type'],
            'dval' => $data['discount_value'],
            'min_spend' => $data['min_spend'],
            'max_disc' => $data['max_discount'],
            'usage_limit' => $data['usage_limit'],
            'per_user' => $data['per_user_limit'],
            'starts' => $data['starts_at'],
            'expires' => $data['expires_at'],
            'active' => $data['is_active'] ? 1 : 0,
        ]);
        return $stmt->rowCount() > 0 ? $this->find($id) : $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = Connection::get()->prepare('DELETE FROM Coupon WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Atomically redeem a coupon for a booking. Returns false on race/limit failure.
     */
    public function redeem(int $couponId, int $userId, int $bookingId, float $amountDiscounted): bool
    {
        $pdo = Connection::get();
        try {
            $pdo->beginTransaction();

            $lock = $pdo->prepare('SELECT * FROM Coupon WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $couponId]);
            $coupon = $lock->fetch();
            if (!$coupon) {
                $pdo->rollBack();
                return false;
            }

            if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
                $pdo->rollBack();
                return false;
            }

            $userUses = $this->countUserRedemptions($couponId, $userId, $pdo);
            if ($userUses >= (int) $coupon['per_user_limit']) {
                $pdo->rollBack();
                return false;
            }

            $ins = $pdo->prepare(
                'INSERT INTO CouponRedemption (coupon_id, user_id, booking_id, amount_discounted)
                 VALUES (:cid, :uid, :bid, :amt)'
            );
            $ins->execute([
                'cid' => $couponId,
                'uid' => $userId,
                'bid' => $bookingId,
                'amt' => $amountDiscounted,
            ]);

            $upd = $pdo->prepare(
                'UPDATE Coupon SET used_count = used_count + 1
                 WHERE id = :id AND is_active = 1
                   AND (usage_limit IS NULL OR used_count < usage_limit)'
            );
            $upd->execute(['id' => $couponId]);
            if ($upd->rowCount() === 0) {
                $pdo->rollBack();
                return false;
            }

            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Duplicate redemption (uq_coupon_booking) or other race — fail gracefully.
            return false;
        }
    }

    public function countUserRedemptions(int $couponId, int $userId, ?PDO $pdo = null): int
    {
        $pdo = $pdo ?? Connection::get();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM CouponRedemption WHERE coupon_id = :cid AND user_id = :uid'
        );
        $stmt->execute(['cid' => $couponId, 'uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
    }

    /** @param array<string,mixed> $coupon */
    private function computeDiscount(array $coupon, float $subtotal): float
    {
        $discount = 0.0;
        if ($coupon['discount_type'] === 'percent') {
            $discount = round($subtotal * ((float) $coupon['discount_value']) / 100, 2);
            if ($coupon['max_discount'] !== null) {
                $discount = min($discount, (float) $coupon['max_discount']);
            }
        } else {
            $discount = (float) $coupon['discount_value'];
        }
        return min($discount, $subtotal);
    }

    /** @return array{valid:bool,discount_amount:float,final_total:float,message:string} */
    private function invalid(string $message): array
    {
        return [
            'valid' => false,
            'discount_amount' => 0.0,
            'final_total' => 0.0,
            'message' => $message,
        ];
    }

    /** @param array<string,mixed> $row */
    private function format(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'scope' => $row['scope'],
            'provider_id' => $row['provider_id'] !== null ? (int) $row['provider_id'] : null,
            'discount_type' => $row['discount_type'],
            'discount_value' => (float) $row['discount_value'],
            'min_spend' => (float) $row['min_spend'],
            'max_discount' => $row['max_discount'] !== null ? (float) $row['max_discount'] : null,
            'usage_limit' => $row['usage_limit'] !== null ? (int) $row['usage_limit'] : null,
            'used_count' => (int) $row['used_count'],
            'per_user_limit' => (int) $row['per_user_limit'],
            'starts_at' => str_replace(' ', 'T', (string) $row['starts_at']),
            'expires_at' => str_replace(' ', 'T', (string) $row['expires_at']),
            'is_active' => (bool) $row['is_active'],
            'created_by' => (int) $row['created_by'],
            'created_at' => str_replace(' ', 'T', (string) $row['created_at']),
        ];
    }
}