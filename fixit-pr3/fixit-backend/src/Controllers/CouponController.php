<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\CouponModel;
use FixIt\Models\ProviderModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CouponController
{
    private CouponModel $coupons;

    public function __construct()
    {
        $this->coupons = new CouponModel();
    }

    public function validate(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        if ($err = Validator::requireFields($data, ['code', 'provider_id', 'subtotal'])) {
            return ResponseHelper::error($response, $err, 422);
        }

        $providerId = (int) $data['provider_id'];
        if (!(new ProviderModel())->findRaw($providerId)) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }

        $subtotal = max(0, (float) $data['subtotal']);
        $result = $this->coupons->validate(
            (string) $data['code'],
            $providerId,
            $subtotal,
            (int) $user['id']
        );
        return ResponseHelper::json($response, $result);
    }

    public function available(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $providerId = isset($params['provider_id']) ? (int) $params['provider_id'] : 0;
        if ($providerId <= 0) {
            return ResponseHelper::error($response, 'provider_id is required', 422);
        }
        if (!(new ProviderModel())->findRaw($providerId)) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }
        return ResponseHelper::json($response, $this->coupons->listAvailable($providerId));
    }

    public function listMine(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $profile = (new ProviderModel())->findByUserId((int) $user['id']);
        if (!$profile) {
            return ResponseHelper::error($response, 'Provider profile not found', 404);
        }
        return ResponseHelper::json($response, $this->coupons->listForProvider((int) $profile['id']));
    }

    public function createMine(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $profile = (new ProviderModel())->findByUserId((int) $user['id']);
        if (!$profile) {
            return ResponseHelper::error($response, 'Provider profile not found', 404);
        }
        $data = (array) $request->getParsedBody();
        if ($err = Validator::requireFields($data, ['code', 'discount_type', 'discount_value', 'expires_at'])) {
            return ResponseHelper::error($response, $err, 422);
        }

        $clean = $this->cleanInput($data);
        if ($clean['discount_type'] === 'percent' && $clean['discount_value'] > 100) {
            return ResponseHelper::error($response, 'Percent discount cannot exceed 100', 422);
        }

        if ($this->coupons->findByCode($clean['code'])) {
            return ResponseHelper::error($response, 'Coupon code already exists', 422);
        }

        $coupon = $this->coupons->create([
            ...$clean,
            'scope' => 'provider',
            'provider_id' => (int) $profile['id'],
            'created_by' => (int) $user['id'],
        ]);
        return ResponseHelper::json($response, $coupon, 201);
    }

    public function updateMine(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $profile = (new ProviderModel())->findByUserId((int) $user['id']);
        if (!$profile) {
            return ResponseHelper::error($response, 'Provider profile not found', 404);
        }
        $id = (int) $args['id'];
        $existing = $this->coupons->find($id);
        if (!$existing || $existing['scope'] !== 'provider' || (int) $existing['provider_id'] !== (int) $profile['id']) {
            return ResponseHelper::error($response, 'Coupon not found', 404);
        }

        $data = (array) $request->getParsedBody();
        $merged = array_merge($existing, $data);
        $clean = $this->cleanInput($merged);
        if ($clean['discount_type'] === 'percent' && $clean['discount_value'] > 100) {
            return ResponseHelper::error($response, 'Percent discount cannot exceed 100', 422);
        }

        $other = $this->coupons->findByCode($clean['code']);
        if ($other && (int) $other['id'] !== $id) {
            return ResponseHelper::error($response, 'Coupon code already exists', 422);
        }

        $updated = $this->coupons->update($id, $clean);
        return ResponseHelper::json($response, $updated);
    }

    public function deleteMine(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $profile = (new ProviderModel())->findByUserId((int) $user['id']);
        if (!$profile) {
            return ResponseHelper::error($response, 'Provider profile not found', 404);
        }
        $id = (int) $args['id'];
        $existing = $this->coupons->find($id);
        if (!$existing || $existing['scope'] !== 'provider' || (int) $existing['provider_id'] !== (int) $profile['id']) {
            return ResponseHelper::error($response, 'Coupon not found', 404);
        }
        $this->coupons->delete($id);
        return ResponseHelper::json($response, ['deleted' => true]);
    }

    public function listAdmin(Request $request, Response $response): Response
    {
        $p = $request->getQueryParams();
        $limit = min(50, max(1, (int) ($p['limit'] ?? 25)));
        $offset = max(0, (int) ($p['offset'] ?? 0));
        return ResponseHelper::json($response, $this->coupons->listAdmin($limit, $offset));
    }

    public function createAdmin(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        if ($err = Validator::requireFields($data, ['code', 'discount_type', 'discount_value', 'expires_at'])) {
            return ResponseHelper::error($response, $err, 422);
        }

        $clean = $this->cleanInput($data);
        if ($clean['discount_type'] === 'percent' && $clean['discount_value'] > 100) {
            return ResponseHelper::error($response, 'Percent discount cannot exceed 100', 422);
        }

        if ($this->coupons->findByCode($clean['code'])) {
            return ResponseHelper::error($response, 'Coupon code already exists', 422);
        }

        $coupon = $this->coupons->create([
            ...$clean,
            'scope' => 'system',
            'provider_id' => null,
            'created_by' => (int) $user['id'],
        ]);
        return ResponseHelper::json($response, $coupon, 201);
    }

    public function updateAdmin(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $existing = $this->coupons->find($id);
        if (!$existing) {
            return ResponseHelper::error($response, 'Coupon not found', 404);
        }

        $data = (array) $request->getParsedBody();
        $merged = array_merge($existing, $data);
        $clean = $this->cleanInput($merged);
        if ($clean['discount_type'] === 'percent' && $clean['discount_value'] > 100) {
            return ResponseHelper::error($response, 'Percent discount cannot exceed 100', 422);
        }

        $other = $this->coupons->findByCode($clean['code']);
        if ($other && (int) $other['id'] !== $id) {
            return ResponseHelper::error($response, 'Coupon code already exists', 422);
        }

        $updated = $this->coupons->update($id, $clean);
        return ResponseHelper::json($response, $updated);
    }

    public function deleteAdmin(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->coupons->find($id)) {
            return ResponseHelper::error($response, 'Coupon not found', 404);
        }
        $this->coupons->delete($id);
        return ResponseHelper::json($response, ['deleted' => true]);
    }

    /** @param array<string,mixed> $data */
    private function cleanInput(array $data): array
    {
        $dtype = in_array($data['discount_type'] ?? '', ['percent', 'fixed'], true)
            ? $data['discount_type']
            : 'percent';

        $startsRaw = $data['starts_at'] ?? date('Y-m-d H:i:s');
        $startsTs = strtotime(str_replace('T', ' ', (string) $startsRaw));
        $startsAt = $startsTs ? date('Y-m-d H:i:s', $startsTs) : date('Y-m-d H:i:s');

        $expiresRaw = (string) ($data['expires_at'] ?? '');
        $expiresTs = strtotime(str_replace('T', ' ', $expiresRaw));
        $expiresAt = $expiresTs ? date('Y-m-d H:i:s', $expiresTs) : date('Y-m-d H:i:s', strtotime('+30 days'));

        return [
            'code' => $this->coupons->normalizeCode((string) ($data['code'] ?? '')),
            'discount_type' => $dtype,
            'discount_value' => max(0, (float) ($data['discount_value'] ?? 0)),
            'min_spend' => max(0, (float) ($data['min_spend'] ?? $data['min_order'] ?? 0)),
            'max_discount' => isset($data['max_discount']) && $data['max_discount'] !== ''
                ? max(0, (float) $data['max_discount'])
                : null,
            'usage_limit' => isset($data['usage_limit']) && $data['usage_limit'] !== ''
                ? max(1, (int) $data['usage_limit'])
                : null,
            'per_user_limit' => max(1, (int) ($data['per_user_limit'] ?? 1)),
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : (bool) ($data['active'] ?? true),
        ];
    }
}