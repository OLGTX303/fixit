<?php

declare(strict_types=1);

namespace FixIt\Services;

use FixIt\Database\Connection;
use FixIt\Models\BookingModel;
use FixIt\Models\StripePaymentModel;
use FixIt\Models\UserModel;
use FixIt\Models\WalletModel;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe integration — TEST MODE ONLY in this build.
 * Never stores raw card data; only Stripe object IDs (cus_, pm_, pi_, seti_).
 */
final class StripeService
{
    private StripeClient $stripe;
    private UserModel $users;
    private StripePaymentModel $payments;
    private WalletModel $wallet;

    public function __construct()
    {
        $this->assertTestModeConfigured();
        $this->stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY']);
        $this->users = new UserModel();
        $this->payments = new StripePaymentModel();
        $this->wallet = new WalletModel();
    }

    public static function isConfigured(): bool
    {
        $secret = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        $publishable = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '';
        return str_starts_with($secret, 'sk_test_') && str_starts_with($publishable, 'pk_test_');
    }

    public function getPublishableKey(): string
    {
        return (string) ($_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '');
    }

    /** Create or return existing Stripe test Customer for user. */
    public function ensureCustomer(int $userId): array
    {
        $user = $this->users->findById($userId);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        if (!empty($user['stripe_test_customer_id'])) {
            return [
                'customer_id' => $user['stripe_test_customer_id'],
                'created' => false,
            ];
        }

        $customer = $this->stripe->customers->create([
            'email' => $user['email'],
            'name' => $user['name'],
            'metadata' => [
                'fixit_user_id' => (string) $userId,
                'stripe_mode' => 'test',
            ],
        ]);

        $this->users->saveStripeCustomerId($userId, $customer->id);

        return [
            'customer_id' => $customer->id,
            'created' => true,
        ];
    }

    /** SetupIntent for saving a card via Payment Element (no charge). */
    public function createSetupIntent(int $userId): array
    {
        $customer = $this->ensureCustomer($userId);

        $intent = $this->stripe->setupIntents->create([
            'customer' => $customer['customer_id'],
            'payment_method_types' => ['card'],
            'usage' => 'off_session',
            'metadata' => [
                'fixit_user_id' => (string) $userId,
                'stripe_mode' => 'test',
            ],
        ]);

        return [
            'client_secret' => $intent->client_secret,
            'setup_intent_id' => $intent->id,
            'customer_id' => $customer['customer_id'],
        ];
    }

    /**
     * Verify pm_ belongs to user's cus_, persist brand/last4, set default on Customer.
     */
    public function savePaymentMethod(int $userId, string $paymentMethodId): array
    {
        $user = $this->users->findById($userId);
        if (!$user || empty($user['stripe_test_customer_id'])) {
            throw new \RuntimeException('Stripe customer not found — create customer first');
        }

        $customerId = $user['stripe_test_customer_id'];
        $this->assertStripeId($paymentMethodId, 'pm_');

        $pm = $this->stripe->paymentMethods->retrieve($paymentMethodId);

        if ($pm->customer && $pm->customer !== $customerId) {
            throw new \RuntimeException('PaymentMethod does not belong to this customer');
        }

        if (!$pm->customer) {
            $this->stripe->paymentMethods->attach($paymentMethodId, ['customer' => $customerId]);
            $pm = $this->stripe->paymentMethods->retrieve($paymentMethodId);
        }

        if ($pm->customer !== $customerId) {
            throw new \RuntimeException('PaymentMethod ownership verification failed');
        }

        $this->stripe->customers->update($customerId, [
            'invoice_settings' => ['default_payment_method' => $paymentMethodId],
        ]);

        $card = $pm->card;
        $this->users->saveStripePaymentMethod($userId, [
            'payment_method_id' => $paymentMethodId,
            'last4' => $card->last4 ?? null,
            'brand' => $card->brand ?? null,
        ]);

        return $this->getSavedPaymentSummary($userId);
    }

