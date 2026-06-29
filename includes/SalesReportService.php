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
