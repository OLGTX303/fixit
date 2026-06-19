<?php

declare(strict_types=1);

namespace FixIt\Services;

use FixIt\Models\StripePaymentModel;
use FixIt\Models\UserModel;
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

    public function __construct()
    {
        $this->assertTestModeConfigured();
        $this->stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY']);
        $this->users = new UserModel();
        $this->payments = new StripePaymentModel();
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
    public function payWithSavedMethod(int $userId, int $amountCents, ?int $bookingId = null, string $currency = 'usd'): array
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

        $this->payments->upsertFromPaymentIntent($userId, $intent, $bookingId);

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
            $this->payments->upsertFromPaymentIntent($userId, $intent, $bookingId);
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