<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\ProviderModel;
use FixIt\Models\ProviderServiceModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProviderServiceController
{
    private ProviderServiceModel $services;

    public function __construct()
    {
        $this->services = new ProviderServiceModel();
    }

    /** Public: list a provider's services. */
    public function list(Request $request, Response $response, array $args): Response
    {
        return ResponseHelper::json($response, $this->services->listForProvider((int) $args['id']));
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $providerId = (int) $args['id'];
        if ($err = $this->denyIfNotOwner($request, $providerId)) {
            return ResponseHelper::error($response, $err, 403);
        }
        $data = (array) $request->getParsedBody();
        if ($e = Validator::requireFields($data, ['name', 'price'])) {
            return ResponseHelper::error($response, $e, 422);
        }
        return ResponseHelper::json($response, $this->services->create($providerId, $this->clean($data)), 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $serviceId = (int) $args['sid'];
        if ($err = $this->denyIfNotServiceOwner($request, $serviceId)) {
            return ResponseHelper::error($response, $err, $err === 'Not found' ? 404 : 403);
        }
        $data = (array) $request->getParsedBody();
        $existing = $this->services->find($serviceId);
        // merge so a partial update (e.g. just is_active toggle) keeps other fields
        $merged = array_merge($existing, $data);
        return ResponseHelper::json($response, $this->services->update($serviceId, $this->clean($merged)));
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $serviceId = (int) $args['sid'];
        if ($err = $this->denyIfNotServiceOwner($request, $serviceId)) {
            return ResponseHelper::error($response, $err, $err === 'Not found' ? 404 : 403);
        }
        $this->services->delete($serviceId);
        return ResponseHelper::json($response, ['deleted' => true]);
    }

    private function clean(array $d): array
    {
        $img = isset($d['image_url']) && $d['image_url']
            ? (filter_var((string) $d['image_url'], FILTER_VALIDATE_URL) ?: null)
            : null;
        return [
            'name' => Validator::cleanText((string) ($d['name'] ?? ''), 120),
            'price' => max(0, (float) ($d['price'] ?? 0)),
            'description' => isset($d['description']) ? Validator::cleanText((string) $d['description'], 500) : null,
            'image_url' => $img,
            'sku' => isset($d['sku']) ? Validator::cleanText((string) $d['sku'], 40) : null,
            'is_active' => array_key_exists('is_active', $d) ? (bool) $d['is_active'] : true,
            'sort_order' => (int) ($d['sort_order'] ?? 0),
        ];
    }

    private function denyIfNotOwner(Request $request, int $providerId): ?string
    {
        $user = $request->getAttribute('user');
        if (($user['role'] ?? '') === 'admin') {
            return null;
        }
        $provider = (new ProviderModel())->findRaw($providerId);
        if (!$provider) {
            return 'Provider not found';
        }
        return (int) $provider['user_id'] === (int) $user['id'] ? null : 'Forbidden';
    }

    private function denyIfNotServiceOwner(Request $request, int $serviceId): ?string
    {
        $user = $request->getAttribute('user');
        $ownerId = $this->services->ownerUserId($serviceId);
        if ($ownerId === null) {
            return 'Not found';
        }
        if (($user['role'] ?? '') === 'admin') {
            return null;
        }
        return $ownerId === (int) $user['id'] ? null : 'Forbidden';
    }
}
