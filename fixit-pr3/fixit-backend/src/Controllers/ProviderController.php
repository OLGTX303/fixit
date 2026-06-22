<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\ProviderModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProviderController
{
    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $filters = !empty($params['category']) ? ['category' => (int) $params['category']] : [];

        $providers = (new ProviderModel())->listEnriched(true, $filters);
        return ResponseHelper::json($response, $providers);
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $provider = (new ProviderModel())->getEnriched((int) $args['id']);
        if (!$provider) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }
        return ResponseHelper::json($response, $provider);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['bio', 'location', 'base_rate', 'latitude', 'longitude']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $model = new ProviderModel();
        if ($model->findByUserId((int) $user['id'])) {
            return ResponseHelper::error($response, 'Provider profile already exists', 409);
        }

        $provider = $model->create((int) $user['id'], [
            'bio' => Validator::cleanText((string) $data['bio'], 2000),
            'location' => Validator::cleanText((string) $data['location'], 180),
            'base_rate' => (float) $data['base_rate'],
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'services' => $data['services'] ?? [],
            'category_ids' => $data['category_ids'] ?? [],
        ]);

        return ResponseHelper::json($response, $provider, 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int) $args['id'];
        $model = new ProviderModel();
        $existing = $model->findRaw($id);
        if (!$existing) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }
        if ($user['role'] !== 'admin' && (int) $existing['user_id'] !== (int) $user['id']) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        $data = (array) $request->getParsedBody();
        $coverUrl = isset($data['cover_url'])
            ? (filter_var((string) $data['cover_url'], FILTER_VALIDATE_URL) ?: null)
            : ($existing['cover_url'] ?? null);

        $provider = $model->update($id, [
            'bio'          => Validator::cleanText((string) ($data['bio'] ?? $existing['bio']), 2000),
            'location'     => Validator::cleanText((string) ($data['location'] ?? $existing['location']), 180),
            'base_rate'    => (float) ($data['base_rate'] ?? $existing['base_rate']),
            'rate_type'    => in_array($data['rate_type'] ?? null, ['hourly','per_job'], true)
                                  ? $data['rate_type']
                                  : ($existing['rate_type'] ?? 'hourly'),
            'per_job_rate' => isset($data['per_job_rate']) ? (float) $data['per_job_rate']
                                  : (isset($existing['per_job_rate']) ? (float) $existing['per_job_rate'] : null),
            'latitude'     => (float) ($data['latitude'] ?? $existing['latitude']),
            'longitude'    => (float) ($data['longitude'] ?? $existing['longitude']),
            'services'     => $data['services'] ?? json_decode((string) ($existing['services_json'] ?? '[]'), true),
            'category_ids' => $data['category_ids'] ?? null,
            'cover_url'    => $coverUrl,
        ]);

        return ResponseHelper::json($response, $provider);
    }

    /** PATCH /admin/providers/{id}/priority — admin only */
    public function setPriority(Request $request, Response $response, array $args): Response
    {
        $id   = (int) $args['id'];
        $data = (array) $request->getParsedBody();
        $isPriority = (bool) ($data['is_priority'] ?? false);

        $model    = new ProviderModel();
        $existing = $model->findRaw($id);
        if (!$existing) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }
        $provider = $model->setPriority($id, $isPriority);
        return ResponseHelper::json($response, $provider);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int) $args['id'];
        $model = new ProviderModel();
        $existing = $model->findRaw($id);
        if (!$existing) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }
        if ($user['role'] !== 'admin' && (int) $existing['user_id'] !== (int) $user['id']) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        $model->delete($id);
        return ResponseHelper::json($response, ['deleted' => true]);
    }

}