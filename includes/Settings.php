<?php
declare(strict_types=1);

class Settings
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function ensureTable(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )
        ");
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->db->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string) $value : $default;
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->db->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
        $stmt->execute([$key, $value]);
    }

    public function getPinHash(): ?string
    {
        return $this->get('staff_pin_hash');
    }

    public function setPin(string $pin): void
    {
        $this->set('staff_pin_hash', password_hash($pin, PASSWORD_DEFAULT));
    }

    public function verifyPin(string $pin): bool
    {
        $hash = $this->getPinHash();
        if ($hash === null) {
            return false;
        }
        return password_verify($pin, $hash);
    }

    public function getCafeName(): string
    {
        return $this->get('cafe_name', APP_NAME) ?? APP_NAME;
    }

    public function getReportEmail(): string
    {
        return $this->get('report_email', REPORT_EMAIL_DEFAULT) ?? REPORT_EMAIL_DEFAULT;
    }

    public function getTipPercent(): float
    {
        return (float) ($this->get('tip_percent', (string) TIP_PERCENT_DEFAULT) ?? TIP_PERCENT_DEFAULT);
    }
}
