<?php

declare(strict_types=1);

namespace FixIt\Services;

/**
 * Minimal SMTP mailer (STARTTLS + AUTH LOGIN) using stream sockets — avoids a
 * PHPMailer dependency. Config lives server-side in .env:
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_EMAIL, SMTP_FROM_NAME
 */
final class MailService
{
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $this->host = (string) ($_ENV['SMTP_HOST'] ?? '');
        $this->port = (int) ($_ENV['SMTP_PORT'] ?? 587);
        $this->user = (string) ($_ENV['SMTP_USER'] ?? '');
        $this->pass = (string) ($_ENV['SMTP_PASS'] ?? '');
        $this->fromEmail = (string) ($_ENV['SMTP_FROM_EMAIL'] ?? $this->user);
        $this->fromName = (string) ($_ENV['SMTP_FROM_NAME'] ?? 'FixIt');
    }

    public static function isConfigured(): bool
    {
        return ($_ENV['SMTP_HOST'] ?? '') !== ''
            && ($_ENV['SMTP_USER'] ?? '') !== ''
            && ($_ENV['SMTP_PASS'] ?? '') !== '';
    }

    public function send(string $toEmail, string $subject, string $textBody): void
    {
        if (!self::isConfigured()) {
            throw new \RuntimeException('SMTP is not configured');
        }

        // The mail server presents a cert for a different CN (self-hosted relay),
        // so peer-name verification is relaxed for the STARTTLS upgrade.
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);
        $fp = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$fp) {
            throw new \RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($fp, 20);

        try {
            $this->expect($fp, 220);
            $this->cmd($fp, 'EHLO fixit.olgtx.com', 250);
            $this->cmd($fp, 'STARTTLS', 220);

            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('STARTTLS negotiation failed');
            }

            $this->cmd($fp, 'EHLO fixit.olgtx.com', 250);
            $this->cmd($fp, 'AUTH LOGIN', 334);
            $this->cmd($fp, base64_encode($this->user), 334);
            $this->cmd($fp, base64_encode($this->pass), 235);

            $this->cmd($fp, "MAIL FROM:<{$this->fromEmail}>", 250);
            $this->cmd($fp, "RCPT TO:<{$toEmail}>", 250);
            $this->cmd($fp, 'DATA', 354);

            $headers = [
                'From: ' . $this->encodeHeaderName($this->fromName) . " <{$this->fromEmail}>",
                "To: <{$toEmail}>",
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'Date: ' . date('r'),
            ];
            $data = implode("\r\n", $headers) . "\r\n\r\n"
                . $this->dotStuff($textBody) . "\r\n.";
            $this->cmd($fp, $data, 250);

            $this->cmd($fp, 'QUIT', 221);
        } finally {
            fclose($fp);
        }
    }

    private function cmd($fp, string $line, int $expectedCode): void
    {
        fwrite($fp, $line . "\r\n");
        $this->expect($fp, $expectedCode);
    }

    private function expect($fp, int $expectedCode): void
    {
        $response = '';
        while (($line = fgets($fp, 515)) !== false) {
            $response .= $line;
            // Multi-line replies have a '-' after the code; final line has a space.
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr(trim($response), 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException("SMTP error: expected {$expectedCode}, got " . trim($response));
        }
    }

    private function dotStuff(string $body): string
    {
        $body = str_replace(["\r\n", "\r", "\n"], "\r\n", $body);
        return preg_replace('/^\./m', '..', $body) ?? $body;
    }

    private function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7e]/', $value)
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    private function encodeHeaderName(string $name): string
    {
        return $this->encodeHeader($name);
    }
}
