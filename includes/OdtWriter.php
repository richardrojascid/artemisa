<?php
declare(strict_types=1);

class OdtWriter
{
    public static function buildSalesReport(array $report, string $cafeName): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('La extensión ZipArchive es necesaria para generar ODT.');
        }

        $rows = [];
        $rows[] = ['Producto', 'Cantidad', 'Monto (CLP)'];
        foreach ($report['products'] as $product) {
            $rows[] = [
                $product['item_name'],
                (string) $product['total_qty'],
                self::formatCLP($product['total_amount']),
            ];
        }
        $rows[] = ['', '', ''];
        $rows[] = ['Subtotal productos', '', self::formatCLP($report['subtotal_products'])];
        $rows[] = ['Propinas recaudadas', '', self::formatCLP($report['total_tips'])];
        $rows[] = ['TOTAL DEL DÍA', '', self::formatCLP($report['grand_total'])];

        $tableRows = '';
        foreach ($rows as $i => $row) {
            $style = $i === 0 || $i >= count($rows) - 3 ? ' style="font-weight:bold"' : '';
            $tableRows .= '<table:table-row>';
            foreach ($row as $cell) {
                $tableRows .= '<table:table-cell' . $style . '><text:p>' . htmlspecialchars($cell, ENT_XML1) . '</text:p></table:table-cell>';
            }
            $tableRows .= '</table:table-row>';
        }

        $content = '<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
 xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
 xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
 office:version="1.2">
 <office:body>
  <office:text>
   <text:h text:style-name="Heading">' . htmlspecialchars('Reporte de ventas — ' . $cafeName, ENT_XML1) . '</text:h>
   <text:p>Fecha: ' . htmlspecialchars($report['date'], ENT_XML1) . '</text:p>
   <text:p>Pedidos: ' . (int) $report['orders_count'] . '</text:p>
   <table:table table:name="Ventas">
    ' . $tableRows . '
   </table:table>
  </office:text>
 </office:body>
</office:document-content>';

        $mimetype = 'application/vnd.oasis.opendocument.text';
        $meta = '<?xml version="1.0" encoding="UTF-8"?>
<office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
 xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0" office:version="1.2">
 <office:meta><meta:creation-date>' . date('c') . '</meta:creation-date></office:meta>
</office:document-meta>';

        $manifest = '<?xml version="1.0" encoding="UTF-8"?>
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">
 <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/"/>
 <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
 <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>
</manifest:manifest>';

        $tmp = tempnam(sys_get_temp_dir(), 'odt');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', $mimetype);
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
        $zip->addFromString('META-INF/manifest.xml', $manifest);
        $zip->addFromString('content.xml', $content);
        $zip->addFromString('meta.xml', $meta);
        $zip->close();

        $data = file_get_contents($tmp);
        unlink($tmp);
        return $data ?: '';
    }

    private static function formatCLP(float $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }
}
