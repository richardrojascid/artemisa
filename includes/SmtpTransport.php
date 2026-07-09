<?php
declare(strict_types=1);

class SmtpTransport
{
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private int $timeout;

    public function __construct(
        string $host,
        int $port,
        string $encryption,
        string $username,
        string $password,
        int $timeout = 20
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->encryption = strtolower($encryption);
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
    }

    /**
     * @param list<string> $recipients
     */
    public function send(
        array $recipients,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $mimeBody,
        array $mimeHeaders
    ): void {
        $socket = $this->connect();
        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO ' . $this->getEhloHost(), [250]);
            $this->maybeStartTls($socket);
            if ($this->encryption === 'tls') {
                $this->command($socket, 'EHLO ' . $this->getEhloHost(), [250]);
            }
            $this->authenticate($socket);

            $fromEmail = $this->sanitizeEmail($fromEmail);
            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250, 251]);

            foreach ($recipients as $recipient) {
                $recipient = $this->sanitizeEmail($recipient);
                $this->command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            }

            $this->command($socket, 'DATA', [354]);

            $headers = array_merge($mimeHeaders, [
                'To: ' . implode(', ', $recipients),
                'Subject: ' . $this->encodeHeader($subject),
                'Date: ' . date('r'),
                'Message-ID: <' . uniqid('artemisa.', true) . '@' . $this->getEhloHost() . '>',
            ]);

            $data = implode("\r\n", $headers) . "\r\n\r\n" . $mimeBody;
            $data = preg_replace("/\r\n\./", "\r\n..", $data) ?? $data;
            fwrite($socket, $data . "\r\n.\r\n");
            $this->expect($socket, 250);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @return resource */
    private function connect()
    {
        $remote = $this->encryption === 'ssl'
            ? 'ssl://' . $this->host . ':' . $this->port
            : $this->host . ':' . $this->port;

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ])
        );

        if ($socket === false) {
            throw new RuntimeException('No se pudo conectar a SMTP ' . $this->host . ':' . $this->port . ' — ' . $errstr);
        }

        stream_set_timeout($socket, $this->timeout);
        return $socket;
    }

    /** @param resource $socket */
    private function maybeStartTls($socket): void
    {
        if ($this->encryption !== 'tls') {
            return;
        }

        $this->command($socket, 'STARTTLS', [220]);
        $cryptoOk = stream_socket_enable_crypto(
            $socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );
        if ($cryptoOk !== true) {
            throw new RuntimeException('No se pudo iniciar TLS con el servidor SMTP.');
        }
    }

    /** @param resource $socket */
    private function authenticate($socket): void
    {
        if ($this->username === '' || $this->password === '') {
            return;
        }

        try {
            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($this->username), [334]);
            $this->command($socket, base64_encode($this->password), [235]);
            return;
        } catch (RuntimeException $loginError) {
            $plain = base64_encode("\0{$this->username}\0{$this->password}");
            $this->command($socket, 'AUTH PLAIN ' . $plain, [235]);
        }
    }

    /**
     * @param resource $socket
     * @param list<int> $expectedCodes
     */
    private function command($socket, string $command, array $expectedCodes): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expectedCodes, $command);
    }

    /**
     * @param resource $socket
     * @param int|list<int> $expectedCodes
     */
    private function expect($socket, $expectedCodes, ?string $command = null): void
    {
        $expectedCodes = is_array($expectedCodes) ? $expectedCodes : [$expectedCodes];
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('Sin respuesta del servidor SMTP.');
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            $prefix = $command ? "Comando SMTP fallido ({$command}). " : '';
            throw new RuntimeException($prefix . trim($response));
        }
    }

    private function getEhloHost(): string
    {
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        if (!is_string($host) || $host === '') {
            return 'localhost';
        }
        return preg_replace('/[^a-zA-Z0-9.-]/', '', $host) ?: 'localhost';
    }

    private function sanitizeEmail(string $email): string
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Correo inválido: ' . $email);
        }
        return $email;
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