    /** Pay with saved off-session PaymentMethod; handles 3DS via requires_action. */
    public function payWithSavedMethod(int $userId, int $amountCents, ?int $bookingId = null, string $currency = 'myr'): array
    {
        $user = $this->users->findById($userId);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $customerId = $user['stripe_test_customer_id'] ?? null;
        $pmId = $user['stripe_test_default_payment_method_id'] ?? null;

        if (!$customerId || !$pmId) {
            throw new \RuntimeException('No saved payment method');
        }

        if ($amountCents < 50) {
            throw new \RuntimeException('Amount must be at least 50 cents');
        }

        $currency = strtolower($currency);
        if ($currency !== 'myr') {
            throw new \RuntimeException('Only MYR payments are supported');
        }

        $pdo = Connection::get();
        $lockedBooking = false;
        if ($bookingId !== null) {
            $pdo->beginTransaction();
            try {
                $this->assertBookingPayable($userId, $bookingId, $amountCents, true);
                $lockedBooking = true;
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage());
            }
        }

        $this->verifyPaymentMethodOwnership($customerId, $pmId);

        $intent = $this->stripe->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'customer' => $customerId,
            'payment_method' => $pmId,
            'off_session' => true,
            'confirm' => true,
            'metadata' => array_filter([
                'fixit_user_id' => (string) $userId,
                'fixit_booking_id' => $bookingId ? (string) $bookingId : null,
                'stripe_mode' => 'test',
            ]),
        ]);

        try {
            $this->payments->upsertFromPaymentIntent($userId, $intent, $bookingId);
            if ($lockedBooking) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($lockedBooking) {
                $pdo->rollBack();
            }
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage());
        }

        $result = [
            'payment_intent_id' => $intent->id,
            'status' => $intent->status,
            'amount_cents' => $amountCents,
            'currency' => $currency,
        ];

        if ($intent->status === 'requires_action' && $intent->next_action) {
            $result['requires_action'] = true;
            $result['client_secret'] = $intent->client_secret;
        }

        if ($intent->status === 'succeeded') {
            $result['paid'] = true;
        }

        return $result;
    }

    /**
     * Pay a booking with wallet balance, saved card, or both (wallet first).
     * Amount is always taken from the booking total on the server.
     */
    public function payBooking(int $userId, int $bookingId, bool $useWallet = true): array
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT customer_id, status, total FROM Job WHERE id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $bookingId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('Booking not found');
            }
            if ((int) $row['customer_id'] !== $userId) {
                throw new \RuntimeException('Booking does not belong to this user');
            }

            $amountCents = (int) round(((float) ($row['total'] ?? 0)) * 100);
            if ($amountCents < 50) {
                throw new \RuntimeException('Booking total is too small to pay');
            }
            if ($this->payments->findSucceededByBooking($bookingId)) {
                throw new \RuntimeException('Booking has already been paid');
            }
            if (!in_array($row['status'], ['requested', 'accepted'], true)) {
                throw new \RuntimeException('Booking is not payable in its current status');
            }

            $walletApplied = 0;
            if ($useWallet) {
                $balance = $this->wallet->lockAndBalance($userId);
                $walletApplied = min(max(0, $balance), $amountCents);
            }
            $cardCents = $amountCents - $walletApplied;

            if ($cardCents > 0) {
                $user = $this->users->findById($userId);
                if (empty($user['stripe_test_default_payment_method_id'])) {
                    throw new \RuntimeException('Save a card to pay the remaining balance');
                }
            }

            $cardResult = null;
            if ($cardCents > 0) {
                $cardResult = $this->chargeSavedCardAmount(
                    $userId,
                    $cardCents,
                    $bookingId,
                    [
                        'fixit_wallet_cents' => (string) $walletApplied,
                        'fixit_booking_total_cents' => (string) $amountCents,
                    ]
                );
            }

            if ($cardResult !== null && ($cardResult['status'] ?? '') === 'requires_action') {
                $pdo->commit();
                return [
                    'payment_intent_id' => $cardResult['payment_intent_id'],
                    'status' => 'requires_action',
                    'requires_action' => true,
                    'client_secret' => $cardResult['client_secret'],
                    'amount_cents' => $amountCents,
                    'wallet_applied_cents' => $walletApplied,
                    'card_amount_cents' => $cardCents,
                    'currency' => 'myr',
                ];
            }

            if ($walletApplied > 0) {
                $this->debitWalletForBooking($userId, $bookingId, $walletApplied);
            }

            if ($cardCents > 0 && $cardResult !== null) {
                $this->payments->recordSucceededBookingPayment(
                    $userId,
                    $bookingId,
                    (string) $cardResult['payment_intent_id'],
                    $amountCents,
                    'myr'
                );
            } else {
                $this->payments->recordSucceededBookingPayment(
                    $userId,
                    $bookingId,
                    'wallet_booking_' . $bookingId,
                    $amountCents,
                    'myr'
                );
            }

            $pdo->commit();
            return [
                'paid' => true,
                'status' => 'succeeded',
                'amount_cents' => $amountCents,
                'wallet_applied_cents' => $walletApplied,
                'card_amount_cents' => $cardCents,
                'currency' => 'myr',
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage());
        }
    }

    /** @param array<string,string> $extraMetadata */
    private function chargeSavedCardAmount(
        int $userId,
        int $amountCents,
        int $bookingId,
        array $extraMetadata = []
    ): array {
        $user = $this->users->findById($userId);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $customerId = $user['stripe_test_customer_id'] ?? null;
        $pmId = $user['stripe_test_default_payment_method_id'] ?? null;
        if (!$customerId || !$pmId) {
            throw new \RuntimeException('No saved payment method');
        }
        if ($amountCents < 50) {
            throw new \RuntimeException('Card amount must be at least 50 cents');
        }

        $this->verifyPaymentMethodOwnership($customerId, $pmId);

        $intent = $this->stripe->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => 'myr',
            'customer' => $customerId,
            'payment_method' => $pmId,
            'off_session' => true,
            'confirm' => true,
            'metadata' => array_merge([
                'fixit_user_id' => (string) $userId,
                'fixit_booking_id' => (string) $bookingId,
                'stripe_mode' => 'test',
            ], $extraMetadata),
        ]);

        $result = [
            'payment_intent_id' => $intent->id,
            'status' => $intent->status,
            'amount_cents' => $amountCents,
            'currency' => 'myr',
        ];

        if ($intent->status === 'requires_action' && $intent->next_action) {
            $result['requires_action'] = true;
            $result['client_secret'] = $intent->client_secret;
        }

        if ($intent->status === 'succeeded') {
            $result['paid'] = true;
        }

        return $result;
    }

    private function debitWalletForBooking(int $userId, int $bookingId, int $walletCents): void
    {
        if ($walletCents <= 0) {
            return;
        }
        $ref = 'booking_pay_' . $bookingId;
        $pdo = Connection::get();
        $chk = $pdo->prepare(
            "SELECT 1 FROM WalletTransaction
             WHERE user_id = :uid AND kind = 'booking_pay' AND stripe_ref = :ref LIMIT 1"
        );
        $chk->execute(['uid' => $userId, 'ref' => $ref]);
        if ($chk->fetchColumn()) {
            return;
        }
        $this->wallet->add(
            $userId,
            'booking_pay',
            -$walletCents,
            'myr',
            $ref,
            'Booking #' . $bookingId . ' payment (wallet)'
        );
    }

    // ── Wallet ────────────────────────────────────────────────────────────
    // The wallet is a ledger (WalletModel). Top-up = a real PaymentIntent on the
    // saved card; withdraw = a real Refund of prior top-ups. Both produce real
    // Stripe sandbox objects AND a settled ledger row — no fake balances.

    public function getWallet(int $userId, ?string $fromDate = null, ?string $toDate = null): array
    {
        return [
            'balance_cents' => $this->wallet->balanceCents($userId),
            'currency' => 'myr',
            'transactions' => $this->wallet->list($userId, 50, $fromDate, $toDate),
            'mode' => 'test',
        ];
    }

    /** Charge the saved card; on success credit the wallet ledger. */
    public function walletTopUp(int $userId, int $amountCents, string $currency = 'myr'): array
    {
        if ($amountCents < 50) {
            throw new \RuntimeException('Top-up must be at least 50 cents');
        }

        $currency = strtolower($currency);
        if ($currency !== 'myr') {
            throw new \RuntimeException('Only MYR top-ups are supported');
        }

        $user = $this->users->findById($userId);
        $customerId = $user['stripe_test_customer_id'] ?? null;
        $pmId = $user['stripe_test_default_payment_method_id'] ?? null;
        if (!$customerId || !$pmId) {
            throw new \RuntimeException('No saved card — add a card first');
        }

        $this->verifyPaymentMethodOwnership($customerId, $pmId);

        $intent = $this->stripe->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'customer' => $customerId,
            'payment_method' => $pmId,
            'off_session' => true,
            'confirm' => true,
            'metadata' => [
                'fixit_user_id' => (string) $userId,
                'fixit_purpose' => 'wallet_topup',
                'stripe_mode' => 'test',
            ],
        ]);

        $this->payments->upsertFromPaymentIntent($userId, $intent);

        if ($intent->status === 'requires_action') {
            // 3DS — not credited until confirmed client-side.
            return [
                'status' => 'requires_action',
                'requires_action' => true,
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id,
                'balance_cents' => $this->wallet->balanceCents($userId),
            ];
        }

        if ($intent->status !== 'succeeded') {
            throw new \RuntimeException('Top-up not completed: ' . $intent->status);
        }

        $this->creditWalletTopUpIfNeeded($userId, (string) $intent->id, $amountCents);

        return [
            'status' => 'succeeded',
            'paid' => true,
            'payment_intent_id' => $intent->id,
            'amount_cents' => $amountCents,
            'balance_cents' => $this->wallet->balanceCents($userId),
        ];
    }

    /**
     * Withdraw = real Stripe Refund(s) of prior top-up charges, newest first.
     * Balance (ledger sum) equals total still-refundable, so a balance check
     * guarantees enough to refund. Records one settled debit row.
     */
    public function walletWithdraw(int $userId, int $amountCents, string $currency = 'myr'): array
    {
        if ($amountCents < 50) {
            throw new \RuntimeException('Withdraw must be at least 50 cents');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $balance = $this->wallet->lockAndBalance($userId);
            if ($amountCents > $balance) {
                throw new \RuntimeException('Insufficient wallet balance');
            }
            $this->wallet->add(
                $userId,
                'withdraw',
                -$amountCents,
                $currency,
                'withdraw_pending_' . bin2hex(random_bytes(6)),
                'Withdrawal to card (pending refund)'
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage());
        }

        $need = $amountCents;
        $refundIds = [];
        try {
            foreach ($this->wallet->topupIntents($userId) as $piId) {
                if ($need <= 0) {
                    break;
                }
                $pi = $this->stripe->paymentIntents->retrieve($piId, ['expand' => ['latest_charge']]);
                $charge = $pi->latest_charge;
                if (!$charge || $charge->status !== 'succeeded') {
                    continue;
                }
                $refundable = (int) $charge->amount - (int) $charge->amount_refunded;
                if ($refundable <= 0) {
                    continue;
                }
                $chunk = min($need, $refundable);
                $refund = $this->stripe->refunds->create([
                    'charge' => $charge->id,
                    'amount' => $chunk,
                    'metadata' => [
                        'fixit_user_id' => (string) $userId,
                        'fixit_purpose' => 'wallet_withdraw',
                    ],
                ]);
                $refundIds[] = $refund->id;
                $need -= $chunk;
            }
            if ($need > 0) {
                throw new \RuntimeException('Could not refund full amount — try a smaller withdraw');
            }
        } catch (\Throwable $e) {
            $this->wallet->add(
                $userId,
                'adjustment',
                $amountCents,
                $currency,
                'withdraw_rollback_' . bin2hex(random_bytes(6)),
                'Withdrawal rollback — Stripe refund failed'
            );
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage());
        }

        return [
            'status' => 'succeeded',
            'amount_cents' => $amountCents,
            'refund_ids' => $refundIds,
            'balance_cents' => $this->wallet->balanceCents($userId),
        ];
    }

    /**
     * Provider withdrawal = a real Stripe test Refund of the platform's
     * refundable charges, newest first, debiting the provider's wallet ledger.
     * Providers have no charge of their own (earnings are ledger payouts), so we
     * draw from the platform's refundable PaymentIntents — a real sandbox object
     * representing money leaving. Falls back to an error if nothing is refundable.
     */
    public function providerWithdraw(int $userId, int $amountCents, string $currency = 'myr'): array
    {
        if ($amountCents < 50) {
            throw new \RuntimeException('Withdraw must be at least 50 cents');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $balance = $this->wallet->lockAndBalance($userId);
            if ($amountCents > $balance) {
                throw new \RuntimeException('Insufficient wallet balance');
            }

            // Test mode: debit the provider ledger only. Provider earnings are
            // virtual credits — never refund unrelated customer top-up charges.
            $this->wallet->add(
                $userId,
                'withdraw',
                -$amountCents,
                $currency,
                'provider_payout_' . bin2hex(random_bytes(8)),
                'Provider withdrawal (test ledger)'
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage());
        }

        return [
            'status' => 'succeeded',
            'amount_cents' => $amountCents,
            'balance_cents' => $this->wallet->balanceCents($userId),
            'payout_method' => 'ledger_test',
            'test_mode' => true,
            'warning' => 'Provider withdrawal debits the test ledger only. Use Stripe Connect for production bank payouts.',
        ];
    }

    /**
     * Refund a paid booking into the customer's wallet ledger. Idempotent:
     * the wallet transaction is keyed by booking id, and paid rows are marked
     * refunded so cancellation cannot credit the wallet twice.
     */
    public function refundBookingIfPaid(int $bookingId, int $customerUserId): bool
    {
        $payments = $this->payments->findAllSucceededByBooking($bookingId);
        $paidCents = 0;
        foreach ($payments as $payment) {
            if ((int) $payment['user_id'] !== $customerUserId) {
                throw new \RuntimeException('Payment owner mismatch');
            }
            $paidCents = max($paidCents, (int) ($payment['amount_cents'] ?? 0));
        }

        $refundedAny = $this->wallet->refundBookingPayment(
            $customerUserId,
            $bookingId,
            $paidCents > 0 ? $paidCents : null
        );

        if ($refundedAny) {
            $this->payments->markRefunded($bookingId);
        }
        return $refundedAny;
    }

    public function detachSavedPaymentMethod(int $userId): void
    {
        $user = $this->users->findById($userId);
        if (!$user) {
            return;
        }

        $pmId = $user['stripe_test_default_payment_method_id'] ?? null;
        if ($pmId) {
            try {
                $this->stripe->paymentMethods->detach($pmId);
            } catch (ApiErrorException $e) {
                if ($e->getStripeCode() !== 'resource_missing') {
                    throw $e;
                }
            }
        }

        $this->users->clearStripePaymentMethod($userId);
    }

    public function getSavedPaymentSummary(int $userId): array
    {
        $user = $this->users->findById($userId);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $hasSaved = !empty($user['stripe_test_default_payment_method_id']);

        return [
            'stripe_test_customer_id' => $user['stripe_test_customer_id'] ?? null,
            'has_saved_payment_method' => $hasSaved,
            'payment_method_id' => $user['stripe_test_default_payment_method_id'] ?? null,
            'last4' => $user['stripe_test_payment_method_last4'] ?? null,
            'brand' => $user['stripe_test_payment_method_brand'] ?? null,
            'saved_at' => $user['stripe_test_payment_method_created_at'] ?? null,
            'mode' => 'test',
        ];
    }

    /** @param string $rawBody Raw webhook payload (not parsed JSON). */
    public function handleWebhook(string $rawBody, ?string $signatureHeader): void
    {
        $secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        if ($secret === '' || $secret === 'whsec_replace_me') {
            throw new \RuntimeException('STRIPE_WEBHOOK_SECRET not configured');
        }

        $event = Webhook::constructEvent($rawBody, $signatureHeader ?? '', $secret);

        switch ($event->type) {
            case 'setup_intent.succeeded':
                $this->onSetupIntentSucceeded($event->data->object);
                break;
            case 'payment_intent.succeeded':
                $this->onPaymentIntentSucceeded($event->data->object);
                break;
            case 'payment_intent.payment_failed':
                $this->onPaymentIntentFailed($event->data->object);
                break;
        }
    }

    private function onSetupIntentSucceeded(object $setupIntent): void
    {
        $userId = (int) ($setupIntent->metadata->fixit_user_id ?? 0);
        $pmId = $setupIntent->payment_method ?? null;
        if ($userId > 0 && $pmId) {
            try {
                $this->savePaymentMethod($userId, (string) $pmId);
            } catch (\Throwable) {
                // Frontend may have already saved; webhook is idempotent backup.
            }
        }
    }

    private function onPaymentIntentSucceeded(object $intent): void
    {
        $userId = (int) ($intent->metadata->fixit_user_id ?? 0);
        $bookingId = isset($intent->metadata->fixit_booking_id)
            ? (int) $intent->metadata->fixit_booking_id
            : null;
        if ($userId > 0) {
            $bookingTotal = (int) ($intent->metadata->fixit_booking_total_cents ?? 0);
            $walletCents = (int) ($intent->metadata->fixit_wallet_cents ?? 0);
            if ($bookingId !== null && $bookingTotal > 0) {
                if ($walletCents > 0) {
                    $this->debitWalletForBooking($userId, $bookingId, $walletCents);
                }
                $this->payments->recordSucceededBookingPayment(
                    $userId,
                    $bookingId,
                    (string) $intent->id,
                    $bookingTotal,
                    (string) ($intent->currency ?? 'myr')
                );
            } else {
                $this->payments->upsertFromPaymentIntent($userId, $intent, $bookingId);
            }
            if (($intent->metadata->fixit_purpose ?? '') === 'wallet_topup') {
                $this->creditWalletTopUpIfNeeded($userId, (string) $intent->id, (int) $intent->amount);
            }
        }
    }

    private function creditWalletTopUpIfNeeded(int $userId, string $piId, int $amountCents): void
    {
        $pdo = Connection::get();
        $chk = $pdo->prepare(
            "SELECT 1 FROM WalletTransaction
             WHERE user_id = :uid AND kind = 'topup' AND stripe_ref = :ref LIMIT 1"
        );
        $chk->execute(['uid' => $userId, 'ref' => $piId]);
        if ($chk->fetchColumn()) {
            return;
        }
        $this->wallet->add($userId, 'topup', $amountCents, 'myr', $piId, 'Wallet top-up (card, 3DS confirmed)');
    }

    private function assertBookingPayable(int $userId, int $bookingId, int $amountCents, bool $forUpdate = false): void
    {
        $pdo = Connection::get();
        $sql = 'SELECT customer_id, status, total FROM Job WHERE id = :id';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $bookingId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException('Booking not found');
        }
        if ((int) $row['customer_id'] !== $userId) {
            throw new \RuntimeException('Booking does not belong to this user');
        }
        if (!in_array($row['status'], ['requested', 'accepted'], true)) {
            throw new \RuntimeException('Booking is not payable in its current status');
        }
        if ($this->payments->findSucceededByBooking($bookingId)) {
            throw new \RuntimeException('Booking has already been paid');
        }
        $expectedCents = (int) round(((float) ($row['total'] ?? 0)) * 100);
        if ($expectedCents > 0 && $amountCents !== $expectedCents) {
            throw new \RuntimeException('Payment amount does not match booking total');
        }
    }

    private function onPaymentIntentFailed(object $intent): void
    {
        $userId = (int) ($intent->metadata->fixit_user_id ?? 0);
        $bookingId = isset($intent->metadata->fixit_booking_id)
            ? (int) $intent->metadata->fixit_booking_id
            : null;
        if ($userId > 0) {
            $this->payments->upsertFromPaymentIntent(
                $userId,
                $intent,
                $bookingId,
                $intent->last_payment_error->message ?? 'Payment failed'
            );
        }
    }

    private function verifyPaymentMethodOwnership(string $customerId, string $paymentMethodId): void
    {
        $pm = $this->stripe->paymentMethods->retrieve($paymentMethodId);
        if ($pm->customer !== $customerId) {
            throw new \RuntimeException('Saved PaymentMethod does not belong to this user');
        }
    }

    private function assertStripeId(string $id, string $prefix): void
    {
        if (!str_starts_with($id, $prefix)) {
            throw new \InvalidArgumentException("Invalid Stripe ID — expected {$prefix}");
        }
    }

    private function assertTestModeConfigured(): void
    {
        $secret = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        if (!str_starts_with($secret, 'sk_test_')) {
            throw new \RuntimeException(
                'STRIPE_SECRET_KEY must be a Stripe TEST secret key (sk_test_...). Live keys are blocked.'
            );
        }
        $mode = $_ENV['STRIPE_MODE'] ?? 'test';
        if ($mode !== 'test') {
            throw new \RuntimeException('STRIPE_MODE must be "test" for this deployment');
        }
    }
}
