<?php
declare(strict_types=1);

require_once __DIR__ . '/SmtpTransport.php';

class Mailer
{
    /**
     * @param array<int, array{filename:string, content:string, mime:string}> $attachments
     */
    public static function send(string $to, string $subject, string $body, array $attachments = [], ?string $fromEmail = null): array
    {
        $fromEmail = $fromEmail ?: (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@localhost');
        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : APP_NAME;

        $boundary = '=_Part_' . md5(uniqid((string) mt_rand(), true));
        $mimeBody = self::buildMimeBody($body, $attachments, $boundary);
        $mimeHeaders = [
            'MIME-Version: 1.0',
            'From: ' . self::encodeAddress($fromName, $fromEmail),
            'Reply-To: ' . $fromEmail,
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];

        if (self::shouldUseSmtp()) {
            return array_merge(self::getStatus(), self::sendViaSmtp($to, $subject, $mimeBody, $mimeHeaders, $fromEmail, $attachments, $body));
        }

        if (!self::isMailConfigPresent()) {
            $savedPath = self::saveFallback($attachments, $body, $subject);
            return array_merge(self::getStatus(), [
                'sent' => false,
                'saved_path' => $savedPath,
                'to' => $to,
                'from' => $fromEmail,
                'error' => 'Falta includes/mail.config.php. Copia mail.config.example.php y configura SMTP_PASS de Titan.',
                'transport' => 'none',
            ]);
        }

        if (defined('MAIL_DRIVER') && MAIL_DRIVER === 'smtp' && (!defined('SMTP_PASS') || SMTP_PASS === '')) {
            $savedPath = self::saveFallback($attachments, $body, $subject);
            return array_merge(self::getStatus(), [
                'sent' => false,
                'saved_path' => $savedPath,
                'to' => $to,
                'from' => $fromEmail,
                'error' => 'SMTP_PASS vacío en mail.config.php.',
                'transport' => 'none',
            ]);
        }

        return array_merge(self::getStatus(), self::sendViaMailFunction($to, $subject, $mimeBody, $mimeHeaders, $fromEmail, $attachments, $body));
    }

    public static function getStatus(): array
    {
        return [
            'mail_config_exists' => self::isMailConfigPresent(),
            'smtp_ready' => self::shouldUseSmtp(),
            'driver' => self::shouldUseSmtp() ? 'smtp' : (defined('MAIL_DRIVER') ? MAIL_DRIVER : 'mail'),
            'smtp_host' => defined('SMTP_HOST') ? SMTP_HOST : null,
            'smtp_port' => defined('SMTP_PORT') ? (int) SMTP_PORT : null,
            'smtp_user' => defined('SMTP_USER') ? SMTP_USER : null,
            'smtp_pass_set' => defined('SMTP_PASS') && SMTP_PASS !== '',
            'notify_email' => defined('ORDER_NOTIFY_EMAIL') ? ORDER_NOTIFY_EMAIL : null,
            'from_email' => defined('MAIL_FROM') ? MAIL_FROM : null,
        ];
    }

    private static function isMailConfigPresent(): bool
    {
        return is_readable(__DIR__ . '/mail.config.php');
    }

    private static function shouldUseSmtp(): bool
    {
        if (!defined('MAIL_DRIVER') || MAIL_DRIVER !== 'smtp') {
            return false;
        }
        if (!defined('SMTP_HOST') || SMTP_HOST === '') {
            return false;
        }
        if (!defined('SMTP_USER') || SMTP_USER === '') {
            return false;
        }
        return defined('SMTP_PASS') && SMTP_PASS !== '';
    }

    /**
     * @param array<int, array{filename:string, content:string, mime:string}> $attachments
     */
    private static function sendViaSmtp(
        string $to,
        string $subject,
        string $mimeBody,
        array $mimeHeaders,
        string $fromEmail,
        array $attachments,
        string $body
    ): array {
        try {
            $fromEmail = SMTP_USER;
            $transport = new SmtpTransport(
                SMTP_HOST,
                defined('SMTP_PORT') ? (int) SMTP_PORT : 587,
                defined('SMTP_ENCRYPTION') ? (string) SMTP_ENCRYPTION : 'tls',
                SMTP_USER,
                SMTP_PASS,
                defined('SMTP_TIMEOUT') ? (int) SMTP_TIMEOUT : 20
            );

            $transport->send(
                [trim($to)],
                $fromEmail,
                defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : APP_NAME,
                $subject,
                $mimeBody,
                $mimeHeaders
            );

            return [
                'sent' => true,
                'saved_path' => null,
                'to' => $to,
                'from' => $fromEmail,
                'error' => null,
                'transport' => 'smtp',
            ];
        } catch (Throwable $e) {
            $savedPath = self::saveFallback($attachments, $body, $subject);

            return [
                'sent' => false,
                'saved_path' => $savedPath,
                'to' => $to,
                'from' => $fromEmail,
                'error' => $e->getMessage(),
                'transport' => 'smtp',
            ];
        }
    }

    /**
     * @param array<int, array{filename:string, content:string, mime:string}> $attachments
     */
    private static function sendViaMailFunction(
        string $to,
        string $subject,
        string $mimeBody,
        array $mimeHeaders,
        string $fromEmail,
        array $attachments,
        string $body
    ): array {
        $additionalParams = self::buildEnvelopeSender($fromEmail);
        $sent = @mail($to, self::encodeSubject($subject), $mimeBody, implode("\r\n", $mimeHeaders), $additionalParams);

        $mailError = error_get_last();
        $savedPath = null;

        if (!$sent) {
            $savedPath = self::saveFallback($attachments, $body, $subject);
        }

        return [
            'sent' => $sent,
            'saved_path' => $savedPath,
            'to' => $to,
            'from' => $fromEmail,
            'error' => $sent ? null : ($mailError['message'] ?? 'mail() no pudo enviar el correo'),
            'transport' => 'mail',
        ];
    }

    /**
     * @param array<int, array{filename:string, content:string, mime:string}> $attachments
     */
    private static function buildMimeBody(string $body, array $attachments, string $boundary): string
    {
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $body . "\r\n";

        foreach ($attachments as $attachment) {
            $message .= "--{$boundary}\r\n";
            $message .= 'Content-Type: ' . $attachment['mime'] . '; name="' . $attachment['filename'] . "\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= 'Content-Disposition: attachment; filename="' . $attachment['filename'] . "\"\r\n\r\n";
            $message .= chunk_split(base64_encode($attachment['content'])) . "\r\n";
        }

        return $message . "--{$boundary}--";
    }

    /**
     * @param array<int, array{filename:string, content:string, mime:string}> $attachments
     */
    private static function saveFallback(array $attachments, string $body, string $subject): ?string
    {
        if (!empty($attachments)) {
            return self::saveAttachmentsLocally($attachments, $subject);
        }
        if (trim($body) !== '') {
            return self::savePlainBodyLocally($body, $subject);
        }
        return null;
    }

    private static function buildEnvelopeSender(string $fromEmail): string
    {
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return '-f' . $fromEmail;
    }

    private static function saveAttachmentsLocally(array $attachments, string $subject): ?string
    {
        $dir = self::ensureReportsDir();
        $saved = [];
        foreach ($attachments as $attachment) {
            $path = $dir . '/' . self::buildReportFilename($subject, $attachment['filename']);
            file_put_contents($path, $attachment['content']);
            $saved[] = $path;
        }

        return $saved[0] ?? null;
    }

    private static function savePlainBodyLocally(string $body, string $subject): ?string
    {
        $dir = self::ensureReportsDir();
        $path = $dir . '/' . self::buildReportFilename($subject, 'comanda.txt');
        file_put_contents($path, $body);

        return $path;
    }

    private static function ensureReportsDir(): string
    {
        $dir = DATA_PATH . '/reports';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    private static function buildReportFilename(string $subject, string $suffix): string
    {
        $slug = preg_replace('/[^a-z0-9_-]+/i', '-', $subject) ?: 'comanda';

        return date('Y-m-d_His') . '_' . $slug . '_' . $suffix;
    }

    private static function encodeSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    private static function encodeAddress(string $name, string $email): string
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
    }
}
