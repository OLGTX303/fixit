<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

/**
 * Wallet = a ledger. Balance is the signed sum of settled rows, never stored
 * separately, so it can never drift from the transactions that produced it.
 */
final class WalletModel
{
    public function balanceCents(int $userId): int
    {
        $stmt = Connection::get()->prepare(
            "SELECT COALESCE(SUM(amount_cents), 0) AS bal
             FROM WalletTransaction
             WHERE user_id = :uid AND status = 'settled'"
        );
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int,array> newest first */
    public function list(int $userId, int $limit = 50): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT id, kind, amount_cents, currency, stripe_ref, status, note, created_at
             FROM WalletTransaction
             WHERE user_id = :uid
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** Settled top-up PaymentIntent ids, newest first — the refund sources for withdraw. */
    public function topupIntents(int $userId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT stripe_ref
             FROM WalletTransaction
             WHERE user_id = :uid AND kind = 'topup' AND status = 'settled'
               AND stripe_ref LIKE 'pi_%'
             ORDER BY created_at DESC, id DESC"
        );
        $stmt->execute(['uid' => $userId]);
        return array_column($stmt->fetchAll(), 'stripe_ref');
    }

    public function add(
        int $userId,
        string $kind,
        int $signedAmountCents,
        string $currency,
        ?string $stripeRef,
        string $note,
        string $status = 'settled'
    ): int {
        $pdo = Connection::get();
        $pdo->prepare(
            "INSERT INTO WalletTransaction
             (user_id, kind, amount_cents, currency, stripe_ref, status, note)
             VALUES (:uid, :kind, :amt, :cur, :ref, :status, :note)"
        )->execute([
            'uid' => $userId,
            'kind' => $kind,
            'amt' => $signedAmountCents,
            'cur' => $currency,
            'ref' => $stripeRef,
            'status' => $status,
            'note' => $note,
        ]);
        return (int) $pdo->lastInsertId();
    }
}
