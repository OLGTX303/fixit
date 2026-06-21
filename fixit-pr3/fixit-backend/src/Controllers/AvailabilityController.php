<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\AvailabilityModel;
use FixIt\Models\ProviderModel;
use FixIt\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AvailabilityController
{
    /** GET /providers/{id}/availability — public */
    public function get(Request $request, Response $response, array $args): Response
    {
        $providerId = (int) $args['id'];
        $provider = (new ProviderModel())->findRaw($providerId);
        if (!$provider) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }
        $slots = (new AvailabilityModel())->forProvider($providerId);
        return ResponseHelper::json($response, $slots);
    }

    /** PUT /providers/{id}/availability — provider only */
    public function save(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $providerId = (int) $args['id'];

        // Only the owning provider (or admin) may update availability
        $providerModel = new ProviderModel();
        $profile = $providerModel->findRaw($providerId);
        if (!$profile) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }
        if ($user['role'] !== 'admin' && (int) $profile['user_id'] !== (int) $user['id']) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        $body = (array) $request->getParsedBody();
        $slots = $body['slots'] ?? [];
        if (!is_array($slots)) {
            return ResponseHelper::error($response, 'slots must be an array', 422);
        }

        // Validate each slot
        $days = ['day_of_week', 'start_time', 'end_time'];
        foreach ($slots as $i => $slot) {
            foreach ($days as $field) {
                if (!isset($slot[$field])) {
                    return ResponseHelper::error($response, "slots[$i] missing $field", 422);
                }
            }
            $dow = (int) $slot['day_of_week'];
            if ($dow < 0 || $dow > 6) {
                return ResponseHelper::error($response, "slots[$i].day_of_week must be 0–6", 422);
            }
        }

        $saved = (new AvailabilityModel())->save($providerId, $slots);
        return ResponseHelper::json($response, $saved);
    }
}
