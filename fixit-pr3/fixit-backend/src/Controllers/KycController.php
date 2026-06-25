<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\ProviderModel;
use FixIt\Services\FaceMatchService;
use FixIt\Support\KycValidator;
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

        $provider = $model->getEnriched($id);
        if (!$provider) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }
        return ResponseHelper::json($response, self::kycPayload($provider));
    }

    /** @param array<string,mixed> $provider */
    private static function kycPayload(array $provider): array
    {
        return [
            'provider_id' => $provider['id'],
            'kyc_status' => $provider['kyc_status'],
            'kyc_doc_url' => $provider['kyc_doc_url'],
            'kyc_id_type' => $provider['kyc_id_type'],
            'kyc_id_confidence' => $provider['kyc_id_confidence'],
            'kyc_id_checks' => $provider['kyc_id_checks'],
            'kyc_liveness_passed' => $provider['kyc_liveness_passed'],
            'kyc_liveness_score' => $provider['kyc_liveness_score'],
            'kyc_color_sequence_hash' => $provider['kyc_color_sequence_hash'],
            'kyc_liveness_checks' => $provider['kyc_liveness_checks'],
            'kyc_submitted_at' => $provider['kyc_submitted_at'],
            'is_verified' => $provider['is_verified'],
        ];
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
        $err = Validator::requireFields($data, [
            'valid', 'document_type', 'confidence', 'checks', 'fraud_score', 'ocr_confidence',
        ]);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $serverReview = KycValidator::validateIdSubmission($data);
        if (!$serverReview['approved']) {
            $model->saveIdRecognition($id, [
                'valid' => false,
                'document_type' => (string) ($data['document_type'] ?? 'unknown'),
                'confidence' => (float) $data['confidence'],
                'checks' => array_merge((array) $data['checks'], [
                    'server_review' => $serverReview,
                ]),
                'doc_url' => null,
                'image_hash' => $data['image_hash'] ?? null,
                'extracted_preview' => $data['extracted_preview'] ?? null,
                'fraud_score' => (float) ($data['fraud_score'] ?? 100),
                'ocr_confidence' => (float) ($data['ocr_confidence'] ?? 0),
                'rejection_reasons' => $serverReview['rejection_reasons'],
                'module_version' => $data['module_version'] ?? 'kyc-id-v2',
            ]);
            return ResponseHelper::error(
                $response,
                'ID verification rejected: ' . implode('; ', $serverReview['rejection_reasons']),
                422
            );
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

        $filename = basename((string) ($data['filename'] ?? 'government_id.jpg'));
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'government_id.jpg';
        $docUrl = '/uploads/kyc/' . $id . '_' . $filename;

        $summary = $model->saveIdRecognition($id, [
            'valid' => true,
            'document_type' => $docType,
            'confidence' => $confidence,
            'checks' => array_merge($checks, ['server_review' => $serverReview]),
            'doc_url' => $docUrl,
            'image_hash' => isset($data['image_hash']) ? (string) $data['image_hash'] : null,
            'extracted_preview' => isset($data['extracted_preview'])
                ? Validator::cleanText((string) $data['extracted_preview'], 500)
                : null,
            'fraud_score' => (float) $data['fraud_score'],
            'ocr_confidence' => (float) $data['ocr_confidence'],
            'module_version' => $data['module_version'] ?? 'kyc-id-v2',
        ]);

        return ResponseHelper::json($response, self::kycPayload($summary));
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

        $passed = (bool) $data['passed'];

        // Server-side face match: compare the ID photo against the live selfie via
        // the local gateway. A real same-person score gates the pass; a detection
        // failure (no face / gateway down) is recorded but stays inconclusive.
        $idImg = self::decodeImage($data['id_image'] ?? null);
        $selfieImg = self::decodeImage($data['selfie_image'] ?? null);
        if (FaceMatchService::isConfigured() && (!$idImg || !$selfieImg)) {
            return ResponseHelper::error($response, 'ID and selfie images are required for liveness verification', 422);
        }
        if (FaceMatchService::isConfigured() && $idImg && $selfieImg) {
            $fm = (new FaceMatchService())->match($idImg['bin'], $idImg['mime'], $selfieImg['bin'], $selfieImg['mime']);
            $checks['face_match'] = [
                'score' => $fm['score'],
                'min_score' => (new FaceMatchService())->minScore(),
                'gateway_ok' => $fm['ok'],
                'message' => $fm['message'],
                'passed' => $fm['passed'],
            ];
            if ($fm['passed'] === false) {
                $passed = false;
            }
        }

        $summary = $model->saveLivenessCheck($id, [
            'passed' => $passed,
            'score' => $score,
            'color_sequence_hash' => $hash,
            'checks' => $checks,
        ]);

        if (!$passed && isset($checks['face_match']) && $checks['face_match']['passed'] === false) {
            return ResponseHelper::error(
                $response,
                'Face does not match the ID photo (match score ' . ($checks['face_match']['score'] ?? '?') . '%).',
                422
            );
        }

        return ResponseHelper::json($response, self::kycPayload($summary));
    }

    /**
     * Decode a base64 data URL into raw bytes + mime, or null if absent/invalid.
     * @return array{bin:string,mime:string}|null
     */
    private static function decodeImage(mixed $raw): ?array
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        if (!preg_match('#^data:([^;]+);base64,(.+)$#s', $raw, $m)) {
            return null;
        }
        $bin = base64_decode($m[2], true);
        if ($bin === false || $bin === '') {
            return null;
        }
        return ['bin' => $bin, 'mime' => strtolower($m[1])];
    }
}