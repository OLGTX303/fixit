<?php

declare(strict_types=1);

namespace FixIt\Support;

/**
 * Slider puzzle captcha — server holds the answer; images generated server-side.
 */
final class SliderCaptchaService
{
    private const TTL_SECONDS = 300;
    private const TOLERANCE_PX = 8;
    private const MIN_DRAG_MS = 280;
    private const WIDTH = 300;
    private const HEIGHT = 160;
    private const PIECE_SIZE = 44;

    /** @return array<string,mixed> */
    public function create(string $ip): array
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('GD extension required for captcha images');
        }

        $id = bin2hex(random_bytes(16));
        $pieceY = random_int(24, self::HEIGHT - self::PIECE_SIZE - 24);
        $targetX = random_int(56, self::WIDTH - self::PIECE_SIZE - 56);
        $seed = random_int(1, 999_999);

        [$background, $piece] = $this->renderImages($seed, $targetX, $pieceY);

        $this->store($id, [
            'target_x' => $targetX,
            'piece_y' => $pieceY,
            'ip' => $ip,
            'created_at' => time(),
            'verified' => false,
            'pass_token' => null,
        ]);

        return [
            'captcha_id' => $id,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'piece_size' => self::PIECE_SIZE,
            'piece_y' => $pieceY,
            'background' => $background,
            'piece' => $piece,
            'expires_in' => self::TTL_SECONDS,
        ];
    }

    /**
     * @return array{verified:bool,pass_token:?string,error:?string}
     */
    public function verify(string $id, int $submittedX, string $ip, int $dragMs): array
    {
        $record = $this->load($id);
        if (!$record) {
            return ['verified' => false, 'pass_token' => null, 'error' => 'Captcha expired. Refresh and try again.'];
        }

        if (($record['ip'] ?? '') !== $ip) {
            $this->delete($id);
            return ['verified' => false, 'pass_token' => null, 'error' => 'Captcha session mismatch.'];
        }

        if (time() - (int) $record['created_at'] > self::TTL_SECONDS) {
            $this->delete($id);
            return ['verified' => false, 'pass_token' => null, 'error' => 'Captcha expired. Refresh and try again.'];
        }

        if (!empty($record['verified'])) {
            return ['verified' => false, 'pass_token' => null, 'error' => 'Captcha already used. Refresh and try again.'];
        }

        if ($dragMs < self::MIN_DRAG_MS) {
            return ['verified' => false, 'pass_token' => null, 'error' => 'Slide more slowly to verify you are human.'];
        }

        $targetX = (int) $record['target_x'];
        if (abs($submittedX - $targetX) > self::TOLERANCE_PX) {
            return ['verified' => false, 'pass_token' => null, 'error' => 'Puzzle not aligned. Adjust the slider and try again.'];
        }

        $passToken = bin2hex(random_bytes(24));
        $record['verified'] = true;
        $record['pass_token'] = $passToken;
        $record['verified_at'] = time();
        $this->store($id, $record);

        return ['verified' => true, 'pass_token' => $passToken, 'error' => null];
    }

    public function consumePassToken(string $id, string $passToken, string $ip): bool
    {
        $record = $this->load($id);
        if (!$record || empty($record['verified']) || empty($record['pass_token'])) {
            return false;
        }

        if (($record['ip'] ?? '') !== $ip || !hash_equals((string) $record['pass_token'], $passToken)) {
            return false;
        }

        if (time() - (int) ($record['verified_at'] ?? 0) > 120) {
            $this->delete($id);
            return false;
        }

        $this->delete($id);
        return true;
    }

    /** @return array{0:string,1:string} base64 PNG pair */
    private function renderImages(int $seed, int $targetX, int $pieceY): array
    {
        $w = self::WIDTH;
        $h = self::HEIGHT;
        $ps = self::PIECE_SIZE;

        $bg = imagecreatetruecolor($w, $h);
        $piece = imagecreatetruecolor($ps, $h);
        imagealphablending($bg, true);
        imagesavealpha($bg, true);
        imagealphablending($piece, false);
        imagesavealpha($piece, true);

        $transparent = imagecolorallocatealpha($piece, 0, 0, 0, 127);
        imagefill($piece, 0, 0, $transparent);
        imagealphablending($piece, true);

        $this->paintBackground($bg, $w, $h, $seed);

        $pieceStrip = imagecreatetruecolor($ps, $h);
        imagecopy($pieceStrip, $bg, 0, 0, $targetX, 0, $ps, $h);

        $holeColor = imagecolorallocatealpha($bg, 30, 41, 59, 60);
        $borderColor = imagecolorallocate($bg, 255, 255, 255);
        imagefilledrectangle($bg, $targetX, $pieceY, $targetX + $ps - 1, $pieceY + $ps - 1, $holeColor);
        imagerectangle($bg, $targetX, $pieceY, $targetX + $ps - 1, $pieceY + $ps - 1, $borderColor);

        imagecopy($piece, $pieceStrip, 0, $pieceY, 0, $pieceY, $ps, $ps);
        imagerectangle($piece, 0, $pieceY, $ps - 1, $pieceY + $ps - 1, $borderColor);

        imagedestroy($pieceStrip);

        return [$this->toDataUri($bg), $this->toDataUri($piece)];
    }

    private function paintBackground(\GdImage $img, int $w, int $h, int $seed): void
    {
        $rng = $seed;
        for ($y = 0; $y < $h; $y++) {
            $ratio = $y / max(1, $h - 1);
            $r = (int) (45 + 80 * $ratio + ($rng % 17));
            $g = (int) (95 + 60 * (1 - $ratio) + ($rng % 23));
            $b = (int) (170 + 40 * $ratio + ($rng % 19));
            $line = imagecolorallocate($img, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
            imageline($img, 0, $y, $w, $y, $line);
        }

        for ($i = 0; $i < 18; $i++) {
            $rng = ($rng * 1103515245 + 12345) & 0x7fffffff;
            $x1 = $rng % $w;
            $rng = ($rng * 1103515245 + 12345) & 0x7fffffff;
            $y1 = $rng % $h;
            $rng = ($rng * 1103515245 + 12345) & 0x7fffffff;
            $x2 = $rng % $w;
            $rng = ($rng * 1103515245 + 12345) & 0x7fffffff;
            $y2 = $rng % $h;
            $c = imagecolorallocatealpha($img, 255, 255, 255, 90);
            imageline($img, $x1, $y1, $x2, $y2, $c);
        }

        for ($i = 0; $i < 40; $i++) {
            $rng = ($rng * 1103515245 + 12345) & 0x7fffffff;
            $cx = $rng % $w;
            $rng = ($rng * 1103515245 + 12345) & 0x7fffffff;
            $cy = $rng % $h;
            $dot = imagecolorallocatealpha($img, 255, 102, 53, 100);
            imagefilledellipse($img, $cx, $cy, 4, 4, $dot);
        }
    }

    private function toDataUri(\GdImage $img): string
    {
        ob_start();
        imagepng($img);
        $raw = (string) ob_get_clean();
        imagedestroy($img);
        return 'data:image/png;base64,' . base64_encode($raw);
    }

    /** @param array<string,mixed> $data */
    private function store(string $id, array $data): void
    {
        file_put_contents($this->path($id), json_encode($data), LOCK_EX);
    }

    /** @return ?array<string,mixed> */
    private function load(string $id): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            return null;
        }
        $path = $this->path($id);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function delete(string $id): void
    {
        $path = $this->path($id);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function path(string $id): string
    {
        return sys_get_temp_dir() . '/fixit_captcha_' . $id . '.json';
    }
}