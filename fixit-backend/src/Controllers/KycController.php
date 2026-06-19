<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\ProviderModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class KycController
{
    private const ID_TYPES = ['passport', 'national_id', 'drivers_license', 'residence_permit', 'unknown'];

    public function status(Request $request, Response $response, array $args): Response
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

        return ResponseHelper::json($response, $model->getKycSummary($id));
    }

    public function submitIdRecognition(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int) $args['id'];
        $model = new ProviderModel();
        $existing = $model->findRaw($id);
        if (!$existing) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }
        if ((int) $existing['user_id'] !== (int) $user['id']) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['valid', 'document_type', 'confidence', 'checks']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $docType = (string) $data['document_type'];
        if (!in_array($docType, self::ID_TYPES, true)) {
            return ResponseHelper::error($response, 'Invalid document_type', 422);
        }

        $confidence = (float) $data['confidence'];
        if ($confidence < 0 || $confidence > 100) {
            return ResponseHelper::error($response, 'confidence must be 0–100', 422);
        }

        $checks = $data['checks'];
        if (!is_array($checks)) {
            return ResponseHelper::error($response, 'checks must be an object', 422);
        }

        $valid = (bool) $data['valid'];
        $filename = basename((string) ($data['filename'] ?? 'government_id.jpg'));
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'government_id.jpg';
        $docUrl = '/uploads/kyc/' . $id . '_' . $filename;

        $summary = $model->saveIdRecognition($id, [
            'valid' => $valid,
            'document_type' => $docType,
            'confidence' => $confidence,
            'checks' => $checks,
            'doc_url' => $docUrl,
            'image_hash' => isset($data['image_hash']) ? (string) $data['image_hash'] : null,
            'extracted_preview' => isset($data['extracted_preview'])
                ? Validator::cleanText((string) $data['extracted_preview'], 500)
                : null,
        ]);

        return ResponseHelper::json($response, $summary);
    }

    public function submitLiveness(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int) $args['id'];
        $model = new ProviderModel();
        $existing = $model->findRaw($id);
        if (!$existing) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }
        if ((int) $existing['user_id'] !== (int) $user['id']) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        if (($existing['kyc_status'] ?? 'none') !== 'id_passed') {
            return ResponseHelper::error($response, 'Complete government ID verification first', 409);
        }

        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['passed', 'score', 'color_sequence_hash', 'checks']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $score = (float) $data['score'];
        if ($score < 0 || $score > 100) {
            return ResponseHelper::error($response, 'score must be 0–100', 422);
        }

        $checks = $data['checks'];
        if (!is_array($checks)) {
            return ResponseHelper::error($response, 'checks must be an object', 422);
        }

        $hash = (string) $data['color_sequence_hash'];
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return ResponseHelper::error($response, 'Invalid color_sequence_hash', 422);
        }

        $summary = $model->saveLivenessCheck($id, [
            'passed' => (bool) $data['passed'],
            'score' => $score,
            'color_sequence_hash' => $hash,
            'checks' => $checks,
        ]);

        return ResponseHelper::json($response, $summary);
    }
}