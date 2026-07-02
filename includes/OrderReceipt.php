<?php
declare(strict_types=1);

class OrderReceipt
{
    public static function toPlainText(array $order, array $items, string $cafeName): string
    {
        $lines = [];
        $lines[] = mb_strtoupper($cafeName, 'UTF-8');
        $lines[] = 'COMANDA DE PEDIDO';
        if (!empty($order['id'])) {
            $lines[] = 'Comanda #' . $order['id'];
        }
        $lines[] = str_repeat('-', 32);
        $createdAt = $order['created_at'] ?? date('Y-m-d H:i:s');
        $lines[] = 'Fecha: ' . date('d/m/Y H:i', strtotime($createdAt));

        if (!empty($order['client_name'])) {
            $lines[] = 'Cliente: ' . $order['client_name'];
        }

        if (!empty($order['table_number'])) {
            $isTakeaway = strtoupper((string) $order['table_number']) === 'PL';
            $lines[] = $isTakeaway ? 'PARA LLEVAR (PL)' : 'Mesa: ' . $order['table_number'];
        }
        if (!empty($order['waiter_name'])) {
            $isTakeaway = !empty($order['table_number']) && strtoupper((string) $order['table_number']) === 'PL';
            $lines[] = ($isTakeaway ? 'Cajero' : 'Mesero') . ': ' . $order['waiter_name'];
        }

        $lines[] = str_repeat('-', 32);

        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $name = $item['item_name'] ?? $item['name'] ?? 'Producto';
            $lines[] = "{$qty}x {$name}";
            $lines[] = '   Precio unit.: ' . self::formatCLP((float) ($item['unit_price'] ?? 0));

            $removed = $item['removed_ingredients'] ?? [];
            if (is_string($removed)) {
                $removed = json_decode($removed, true) ?: [];
            }
            if (!empty($removed)) {
                $lines[] = '   Sin: ' . implode(', ', $removed);
            }

            $extras = $item['added_extras'] ?? [];
            if (is_string($extras)) {
                $extras = json_decode($extras, true) ?: [];
            }
            foreach ($extras as $extra) {
                $extraName = is_array($extra) ? ($extra['name'] ?? '') : $extra;
                $extraPrice = is_array($extra) ? (float) ($extra['price'] ?? 0) : 0;
                $priceStr = $extraPrice > 0 ? ' (+' . self::formatCLP($extraPrice) . ')' : '';
                $lines[] = "   + {$extraName}{$priceStr}";
            }

            if (!empty($item['notes'])) {
                $lines[] = '   Nota: ' . $item['notes'];
            }

            $lines[] = '   Subtotal: ' . self::formatCLP((float) ($item['line_total'] ?? 0));
            $lines[] = '';
        }

        $lines[] = str_repeat('-', 32);
        $subtotal = (float) ($order['subtotal'] ?? $order['total'] ?? 0);
        $tipAmount = (float) ($order['tip_amount'] ?? 0);
        $lines[] = 'Subtotal productos: ' . self::formatCLP($subtotal);
        if ($tipAmount > 0) {
            $tipPercent = (float) ($order['tip_percent'] ?? ($subtotal > 0 ? ($tipAmount / $subtotal) * 100 : 10));
            $tipLabel = ($order['tip_mode'] ?? '') === 'manual'
                ? 'Propina'
                : 'Propina ' . (int) round($tipPercent) . '%';
            $lines[] = "{$tipLabel}: " . self::formatCLP($tipAmount);
        }
        $lines[] = 'TOTAL: ' . self::formatCLP((float) ($order['total'] ?? 0));
        $lines[] = str_repeat('-', 32);
        $lines[] = 'Gracias por su preferencia';

        return implode("\n", $lines);
    }

    public static function formatCLP(float $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }
}
