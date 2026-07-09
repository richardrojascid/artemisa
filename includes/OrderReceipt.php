<?php
declare(strict_types=1);

class OrderReceipt
{
    private const LINE_WIDTH = 24;

    public static function toPlainText(array $order, array $items, string $cafeName): string
    {
        $lines = [];
        foreach (self::wrapLines(mb_strtoupper($cafeName, 'UTF-8')) as $line) {
            $lines[] = $line;
        }
        $lines[] = 'COMANDA DE PEDIDO';
        if (!empty($order['id'])) {
            $lines[] = 'Comanda #' . $order['id'];
        }
        $lines[] = str_repeat('-', self::LINE_WIDTH);
        $createdAt = $order['created_at'] ?? date('Y-m-d H:i:s');
        array_push($lines, ...self::wrapLines('Fecha: ' . date('d/m/Y H:i', strtotime($createdAt))));

        if (!empty($order['client_name'])) {
            array_push($lines, ...self::wrapLines('Cliente: ' . $order['client_name']));
        }

        if (!empty($order['table_number'])) {
            $isTakeaway = strtoupper((string) $order['table_number']) === 'PL';
            $lines[] = $isTakeaway ? 'PARA LLEVAR (PL)' : 'Mesa: ' . $order['table_number'];
        }
        if (!empty($order['waiter_name'])) {
            $isTakeaway = !empty($order['table_number']) && strtoupper((string) $order['table_number']) === 'PL';
            array_push($lines, ...self::wrapLines(($isTakeaway ? 'Cajero' : 'Mesero') . ': ' . $order['waiter_name']));
        }

        $lines[] = str_repeat('-', self::LINE_WIDTH);

        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $name = $item['item_name'] ?? $item['name'] ?? 'Producto';
            array_push($lines, ...self::wrapLines("{$qty}x {$name}"));
            array_push($lines, ...self::wrapLines('Precio unit.: ' . self::formatCLP((float) ($item['unit_price'] ?? 0))));

            $removed = $item['removed_ingredients'] ?? [];
            if (is_string($removed)) {
                $removed = json_decode($removed, true) ?: [];
            }
            if (!empty($removed)) {
                array_push($lines, ...self::wrapLines('Sin: ' . implode(', ', $removed)));
            }

            $extras = $item['added_extras'] ?? [];
            if (is_string($extras)) {
                $extras = json_decode($extras, true) ?: [];
            }
            foreach ($extras as $extra) {
                $extraName = is_array($extra) ? ($extra['name'] ?? '') : $extra;
                $extraPrice = is_array($extra) ? (float) ($extra['price'] ?? 0) : 0;
                $priceStr = $extraPrice > 0 ? ' (+' . self::formatCLP($extraPrice) . ')' : '';
                array_push($lines, ...self::wrapLines("+ {$extraName}{$priceStr}"));
            }

            if (!empty($item['notes'])) {
                array_push($lines, ...self::wrapLines('Nota: ' . $item['notes']));
            }

            array_push($lines, ...self::wrapLines('Subtotal: ' . self::formatCLP((float) ($item['line_total'] ?? 0))));
            $lines[] = '';
        }

        $lines[] = str_repeat('-', self::LINE_WIDTH);
        $subtotal = (float) ($order['subtotal'] ?? $order['total'] ?? 0);
        $tipAmount = (float) ($order['tip_amount'] ?? 0);
        array_push($lines, ...self::wrapLines('Subtotal prod.: ' . self::formatCLP($subtotal)));
        if ($tipAmount > 0) {
            $tipPercent = (float) ($order['tip_percent'] ?? ($subtotal > 0 ? ($tipAmount / $subtotal) * 100 : 10));
            $tipLabel = ($order['tip_mode'] ?? '') === 'manual'
                ? 'Propina'
                : 'Propina ' . (int) round($tipPercent) . '%';
            array_push($lines, ...self::wrapLines("{$tipLabel}: " . self::formatCLP($tipAmount)));
        }
        array_push($lines, ...self::wrapLines('TOTAL: ' . self::formatCLP((float) ($order['total'] ?? 0))));
        $lines[] = str_repeat('-', self::LINE_WIDTH);
        $lines[] = 'Gracias por su preferencia';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private static function wrapLines(string $text, ?int $width = null): array
    {
        $width = $width ?? self::LINE_WIDTH;
        $text = trim($text);
        if ($text === '') {
            return [''];
        }

        $lines = [];
        $words = preg_split('/\s+/u', $text) ?: [];
        $current = '';

        foreach ($words as $word) {
            if (mb_strlen($word, 'UTF-8') > $width) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }
                $offset = 0;
                $len = mb_strlen($word, 'UTF-8');
                while ($offset < $len) {
                    $lines[] = mb_substr($word, $offset, $width, 'UTF-8');
                    $offset += $width;
                }
                continue;
            }

            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate, 'UTF-8') <= $width) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    public static function formatCLP(float $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }
}
