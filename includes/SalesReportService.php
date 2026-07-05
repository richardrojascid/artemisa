<?php
declare(strict_types=1);

require_once __DIR__ . '/OdtWriter.php';
require_once __DIR__ . '/Mailer.php';

class SalesReportService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getDailyReport(?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');

        $ordersStmt = $this->db->prepare("
            SELECT id, table_number, waiter_name, subtotal, tip_amount, total, created_at
            FROM orders
            WHERE date(created_at) = date(?)
            ORDER BY created_at
        ");
        $ordersStmt->execute([$date]);
        $orders = $ordersStmt->fetchAll();

        $productsStmt = $this->db->prepare("
            SELECT oi.item_name,
                   SUM(oi.quantity) AS total_qty,
                   SUM(oi.line_total) AS total_amount
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE date(o.created_at) = date(?)
            GROUP BY oi.item_name
            ORDER BY total_amount DESC, oi.item_name
        ");
        $productsStmt->execute([$date]);
        $products = $productsStmt->fetchAll();

        $subtotalProducts = 0.0;
        $totalTips = 0.0;
        $grandTotal = 0.0;

        foreach ($orders as &$order) {
            $order['subtotal'] = (float) $order['subtotal'];
            $order['tip_amount'] = (float) ($order['tip_amount'] ?? 0);
            $order['total'] = (float) $order['total'];
            $subtotalProducts += $order['subtotal'];
            $totalTips += $order['tip_amount'];
            $grandTotal += $order['total'];
        }

        foreach ($products as &$product) {
            $product['total_qty'] = (int) $product['total_qty'];
            $product['total_amount'] = (float) $product['total_amount'];
        }

        return [
            'date' => $date,
            'orders_count' => count($orders),
            'orders' => $orders,
            'products' => $products,
            'subtotal_products' => $subtotalProducts,
            'total_tips' => $totalTips,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Ventas del día con detalle por línea de producto (para exportar a Excel).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDailyOrdersDetailed(?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');

        $ordersStmt = $this->db->prepare("
            SELECT id, table_number, waiter_name, client_name, order_type,
                   subtotal, tip_amount, total, include_tip, created_at
            FROM orders
            WHERE date(created_at) = date(?)
            ORDER BY datetime(created_at) ASC, id ASC
        ");
        $ordersStmt->execute([$date]);
        $orders = $ordersStmt->fetchAll();

        $itemsStmt = $this->db->prepare("
            SELECT order_id, item_name, unit_price, quantity, extras_total, line_total, notes
            FROM order_items
            WHERE order_id = ?
            ORDER BY id ASC
        ");

        $rows = [];
        foreach ($orders as $order) {
            $itemsStmt->execute([(int) $order['id']]);
            $items = $itemsStmt->fetchAll();
            if ($items === []) {
                continue;
            }

            $orderType = $order['order_type'] ?? 'servir';
            if ($orderType !== 'llevar' && strtoupper((string) ($order['table_number'] ?? '')) === 'PL') {
                $orderType = 'llevar';
            }

            $subtotal = (float) $order['subtotal'];
            $tipAmount = (float) ($order['tip_amount'] ?? 0);
            $tipPercent = $subtotal > 0 && $tipAmount > 0
                ? round(($tipAmount / $subtotal) * 100, 1)
                : 0.0;

            $createdAt = $order['created_at'] ?? '';
            $timestamp = strtotime($createdAt) ?: time();

            $mesa = '';
            if ($orderType === 'llevar') {
                $mesa = strtoupper((string) ($order['table_number'] ?? '')) === 'PL'
                    ? 'PL'
                    : (string) ($order['table_number'] ?? 'PL');
            } elseif (!empty($order['table_number'])) {
                $mesa = (string) $order['table_number'];
            }

            $mesero = $orderType === 'servir' ? (string) ($order['waiter_name'] ?? '') : '';
            $cajera = $orderType === 'llevar' ? (string) ($order['waiter_name'] ?? '') : '';
            $cliente = trim((string) ($order['client_name'] ?? ''));

            foreach ($items as $item) {
                $rows[] = [
                    'fecha' => date('Y-m-d', $timestamp),
                    'hora' => date('H:i', $timestamp),
                    'comanda_id' => (int) $order['id'],
                    'tipo_servicio' => $orderType === 'llevar' ? 'Para llevar' : 'Para servir',
                    'mesa' => $mesa,
                    'mesero' => $mesero,
                    'cajera' => $cajera,
                    'cliente' => $cliente,
                    'producto' => (string) $item['item_name'],
                    'cantidad' => (int) $item['quantity'],
                    'precio_unitario' => (int) round((float) $item['unit_price']),
                    'subtotal_linea' => (int) round((float) $item['line_total']),
                    'notas' => trim((string) ($item['notes'] ?? '')),
                    'subtotal_comanda' => (int) round($subtotal),
                    'propina_porcentaje' => $tipPercent,
                    'propina_monto' => (int) round($tipAmount),
                    'total_comanda' => (int) round((float) $order['total']),
                ];
            }
        }

        return $rows;
    }

    public function buildDetailedSalesExcel(array $rows, string $cafeName, string $date): string
    {
        $lines = [];
        $lines[] = "\xEF\xBB\xBF";
        $lines[] = $this->csvRow(['Reporte detallado de ventas — ' . $cafeName]);
        $lines[] = $this->csvRow(['Fecha', $date]);
        $lines[] = $this->csvRow(['Comandas', count(array_unique(array_column($rows, 'comanda_id')))]);
        $lines[] = '';

        $headers = [
            'Fecha',
            'Hora',
            'N° Comanda',
            'Tipo',
            'Mesa',
            'Mesero',
            'Cajera',
            'Cliente',
            'Producto',
            'Cantidad',
            'Precio unitario',
            'Subtotal línea',
            'Notas',
            'Subtotal comanda',
            'Propina %',
            'Propina monto',
            'Total comanda',
        ];
        $lines[] = $this->csvRow($headers);

        foreach ($rows as $row) {
            $lines[] = $this->csvRow([
                $row['fecha'],
                $row['hora'],
                $row['comanda_id'],
                $row['tipo_servicio'],
                $row['mesa'],
                $row['mesero'],
                $row['cajera'],
                $row['cliente'],
                $row['producto'],
                $row['cantidad'],
                $row['precio_unitario'],
                $row['subtotal_linea'],
                $row['notas'],
                $row['subtotal_comanda'],
                $row['propina_porcentaje'],
                $row['propina_monto'],
                $row['total_comanda'],
            ]);
        }

        if ($rows !== []) {
            $lines[] = '';
            $lines[] = $this->csvRow([
                'TOTALES DÍA',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                array_sum(array_column($rows, 'cantidad')),
                '',
                array_sum(array_column($rows, 'subtotal_linea')),
                '',
                $this->sumUniqueOrderField($rows, 'subtotal_comanda'),
                '',
                $this->sumUniqueOrderField($rows, 'propina_monto'),
                $this->sumUniqueOrderField($rows, 'total_comanda'),
            ]);
        }

        return implode("\r\n", $lines);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function sumUniqueOrderField(array $rows, string $field): int
    {
        $seen = [];
        $sum = 0;
        foreach ($rows as $row) {
            $id = (int) $row['comanda_id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $sum += (int) $row[$field];
        }

        return $sum;
    }

    public function buildCsv(array $report, string $cafeName): string
    {
        $lines = [];
        $lines[] = "\xEF\xBB\xBF"; // UTF-8 BOM para Excel
        $lines[] = $this->csvRow(['Reporte de ventas — ' . $cafeName]);
        $lines[] = $this->csvRow(['Fecha', $report['date']]);
        $lines[] = $this->csvRow(['Pedidos del día', $report['orders_count']]);
        $lines[] = '';
        $lines[] = $this->csvRow(['Producto', 'Cantidad vendida', 'Monto (CLP)']);

        foreach ($report['products'] as $product) {
            $lines[] = $this->csvRow([
                $product['item_name'],
                $product['total_qty'],
                (int) round($product['total_amount']),
            ]);
        }

        $lines[] = '';
        $lines[] = $this->csvRow(['Subtotal productos', '', (int) round($report['subtotal_products'])]);
        $lines[] = $this->csvRow(['Propinas recaudadas (10%)', '', (int) round($report['total_tips'])]);
        $lines[] = $this->csvRow(['TOTAL DEL DÍA', '', (int) round($report['grand_total'])]);

        return implode("\r\n", $lines);
    }

    public function sendEmailReport(string $to, string $cafeName, ?string $date = null): array
    {
        $report = $this->getDailyReport($date);
        $dateLabel = $report['date'];
        $csv = $this->buildCsv($report, $cafeName);
        $odt = OdtWriter::buildSalesReport($report, $cafeName);

        $subject = "Reporte de ventas {$dateLabel} — {$cafeName}";
        $body = "Adjunto reporte de ventas del día {$dateLabel}.\n\n"
            . "Pedidos: {$report['orders_count']}\n"
            . "Subtotal productos: $" . number_format($report['subtotal_products'], 0, ',', '.') . "\n"
            . "Propinas: $" . number_format($report['total_tips'], 0, ',', '.') . "\n"
            . "Total del día: $" . number_format($report['grand_total'], 0, ',', '.') . "\n";

        return Mailer::send($to, $subject, $body, [
            ['filename' => "ventas_{$dateLabel}.csv", 'content' => $csv, 'mime' => 'text/csv'],
            ['filename' => "ventas_{$dateLabel}.odt", 'content' => $odt, 'mime' => 'application/vnd.oasis.opendocument.text'],
        ]);
    }

    private function csvRow(array $fields): string
    {
        return implode(';', array_map(function ($field) {
            $value = (string) $field;
            if (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")) {
                return '"' . str_replace('"', '""', $value) . '"';
            }
            return $value;
        }, $fields));
    }
}
