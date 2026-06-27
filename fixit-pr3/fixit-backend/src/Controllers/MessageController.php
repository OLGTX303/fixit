<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\BookingModel;
use FixIt\Models\HarmReviewModel;
use FixIt\Models\MessageModel;
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

        return ResponseHelper::json($response, $message, 201);
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