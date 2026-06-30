<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Services\StripeService;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class WalletController
{
    private function service(): StripeService
    {
        if (!StripeService::isConfigured()) {
            throw new \RuntimeException('Stripe test mode is not configured');
        }
        return new StripeService();
    }

    public function get(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        if (!StripeService::isConfigured()) {
            return ResponseHelper::json($response, [
                'balance_cents' => 0,
                'currency' => 'myr',
                'transactions' => [],
                'mode' => 'test',
                'configured' => false,
            ]);
        }
        return ResponseHelper::json($response, $this->service()->getWallet((int) $user['id']));
    }

    public function topUp(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['amount_cents']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }
        try {
            $result = $this->service()->walletTopUp((int) $user['id'], (int) $data['amount_cents']);
            return ResponseHelper::json($response, $result);
        } catch (\RuntimeException | \Stripe\Exception\ApiErrorException $e) {
            // Stripe API errors carry a user-readable message — return 400, not 500.
            return ResponseHelper::error($response, $e->getMessage(), 400);
        }
    }

    public function withdraw(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['amount_cents']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }
        try {
            // Customers withdraw by refunding their own top-ups; providers withdraw
            // their earned ledger balance via a platform refund (real sandbox object).
            $result = ($user['role'] ?? '') === 'provider'
                ? $this->service()->providerWithdraw((int) $user['id'], (int) $data['amount_cents'])
                : $this->service()->walletWithdraw((int) $user['id'], (int) $data['amount_cents']);
            return ResponseHelper::json($response, $result);
        } catch (\RuntimeException | \Stripe\Exception\ApiErrorException $e) {
            // Stripe API errors carry a user-readable message — return 400, not 500.
            return ResponseHelper::error($response, $e->getMessage(), 400);
        }
    }
}
