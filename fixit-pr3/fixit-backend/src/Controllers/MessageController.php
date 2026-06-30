<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\BookingModel;
use FixIt\Models\HarmReviewModel;
use FixIt\Models\MessageModel;
use FixIt\Services\PushService;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class MessageController
{
    private BookingModel $bookings;
    private MessageModel $messages;

    public function __construct()
    {
        $this->bookings = new BookingModel();
        $this->messages = new MessageModel();
    }

    public function list(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $jobId = (int) $args['id'];
        if ($err = $this->requireJob($user, $jobId)) {
            return ResponseHelper::error($response, $err['msg'], $err['code']);
        }

        $params = $request->getQueryParams();
        $limit = isset($params['limit']) ? (int) $params['limit'] : 200;
        $offset = isset($params['offset']) ? (int) $params['offset'] : 0;
        return ResponseHelper::json($response, $this->messages->forJob($jobId, $limit, $offset));
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $jobId = (int) $args['id'];
        if ($err = $this->requireJob($user, $jobId)) {
            return ResponseHelper::error($response, $err['msg'], $err['code']);
        }

        $data = (array) $request->getParsedBody();
        $isEncrypted = !empty($data['is_encrypted']);

        if ($isEncrypted) {
            $err = Validator::requireFields($data, ['ciphertext', 'iv', 'content_hash']);
            if ($err) {
                return ResponseHelper::error($response, $err, 422);
            }
        } else {
            $err = Validator::requireFields($data, ['body']);
            if ($err) {
                return ResponseHelper::error($response, $err, 422);
            }
        }

        $harmCategories = is_array($data['harm_categories'] ?? null) ? $data['harm_categories'] : [];
        $clientHarm = (string) ($data['harm_status'] ?? 'clear');
        if (!in_array($clientHarm, ['clear', 'flagged', 'blocked'], true)) {
            return ResponseHelper::error($response, 'Invalid harm_status', 422);
        }
        $harmStatus = ($clientHarm === 'blocked' || $harmCategories)
            ? ($clientHarm === 'blocked' ? 'blocked' : 'flagged')
            : $clientHarm;
        if ($harmStatus === 'blocked') {
            return ResponseHelper::error($response, 'Message blocked by safety review', 422);
        }

        $payload = [
            'body' => $isEncrypted ? null : Validator::cleanText((string) $data['body'], 4000),
            'ciphertext' => $isEncrypted ? (string) $data['ciphertext'] : null,
            'iv' => $isEncrypted ? (string) $data['iv'] : null,
            'is_encrypted' => $isEncrypted,
            'harm_status' => $harmStatus,
            'harm_categories' => $harmCategories,
            'content_hash' => $data['content_hash'] ?? null,
        ];

        $message = $this->messages->create($jobId, (int) $user['id'], $payload);

        if ($harmStatus === 'flagged') {
            (new HarmReviewModel())->create(
                (int) $message['id'],
                $jobId,
                (int) $user['id'],
                'flagged',
                $harmCategories,
                $payload['content_hash']
            );
        }

        $this->notifyRecipients($jobId, (int) $user['id'], $isEncrypted, $payload['body']);

        return ResponseHelper::json($response, $message, 201);
    }

    /**
     * Push a chat notification to the other party (or both, for an admin sender).
     * Encrypted bodies are never leaked — recipients get a generic preview.
     * Best-effort: any failure is swallowed so it can't break message creation.
     */
    private function notifyRecipients(int $jobId, int $senderId, bool $isEncrypted, ?string $body): void
    {
        try {
            $booking = $this->bookings->findEnriched($jobId);
            if (!$booking) {
                return;
            }
            $customerId = (int) ($booking['customer']['id'] ?? 0);
            $providerUserId = (int) ($booking['provider']['user_id'] ?? 0);

            $senderName = $senderId === $customerId
                ? (string) ($booking['customer']['name'] ?? 'Customer')
                : ($senderId === $providerUserId
                    ? (string) ($booking['provider']['name'] ?? 'Provider')
                    : 'Customer Service');

            $recipients = array_unique(array_filter(
                [$customerId, $providerUserId],
                fn ($id) => $id > 0 && $id !== $senderId
            ));
            if (!$recipients) {
                return;
            }

            $preview = $isEncrypted
                ? 'Sent you an encrypted message'
                : mb_substr((string) $body, 0, 80);
            $push = new PushService();
            foreach ($recipients as $rid) {
                $push->sendToUser((int) $rid, $senderName, $preview, [
                    'type' => 'chat',
                    'job_id' => (string) $jobId,
                ]);
            }
        } catch (\Throwable) {
            /* notifications are best-effort */
        }
    }

    /** @param array<string,mixed> $user */
    /** @return array{msg:string,code:int}|null */
    private function requireJob(array $user, int $jobId): ?array
    {
        $booking = $this->bookings->findEnriched($jobId);
        if (!$booking) {
            return ['msg' => 'Job not found', 'code' => 404];
        }
        if (!$this->bookings->userCanAccess($user, $booking)) {
            return ['msg' => 'Forbidden', 'code' => 403];
        }
        return null;
    }
}