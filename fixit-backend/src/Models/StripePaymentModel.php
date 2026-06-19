<?php

declare(strict_types=1);

namespace FixIt\Models;

use FixIt\Database\Connection;

final class StripePaymentModel
{
    /** @param object $intent Stripe PaymentIntent object */
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