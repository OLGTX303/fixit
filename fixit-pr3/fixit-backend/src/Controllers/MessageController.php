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
    public function list(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $jobId = (int) $args['id'];
        $bookingModel = new BookingModel();
        $booking = $bookingModel->findEnriched($jobId);
        if (!$booking) {
            return ResponseHelper::error($response, 'Job not found', 404);
        }
        if (!$bookingModel->userCanAccess($user, $booking)) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        $params = $request->getQueryParams();
        $limit = isset($params['limit']) ? (int) $params['limit'] : 200;
        $offset = isset($params['offset']) ? (int) $params['offset'] : 0;
        $messages = (new MessageModel())->forJob($jobId, $limit, $offset);
        return ResponseHelper::json($response, $messages);
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $jobId = (int) $args['id'];
        $bookingModel = new BookingModel();
        $booking = $bookingModel->findEnriched($jobId);
        if (!$booking) {
            return ResponseHelper::error($response, 'Job not found', 404);
        }
        if (!$bookingModel->userCanAccess($user, $booking)) {
            return ResponseHelper::error($response, 'Forbidden', 403);
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
        // Never trust client "clear" when categories were flagged, or when the
        // client explicitly reports blocked.
        $harmStatus = $clientHarm;
        if ($clientHarm === 'blocked' || count($harmCategories) > 0) {
            $harmStatus = $clientHarm === 'blocked' ? 'blocked' : 'flagged';
        }
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

        $message = (new MessageModel())->create($jobId, (int) $user['id'], $payload);

        if ($harmStatus === 'flagged') {
            (new HarmReviewModel())->create(
                (int) $message['id'],
                $jobId,
                (int) $user['id'],
                'flagged',
                is_array($payload['harm_categories']) ? $payload['harm_categories'] : [],
                $payload['content_hash']
            );
        }

        return ResponseHelper::json($response, $message, 201);
    }
}