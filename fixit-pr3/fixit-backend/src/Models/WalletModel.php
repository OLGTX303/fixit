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
    public function list(int $userId, int $limit = 50, ?string $fromDate = null, ?string $toDate = null): array
    {
        $limit = max(1, min(50, $limit));
        $where = "WHERE user_id = :uid";
        $params = ['uid' => $userId];
        if ($fromDate !== null && $fromDate !== '') {
            $where .= ' AND created_at >= :from_date';
            $params['from_date'] = $fromDate . ' 00:00:00';
        }
        if ($toDate !== null && $toDate !== '') {
            $where .= ' AND created_at <= :to_date';
            $params['to_date'] = $toDate . ' 23:59:59';
        }
        $stmt = Connection::get()->prepare(
            "SELECT id, kind, amount_cents, currency, stripe_ref, status, note, created_at
             FROM WalletTransaction
             {$where}
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit}"
        );
        $stmt->execute($params);
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

    /**
     * Credit a provider's wallet for a completed job. Idempotent: keyed on the
     * job id via stripe_ref ("job_<id>"), so re-completing never double-pays.
     * Returns true if a new payout row was written.
     */
    public function creditJobPayout(int $userId, int $cents, int $jobId, string $currency = 'myr'): bool
    {
        $ref = 'job_' . $jobId;
        try {
            $this->add($userId, 'payout', $cents, $currency, $ref, 'Job payout #' . $jobId . ' (after 15% fee)');
            return true;
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'uq_wallet_payout_job') || (int) ($e->errorInfo[1] ?? 0) === 1062) {
                return false;
            }
            throw $e;
        }
    }

    /** Reverse a prior job payout on cancel/refund. Idempotent. */
    public function clawBackJobPayout(int $userId, int $jobId, string $currency = 'myr'): bool
    {
        $ref = 'job_' . $jobId;
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            "SELECT amount_cents FROM WalletTransaction
             WHERE user_id = :uid AND kind = 'payout' AND stripe_ref = :ref AND status = 'settled'
             LIMIT 1"
        );
        $stmt->execute(['uid' => $userId, 'ref' => $ref]);
        $cents = $stmt->fetchColumn();
        if ($cents === false) {
            return false;
        }
        $clawRef = 'clawback_' . $jobId;
        $chk = $pdo->prepare(
            "SELECT 1 FROM WalletTransaction WHERE kind = 'adjustment' AND stripe_ref = :ref LIMIT 1"
        );
        $chk->execute(['ref' => $clawRef]);
        if ($chk->fetchColumn()) {
            return false;
        }
        $this->add($userId, 'adjustment', -(int) $cents, $currency, $clawRef, 'Payout clawback for cancelled job #' . $jobId);
        return true;
    }

    /** Refund a cancelled booking into the customer's wallet. Idempotent. */
    public function refundBookingPayment(
        int $userId,
        int $bookingId,
        ?int $amountCents = null,
        string $currency = 'myr'
    ): bool
    {
        $ref = 'booking_pay_' . $bookingId;
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            "SELECT amount_cents FROM WalletTransaction
             WHERE user_id = :uid AND kind = 'booking_pay' AND stripe_ref = :ref AND status = 'settled'
             LIMIT 1"
        );
        $stmt->execute(['uid' => $userId, 'ref' => $ref]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row && $amountCents === null) {
            return false;
        }
        $refundCents = $amountCents !== null ? abs($amountCents) : abs((int) $row['amount_cents']);
        if ($refundCents <= 0) {
            return false;
        }

        $refundRef = 'booking_refund_' . $bookingId;
        $chk = $pdo->prepare(
            "SELECT 1 FROM WalletTransaction
             WHERE user_id = :uid AND kind = 'booking_refund' AND stripe_ref = :ref LIMIT 1"
        );
        $chk->execute(['uid' => $userId, 'ref' => $refundRef]);
        if ($chk->fetchColumn()) {
            return true;
        }

        $this->add(
            $userId,
            'booking_refund',
            $refundCents,
            $currency,
            $refundRef,
            'Booking #' . $bookingId . ' refund (wallet)'
        );
        return true;
    }

    /** Lock wallet rows and return settled balance — use inside a transaction. */
    public function lockAndBalance(int $userId): int
    {
        $pdo = Connection::get();
        $pdo->prepare(
            'SELECT id FROM WalletTransaction WHERE user_id = :uid FOR UPDATE'
        )->execute(['uid' => $userId]);
        return $this->balanceCents($userId);
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
