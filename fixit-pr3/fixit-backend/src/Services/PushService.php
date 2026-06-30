<?php

declare(strict_types=1);

namespace FixIt\Services;

use FixIt\Models\PushSubscriptionModel;

/**
 * Sends chat push notifications to a user's registered devices.
 *
 * Two transports, each independently gated on env credentials — if a transport
 * is unconfigured it's silently skipped, so the app runs fine before you supply
 * keys (see PUSH_SETUP.md):
 *   - Android: Firebase Cloud Messaging (legacy HTTP), env FCM_SERVER_KEY.
 *   - Web:     Web Push / VAPID via minishlink/web-push, env VAPID_*.
 *
 * Never throws into the request path — failures are swallowed so a dead token
 * can't break sending a message.
 */
final class PushService
{
    public static function vapidPublicKey(): string
    {
        return (string) ($_ENV['VAPID_PUBLIC_KEY'] ?? '');
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $subs = (new PushSubscriptionModel())->forUser($userId);
        if (!$subs) {
            return;
        }

        $web = array_values(array_filter($subs, fn ($s) => $s['platform'] === 'web'));
        $android = array_values(array_filter($subs, fn ($s) => $s['platform'] === 'android'));

        try {
            $this->sendWeb($web, $title, $body, $data);
        } catch (\Throwable) { /* never break the request */ }

        foreach ($android as $s) {
            try {
                $this->sendFcm((string) $s['fcm_token'], $title, $body, $data, (int) $s['id']);
            } catch (\Throwable) { /* ignore */ }
        }
    }

    /** @param list<array<string,mixed>> $subs */
    private function sendWeb(array $subs, string $title, string $body, array $data): void
    {
        $pub = (string) ($_ENV['VAPID_PUBLIC_KEY'] ?? '');
        $priv = (string) ($_ENV['VAPID_PRIVATE_KEY'] ?? '');
        if (!$subs || $pub === '' || $priv === '' || !class_exists(\Minishlink\WebPush\WebPush::class)) {
            return; // not configured / library not installed
        }

        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject'    => (string) ($_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@fixit.local'),
                'publicKey'  => $pub,
                'privateKey' => $priv,
            ],
        ]);

        $payload = json_encode(['title' => $title, 'body' => $body, 'data' => $data]);
        $model = new PushSubscriptionModel();
        $byEndpoint = [];
        foreach ($subs as $s) {
            $byEndpoint[(string) $s['endpoint']] = (int) $s['id'];
            $webPush->queueNotification(
                \Minishlink\WebPush\Subscription::create([
                    'endpoint' => (string) $s['endpoint'],
                    'keys' => ['p256dh' => (string) $s['p256dh'], 'auth' => (string) $s['auth']],
                ]),
                $payload
            );
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                $id = $byEndpoint[$report->getEndpoint()] ?? null;
                if ($id) {
                    $model->removeById($id);
                }
            }
        }
    }

    private function sendFcm(string $token, string $title, string $body, array $data, int $rowId): void
    {
        $key = (string) ($_ENV['FCM_SERVER_KEY'] ?? '');
        if ($token === '' || $key === '') {
            return;
        }

        $payload = json_encode([
            'to' => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => array_map('strval', $data),
            'priority' => 'high',
        ]);

        [$status, $resp] = $this->httpPost(
            'https://fcm.googleapis.com/fcm/send',
            $payload,
            ['Content-Type: application/json', 'Authorization: key=' . $key]
        );

        // FCM reports a dead token via results[].error = NotRegistered/InvalidRegistration.
        if ($status === 200 && is_string($resp)) {
            $j = json_decode($resp, true);
            $err = $j['results'][0]['error'] ?? '';
            if (in_array($err, ['NotRegistered', 'InvalidRegistration'], true)) {
                (new PushSubscriptionModel())->removeById($rowId);
            }
        }
    }

    /** @return array{0:int,1:string|false} */
    private function httpPost(string $url, string $body, array $headers): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
            ]);
            $resp = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return [$status, $resp];
        }

        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 8,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
        return [$status, $resp];
    }
}
