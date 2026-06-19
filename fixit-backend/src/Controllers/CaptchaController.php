<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Support\ResponseHelper;
use FixIt\Support\SliderCaptchaService;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CaptchaController
{
    public function challenge(Request $request, Response $response): Response
    {
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        try {
            $service = new SliderCaptchaService();
            $payload = $service->create($ip);
            return ResponseHelper::json($response, $payload);
        } catch (\Throwable $e) {
            return ResponseHelper::error($response, 'Captcha unavailable', 503);
        }
    }

    public function verify(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['captcha_id', 'captcha_x', 'drag_ms']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $service = new SliderCaptchaService();
        $result = $service->verify(
            (string) $data['captcha_id'],
            (int) $data['captcha_x'],
            $ip,
            (int) $data['drag_ms']
        );

        if (!$result['verified']) {
            return ResponseHelper::error($response, (string) $result['error'], 422);
        }

        return ResponseHelper::json($response, [
            'verified' => true,
            'captcha_id' => $data['captcha_id'],
            'captcha_pass_token' => $result['pass_token'],
        ]);
    }
}