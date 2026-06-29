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

        $sent = @mail($to, self::encodeSubject($subject), $message, implode("\r\n", $headers));

        $savedPath = null;
        if (!$sent && !empty($attachments)) {
            $savedPath = self::saveLocally($attachments, $subject);
        }

        return [
            'sent' => $sent,
            'saved_path' => $savedPath,
            'to' => $to,
        ];
    }

    private static function saveLocally(array $attachments, string $subject): ?string
    {
        $dir = DATA_PATH . '/reports';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $slug = preg_replace('/[^a-z0-9_-]+/i', '-', $subject);
        $saved = [];
        foreach ($attachments as $attachment) {
            $path = $dir . '/' . date('Y-m-d_His') . '_' . $attachment['filename'];
            file_put_contents($path, $attachment['content']);
            $saved[] = $path;
        }

        return $saved[0] ?? null;
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
