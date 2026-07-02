<?php
declare(strict_types=1);

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
        $headers = [
            'MIME-Version: 1.0',
            'From: ' . self::encodeAddress($fromName, $fromEmail),
            'Reply-To: ' . $fromEmail,
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];

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

        $message .= "--{$boundary}--";

        $additionalParams = self::buildEnvelopeSender($fromEmail);
        $sent = @mail($to, self::encodeSubject($subject), $message, implode("\r\n", $headers), $additionalParams);

        $mailError = error_get_last();
        $savedPath = null;

        if (!$sent) {
            if (!empty($attachments)) {
                $savedPath = self::saveAttachmentsLocally($attachments, $subject);
            }
            if ($savedPath === null && trim($body) !== '') {
                $savedPath = self::savePlainBodyLocally($body, $subject);
            }
        }

        return [
            'sent' => $sent,
            'saved_path' => $savedPath,
            'to' => $to,
            'from' => $fromEmail,
            'error' => $sent ? null : ($mailError['message'] ?? 'mail() no pudo enviar el correo'),
        ];
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
