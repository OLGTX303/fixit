<?php

declare(strict_types=1);

namespace FixIt\Support;

/**
 * Server-side KYC gate — never trust client-reported valid=true alone.
 */
final class KycValidator
{
    private const MIN_CONFIDENCE = 65.0;
    private const MAX_FRAUD_SCORE = 40.0;
    private const MIN_OCR_CONFIDENCE = 35.0;

    private const REQUIRED_CHECKS = [
        'resolution',
        'aspect_ratio',
        'anti_spoof',
        'contrast',
    ];

    /** @param array<string,mixed> $data */
    public static function validateIdSubmission(array $data): array
    {
        $reasons = [];
        $confidence = (float) ($data['confidence'] ?? 0);
        $fraudScore = (float) ($data['fraud_score'] ?? 100);
        $ocrConfidence = (float) ($data['ocr_confidence'] ?? 0);
        $checks = $data['checks'] ?? [];

        if (!is_array($checks)) {
            return self::reject(['Invalid checks payload']);
        }

        if ($confidence < self::MIN_CONFIDENCE) {
            $reasons[] = 'Confidence below server minimum (' . self::MIN_CONFIDENCE . '%)';
        }

        if ($fraudScore > self::MAX_FRAUD_SCORE) {
            $reasons[] = 'Fraud score too high (' . $fraudScore . ')';
        }

        // OCR loads its engine from a CDN at runtime and can fail to load on some
        // devices/networks. When it genuinely didn't run, treat text as advisory
        // (the image/anti-spoof checks plus the downstream face liveness + face
        // match remain the real identity gates). When OCR DID run, enforce it.
        $ocr = is_array($checks['ocr_quality'] ?? null) ? $checks['ocr_quality'] : [];
        $ocrRan = ((float) ($ocr['confidence'] ?? 0) > 0) || ((int) ($ocr['chars'] ?? 0) > 0);

        if ($ocrRan) {
            if ($ocrConfidence < self::MIN_OCR_CONFIDENCE) {
                $reasons[] = 'OCR confidence too low';
            }
            if (empty($checks['ocr_quality']['pass'])) {
                $reasons[] = 'Required check failed: ocr_quality';
            }
        }

        foreach (self::REQUIRED_CHECKS as $key) {
            if (empty($checks[$key]['pass'])) {
                $reasons[] = "Required check failed: {$key}";
            }
        }

        $mrz = $checks['mrz'] ?? null;
        if (is_array($mrz) && !empty($mrz['found']) && empty($mrz['pass'])) {
            $reasons[] = 'MRZ checksum validation failed';
        }

        $flags = $checks['fraud_flags'] ?? [];
        if (is_array($flags) && count($flags) >= 1) {
            $reasons[] = 'Fraud flags detected: ' . implode(', ', $flags);
        }

        $fraudScoreClient = $checks['anti_spoof']['fraud_score'] ?? null;
        if (is_numeric($fraudScoreClient) && (float) $fraudScoreClient > self::MAX_FRAUD_SCORE) {
            $reasons[] = 'Anti-spoof fraud score too high (' . $fraudScoreClient . ')';
        }

        $textual = (!empty($checks['gov_keywords']['pass']) || !empty($checks['mrz']['pass']));
        if ($ocrRan && !$textual) {
            $reasons[] = 'Insufficient government text or valid MRZ';
        }

        $clientValid = (bool) ($data['valid'] ?? false);
        if ($clientValid && !empty($reasons)) {
            $reasons[] = 'Client marked valid but server checks failed';
        }

        $approved = empty($reasons) && $clientValid;

        return [
            'approved' => $approved,
            'valid' => $approved,
            'rejection_reasons' => $reasons,
            'server_confidence' => $confidence,
            'fraud_score' => $fraudScore,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function validateLiveness(array $data): array
    {
        $reasons = [];
        $score = (float) ($data['score'] ?? 0);
        $checks = $data['checks'] ?? [];

        if (!is_array($checks)) {
            return self::reject(['Invalid liveness checks payload']);
        }

        if (($checks['method'] ?? '') !== '8_color_random_reflection') {
            $reasons[] = 'Invalid liveness method';
        }

        $matches = (int) ($checks['matches'] ?? 0);
        $threshold = (int) ($checks['threshold'] ?? 4);
        if ($matches < $threshold) {
            $reasons[] = "Colour reflection matches ({$matches}) below threshold ({$threshold})";
        }

        if ($score < 50) {
            $reasons[] = 'Liveness score below server minimum (50%)';
        }

        $hash = (string) ($data['color_sequence_hash'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            $reasons[] = 'Invalid color_sequence_hash';
        }

        $clientPassed = (bool) ($data['passed'] ?? false);
        if ($clientPassed && !empty($reasons)) {
            $reasons[] = 'Client marked passed but server liveness checks failed';
        }

        return [
            'approved' => empty($reasons) && $clientPassed,
            'rejection_reasons' => $reasons,
        ];
    }
}