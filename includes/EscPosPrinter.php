<?php
declare(strict_types=1);

class EscPosPrinter
{
    private const ESC = "\x1b";
    private const GS = "\x1d";

    public static function buildReceipt(array $order, array $items, string $cafeName = 'CAFÉ COMANDA'): string
    {
        $out = '';
        $out .= self::initialize();
        $out .= self::alignCenter();
        $out .= self::bold(true);
        $out .= self::text($cafeName . "\n");
        $out .= self::bold(false);
        $out .= self::text("COMANDA DE PEDIDO\n");
        if (!empty($order['id'])) {
            $out .= self::bold(true);
            $out .= self::text('Comanda #' . $order['id'] . "\n");
            $out .= self::bold(false);
        }
        $out .= self::separator();

        $out .= self::alignLeft();
        $createdAt = $order['created_at'] ?? date('Y-m-d H:i:s');
        $out .= self::text('Fecha: ' . date('d/m/Y H:i', strtotime($createdAt)) . "\n");

        if (!empty($order['client_name'])) {
            $out .= self::text('Cliente: ' . $order['client_name'] . "\n");
        }

        if (!empty($order['table_number'])) {
            $isTakeaway = strtoupper((string) $order['table_number']) === 'PL';
            if ($isTakeaway) {
                $out .= self::text("PARA LLEVAR (PL)\n");
            } else {
                $out .= self::text('Mesa: ' . $order['table_number'] . "\n");
            }
        }
        if (!empty($order['waiter_name'])) {
            $staffLabel = (!empty($order['table_number']) && strtoupper((string) $order['table_number']) === 'PL')
                ? 'Cajero'
                : 'Mesero';
            $out .= self::text($staffLabel . ': ' . $order['waiter_name'] . "\n");
        }

        $out .= self::separator();

        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $name = $item['item_name'] ?? $item['name'] ?? 'Producto';
            $lineTotal = (float) ($item['line_total'] ?? 0);

            $out .= self::bold(true);
            $out .= self::text("{$qty}x {$name}\n");
            $out .= self::bold(false);

            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $out .= self::text('   Precio unit.: ' . self::formatCLP($unitPrice) . "\n");

            $removed = $item['removed_ingredients'] ?? [];
            if (is_string($removed)) {
                $removed = json_decode($removed, true) ?: [];
            }
            if (!empty($removed)) {
                $out .= self::text('   Sin: ' . implode(', ', $removed) . "\n");
            }

            $extras = $item['added_extras'] ?? [];
            if (is_string($extras)) {
                $extras = json_decode($extras, true) ?: [];
            }
            if (!empty($extras)) {
                foreach ($extras as $extra) {
                    $extraName = is_array($extra) ? ($extra['name'] ?? '') : $extra;
                    $extraPrice = is_array($extra) ? (float) ($extra['price'] ?? 0) : 0;
                    $priceStr = $extraPrice > 0 ? ' (+' . self::formatCLP($extraPrice) . ')' : '';
                    $out .= self::text("   + {$extraName}{$priceStr}\n");
                }
            }

            if (!empty($item['notes'])) {
                $out .= self::text('   Nota: ' . $item['notes'] . "\n");
            }

            $out .= self::text('   Subtotal: ' . self::formatCLP($lineTotal) . "\n");
            $out .= self::text("\n");
        }

        $out .= self::separator();
        $out .= self::alignRight();

        $subtotal = (float) ($order['subtotal'] ?? $order['total'] ?? 0);
        $tipAmount = (float) ($order['tip_amount'] ?? 0);
        $out .= self::text('Subtotal productos: ' . self::formatCLP($subtotal) . "\n");

        if ($tipAmount > 0) {
            $tipPercent = (float) ($order['tip_percent'] ?? ($subtotal > 0 ? ($tipAmount / $subtotal) * 100 : 10));
            $tipLabel = ($order['tip_mode'] ?? '') === 'manual'
                ? 'Propina'
                : 'Propina ' . (int) round($tipPercent) . '%';
            $out .= self::text("{$tipLabel}: " . self::formatCLP($tipAmount) . "\n");
        }

        $out .= self::bold(true);
        $out .= self::text('TOTAL: ' . self::formatCLP((float) ($order['total'] ?? 0)) . "\n");
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
        return self::ESC . '@';
    }

    private static function text(string $text): string
    {
        return $text;
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

    private static function alignRight(): string
    {
        return self::ESC . 'a' . "\x02";
    }

    private static function separator(): string
    {
        return str_repeat('-', 32) . "\n";
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
