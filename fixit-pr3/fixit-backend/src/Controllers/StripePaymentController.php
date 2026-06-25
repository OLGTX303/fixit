<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Services\StripeService;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class StripePaymentController
{
    private function service(): StripeService
    {
        if (!StripeService::isConfigured()) {
            throw new \RuntimeException('Stripe test mode is not configured');
        }
        return new StripeService();
    }

    /** Publishable key + saved card summary for Payment Element bootstrap. */
    public function config(Request $request, Response $response): Response
    {
        if (!StripeService::isConfigured()) {
            return ResponseHelper::json($response, [
                'configured' => false,
                'mode' => 'test',
            ]);
        }

        $user = $request->getAttribute('user');
        $stripe = $this->service();

        return ResponseHelper::json($response, [
            'configured' => true,
            'publishable_key' => $stripe->getPublishableKey(),
            'mode' => 'test',
            'saved_payment_method' => $stripe->getSavedPaymentSummary((int) $user['id']),
        ]);
    }

    public function ensureCustomer(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $result = $this->service()->ensureCustomer((int) $user['id']);
        return ResponseHelper::json($response, $result);
    }

    public function createSetupIntent(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $result = $this->service()->createSetupIntent((int) $user['id']);
        return ResponseHelper::json($response, $result);
    }

    public function savePaymentMethod(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['payment_method_id']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $pmId = (string) $data['payment_method_id'];
        if (!str_starts_with($pmId, 'pm_')) {
            return ResponseHelper::error($response, 'Invalid payment_method_id', 422);
        }

        try {
            $summary = $this->service()->savePaymentMethod((int) $user['id'], $pmId);
            return ResponseHelper::json($response, $summary);
        } catch (\RuntimeException $e) {
            return ResponseHelper::error($response, $e->getMessage(), 400);
        }
    }

    public function payWithSavedMethod(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['amount_cents']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $amountCents = (int) $data['amount_cents'];
        $bookingId = isset($data['booking_id']) ? (int) $data['booking_id'] : null;
        $currency = (string) ($data['currency'] ?? 'myr');

        try {
            $result = $this->service()->payWithSavedMethod(
                (int) $user['id'],
                $amountCents,
                $bookingId,
                $currency
            );
            return ResponseHelper::json($response, $result);
        } catch (\RuntimeException $e) {
            return ResponseHelper::error($response, $e->getMessage(), 400);
        }
    }

    public function removeSavedPaymentMethod(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $this->service()->detachSavedPaymentMethod((int) $user['id']);
        return ResponseHelper::json($response, ['removed' => true]);
    }

    /**
     * Stripe webhook — no JWT. Verifies Stripe-Signature header.
     * Must receive raw body (see public/index.php route).
     */
    public function webhook(Request $request, Response $response): Response
    {
        $raw = $request->getAttribute('stripe_raw_body')
            ?? $request->getBody()->getContents();
        $sig = $request->getHeaderLine('Stripe-Signature');

        try {
            $this->service()->handleWebhook($raw, $sig !== '' ? $sig : null);
            $response->getBody()->write(json_encode(['received' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            return ResponseHelper::error($response, $e->getMessage(), 400);
        }
    }
}