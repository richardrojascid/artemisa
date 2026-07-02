<?php
declare(strict_types=1);

require_once __DIR__ . '/OrderReceipt.php';
require_once __DIR__ . '/Mailer.php';

class OrderNotifier
{
    public static function sendOrderEmail(array $order, Settings $settings): array
    {
        $cafeName = $settings->getCafeName();
        $to = defined('ORDER_NOTIFY_EMAIL') ? ORDER_NOTIFY_EMAIL : $settings->getReportEmail();
        $body = OrderReceipt::toPlainText($order, $order['items'] ?? [], $cafeName);
        $orderId = $order['id'] ?? '?';

        return Mailer::send(
            $to,
            "Comanda #{$orderId} — {$cafeName}",
            $body
        );
    }
}
