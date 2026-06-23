<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class StripePaymentModel
{
    /** @param object $intent Stripe PaymentIntent object */
    /** Revenue stats for admin CRM — queries the real StripePayment table. */
    public function listStats(): array
    {
        $pdo = Connection::get();

        $monthly = $pdo->query(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m') AS month,
                SUM(CASE WHEN status = 'succeeded' THEN amount_cents ELSE 0 END) AS revenue_cents,
                COUNT(CASE WHEN status = 'succeeded' THEN 1 ELSE NULL END) AS paid_count
             FROM StripePayment
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $totals = $pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'succeeded' THEN amount_cents ELSE 0 END), 0) AS total_revenue_cents,
                COUNT(CASE WHEN status = 'succeeded' THEN 1 ELSE NULL END) AS total_paid,
                COUNT(CASE WHEN status = 'failed'    THEN 1 ELSE NULL END) AS total_failed,
                COUNT(*) AS total_transactions
             FROM StripePayment"
        )->fetch(\PDO::FETCH_ASSOC);

        return [
            'monthly'  => $monthly,
            'totals'   => $totals,
            'currency' => 'myr',
            'mode'     => 'sandbox',
        ];
    }

    /**
     * Succeeded PaymentIntent ids that may still hold a refundable balance,
     * newest first. Used as the refund source for a provider withdrawal — the
     * platform's real sandbox charges (currently customer wallet top-ups), since
     * provider earnings are ledger credits with no charge of their own.
     *
     * @return list<string>
     */
    public function refundableIntents(): array
    {
        $stmt = Connection::get()->query(
            "SELECT stripe_payment_intent_id
             FROM StripePayment
             WHERE status = 'succeeded' AND stripe_payment_intent_id LIKE 'pi_%'
             ORDER BY created_at DESC, id DESC"
        );
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'stripe_payment_intent_id');
    }

    public function upsertFromPaymentIntent(
        int $userId,
        object $intent,
        ?int $bookingId = null,
        ?string $failureMessage = null
    ): void {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO StripePayment
             (user_id, booking_id, stripe_payment_intent_id, amount_cents, currency, status, failure_message)
             VALUES (:uid, :bid, :pi, :amount, :currency, :status, :fail)
             ON DUPLICATE KEY UPDATE
             status = VALUES(status),
             failure_message = VALUES(failure_message),
             updated_at = NOW()'
        );
        $stmt->execute([
            'uid' => $userId,
            'bid' => $bookingId,
            'pi' => $intent->id,
            'amount' => (int) $intent->amount,
            'currency' => (string) $intent->currency,
            'status' => (string) $intent->status,
            'fail' => $failureMessage,
        ]);
    }

}