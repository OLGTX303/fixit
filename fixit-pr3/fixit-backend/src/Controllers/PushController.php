<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\PushSubscriptionModel;
use FixIt\Services\PushService;
use FixIt\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PushController
{
    /** GET /push/vapid-public-key — the browser needs this to subscribe. */
    public function vapidPublicKey(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, ['public_key' => PushService::vapidPublicKey()]);
    }

    /** POST /me/push/subscribe — body: { platform, endpoint?, p256dh?, auth?, fcm_token? } */
    public function subscribe(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $platform = (string) ($data['platform'] ?? '');
        if (!in_array($platform, ['web', 'android'], true)) {
            return ResponseHelper::error($response, 'Invalid platform', 422);
        }

        (new PushSubscriptionModel())->upsert((int) $user['id'], $platform, [
            'endpoint'  => isset($data['endpoint']) ? (string) $data['endpoint'] : null,
            'p256dh'    => isset($data['p256dh']) ? (string) $data['p256dh'] : null,
            'auth'      => isset($data['auth']) ? (string) $data['auth'] : null,
            'fcm_token' => isset($data['fcm_token']) ? (string) $data['fcm_token'] : null,
        ]);

        return ResponseHelper::json($response, ['subscribed' => true]);
    }

    /** DELETE /me/push/subscribe — body: { dedupe_key } (endpoint or fcm token). */
    public function unsubscribe(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $key = (string) ($data['dedupe_key'] ?? $data['endpoint'] ?? $data['fcm_token'] ?? '');
        if ($key !== '') {
            (new PushSubscriptionModel())->remove((int) $user['id'], $key);
        }
        return ResponseHelper::json($response, ['unsubscribed' => true]);
    }
}
