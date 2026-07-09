<?php
declare(strict_types=1);

class OrderReceipt
{
    private const LINE_WIDTH = 28;

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
            array_push($lines, ...self::linesLabelValue('Precio unit.:', self::formatCLP((float) ($item['unit_price'] ?? 0))));

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
                $priceStr = $extraPrice > 0 ? '(+' . self::formatCLP($extraPrice) . ')' : '';
                if ($priceStr !== '') {
                    array_push($lines, ...self::linesLabelValue("+ {$extraName}", $priceStr));
                } else {
                    array_push($lines, ...self::wrapLines("+ {$extraName}"));
                }
            }

            if (!empty($item['notes'])) {
                array_push($lines, ...self::wrapLines('Nota: ' . $item['notes']));
            }

            array_push($lines, ...self::linesLabelValue('Subtotal:', self::formatCLP((float) ($item['line_total'] ?? 0))));
            $lines[] = '';
        }

        $lines[] = str_repeat('-', self::LINE_WIDTH);
        $subtotal = (float) ($order['subtotal'] ?? $order['total'] ?? 0);
        $tipAmount = (float) ($order['tip_amount'] ?? 0);
        array_push($lines, ...self::linesLabelValue('Subtotal prod.:', self::formatCLP($subtotal)));
        if ($tipAmount > 0) {
            $tipPercent = (float) ($order['tip_percent'] ?? ($subtotal > 0 ? ($tipAmount / $subtotal) * 100 : 10));
            $tipLabel = ($order['tip_mode'] ?? '') === 'manual'
                ? 'Propina'
                : 'Propina ' . (int) round($tipPercent) . '%';
            array_push($lines, ...self::linesLabelValue($tipLabel . ':', self::formatCLP($tipAmount)));
        }
        array_push($lines, ...self::linesLabelValue('TOTAL:', self::formatCLP((float) ($order['total'] ?? 0))));
        $lines[] = str_repeat('-', self::LINE_WIDTH);
        $lines[] = 'Gracias por su preferencia';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private static function linesLabelValue(string $label, string $value, ?int $width = null): array
    {
        $width = $width ?? self::LINE_WIDTH;
        $labelLen = mb_strlen($label, 'UTF-8');
        $valueLen = mb_strlen($value, 'UTF-8');

        if ($labelLen + $valueLen <= $width) {
            return [self::padLine($label, $value, $width)];
        }

        $lines = self::wrapLines($label, $width);
        $lastIdx = count($lines) - 1;
        $lastLine = $lines[$lastIdx];
        $lastLen = mb_strlen($lastLine, 'UTF-8');

        if ($lastLen + $valueLen <= $width) {
            $lines[$lastIdx] = self::padLine($lastLine, $value, $width);
            return $lines;
        }

        $lines[] = self::alignRightValue($value, $width);
        return $lines;
    }

    private static function padLine(string $left, string $right, int $width): string
    {
        $leftLen = mb_strlen($left, 'UTF-8');
        $rightLen = mb_strlen($right, 'UTF-8');
        $spaces = max(1, $width - $leftLen - $rightLen);
        return $left . str_repeat(' ', $spaces) . $right;
    }

    private static function alignRightValue(string $value, int $width): string
    {
        $valueLen = mb_strlen($value, 'UTF-8');
        if ($valueLen >= $width) {
            return $value;
        }
        return str_repeat(' ', $width - $valueLen) . $value;
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
