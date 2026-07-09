<?php
declare(strict_types=1);

class EscPosPrinter
{
    private const ESC = "\x1b";
    private const GS = "\x1d";
    /** Ancho imprimible PT-210 58mm (U24): ~32 caracteres. */
    private const LINE_WIDTH = 32;

    public static function buildReceipt(array $order, array $items, string $cafeName = 'CAFÉ COMANDA'): string
    {
        $out = '';
        $out .= self::initialize();
        $out .= self::alignCenter();
        $out .= self::bold(true);
        foreach (self::wrapLines($cafeName) as $line) {
            $out .= self::text($line . "\n");
        }
        $out .= self::bold(false);
        $out .= self::text("COMANDA DE PEDIDO\n");
        if (!empty($order['id'])) {
            $out .= self::text('Comanda #' . $order['id'] . "\n");
        }
        $out .= self::separator();

        $out .= self::alignLeft();
        $createdAt = $order['created_at'] ?? date('Y-m-d H:i:s');
        $out .= self::textWrapped('Fecha: ' . date('d/m/Y H:i', strtotime($createdAt)));

        if (!empty($order['client_name'])) {
            $out .= self::textWrapped('Cliente: ' . $order['client_name']);
        }

        if (!empty($order['table_number'])) {
            $isTakeaway = strtoupper((string) $order['table_number']) === 'PL';
            if ($isTakeaway) {
                $out .= self::text("PARA LLEVAR (PL)\n");
            } else {
                $out .= self::textWrapped('Mesa: ' . $order['table_number']);
            }
        }
        if (!empty($order['waiter_name'])) {
            $staffLabel = (!empty($order['table_number']) && strtoupper((string) $order['table_number']) === 'PL')
                ? 'Cajero'
                : 'Mesero';
            $out .= self::textWrapped($staffLabel . ': ' . $order['waiter_name']);
        }

        $out .= self::separator();

        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $name = $item['item_name'] ?? $item['name'] ?? 'Producto';
            $lineTotal = (float) ($item['line_total'] ?? 0);

            $out .= self::textWrapped("{$qty}x {$name}");

            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $out .= self::textLabelValue('Precio unit.:', self::formatCLP($unitPrice));

            $removed = $item['removed_ingredients'] ?? [];
            if (is_string($removed)) {
                $removed = json_decode($removed, true) ?: [];
            }
            if (!empty($removed)) {
                $out .= self::textWrapped('Sin: ' . implode(', ', $removed));
            }

            $extras = $item['added_extras'] ?? [];
            if (is_string($extras)) {
                $extras = json_decode($extras, true) ?: [];
            }
            if (!empty($extras)) {
                foreach ($extras as $extra) {
                    $extraName = is_array($extra) ? ($extra['name'] ?? '') : $extra;
                    $extraPrice = is_array($extra) ? (float) ($extra['price'] ?? 0) : 0;
                    $priceStr = $extraPrice > 0 ? '(+' . self::formatCLP($extraPrice) . ')' : '';
                    if ($priceStr !== '') {
                        $out .= self::textLabelValue("+ {$extraName}", $priceStr);
                    } else {
                        $out .= self::textWrapped("+ {$extraName}");
                    }
                }
            }

            if (!empty($item['notes'])) {
                $out .= self::textWrapped('Nota: ' . $item['notes']);
            }

            $out .= self::textLabelValue('Subtotal:', self::formatCLP($lineTotal));
            $out .= self::text("\n");
        }

        $out .= self::separator();

        $subtotal = (float) ($order['subtotal'] ?? $order['total'] ?? 0);
        $tipAmount = (float) ($order['tip_amount'] ?? 0);
        $out .= self::textLabelValue('Subtotal prod.:', self::formatCLP($subtotal));

        if ($tipAmount > 0) {
            $tipPercent = (float) ($order['tip_percent'] ?? ($subtotal > 0 ? ($tipAmount / $subtotal) * 100 : 10));
            $tipLabel = ($order['tip_mode'] ?? '') === 'manual'
                ? 'Propina'
                : 'Propina ' . (int) round($tipPercent) . '%';
            $out .= self::textLabelValue($tipLabel . ':', self::formatCLP($tipAmount));
        }

        $out .= self::bold(true);
        $out .= self::textLabelValue('TOTAL:', self::formatCLP((float) ($order['total'] ?? 0)));
        $out .= self::bold(false);

        $out .= self::separator();
        $out .= self::alignCenter();
        $out .= self::text("Gracias por su preferencia\n");
        $out .= self::feed(5);
        $out .= self::cut();

        return $out;
    }

    public static function sendToNetwork(string $data, string $host, int $port = 9100, int $timeout = 5): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket === false) {
            return false;
        }

        fwrite($socket, $data);
        fclose($socket);
        return true;
    }

    private static function initialize(): string
    {
        return self::ESC . '@'
            . self::GS . 'L' . "\x00\x00"
            . self::GS . 'W' . "\x80\x01";
    }

    private static function text(string $text): string
    {
        return $text;
    }

    private static function textLabelValue(string $label, string $value): string
    {
        $out = '';
        foreach (self::linesLabelValue($label, $value) as $line) {
            $out .= self::text($line . "\n");
        }
        return $out;
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

    private static function textWrapped(string $text, ?int $width = null): string
    {
        $out = '';
        foreach (self::wrapLines($text, $width) as $line) {
            $out .= self::text($line . "\n");
        }
        return $out;
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

    private static function bold(bool $on): string
    {
        return self::ESC . 'E' . chr($on ? 1 : 0);
    }

    private static function alignLeft(): string
    {
        return self::ESC . 'a' . "\x00";
    }

    private static function alignCenter(): string
    {
        return self::ESC . 'a' . "\x01";
    }

    private static function separator(): string
    {
        return str_repeat('-', self::LINE_WIDTH) . "\n";
    }

    private static function feed(int $lines = 1): string
    {
        return self::ESC . 'd' . chr($lines);
    }

    private static function formatCLP(float $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }

    private static function cut(): string
    {
        return self::GS . 'V' . "\x00";
    }
}
