<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FactusolImportService — Importa datos desde archivos Factusol (.mdb / CSV).
 *
 * Factusol (by DELSOL) usa MS Access (.mdb) como base de datos.
 * Este servicio procesa datos exportados en CSV desde Factusol
 * y los importa al ERP, mapeando las tablas:
 *
 *   F_ART → articles      (Artículos)
 *   F_FAC → invoices       (Facturas de venta - cabecera)
 *   F_LFA → invoice_lines  (Facturas de venta - líneas)
 *   F_FRE → invoices       (Facturas recibidas - cabecera)
 *   F_LFR → invoice_lines  (Facturas recibidas - líneas)
 *   Clientes → customers
 *   Proveedores → suppliers
 */
class FactusolImportService
{
    private int $companyId;
    private array $stats = [];

    // ── Mapeo de alícuotas AFIP Factusol → porcentaje ──
    private const IVA_MAP = [
        0 => 0,
        1 => 21,
        2 => 10.5,
        3 => 27,
        4 => 0,    // Exento
        5 => 5,
        6 => 2.5,
    ];

    public function __construct()
    {
        $this->resetStats();
    }

    private function resetStats(): void
    {
        $this->stats = [
            'articles_imported'   => 0,
            'articles_updated'    => 0,
            'customers_imported'  => 0,
            'customers_updated'   => 0,
            'suppliers_imported'  => 0,
            'suppliers_updated'   => 0,
            'invoices_imported'   => 0,
            'invoices_skipped'    => 0,
            'lines_imported'      => 0,
            'errors'              => [],
        ];
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    // ════════════════════════════════════════════════════════════════
    // ARTÍCULOS (F_ART)
    // ════════════════════════════════════════════════════════════════

    /**
     * Importa artículos desde CSV exportado de F_ART.
     * Columnas esperadas: CODART, DESART, FAMART, PHAART, PCOART, IVALIN
     */
    public function importArticles(array $rows, int $companyId): array
    {
        $this->companyId = $companyId;
        $this->resetStats();

        foreach ($rows as $i => $row) {
            try {
                $code = trim($row['CODART'] ?? $row['codigo'] ?? '');
                if (empty($code)) continue;

                $ivaType = (int) ($row['IVALIN'] ?? 1);
                $vatRate = self::IVA_MAP[$ivaType] ?? 21;

                $data = [
                    'company_id'  => $this->companyId,
                    'code'        => $code,
                    'description' => trim($row['DESART'] ?? $row['descripcion'] ?? 'Sin descripción'),
                    'category'    => trim($row['FAMART'] ?? $row['familia'] ?? ''),
                    'sale_price'  => (float) str_replace(',', '.', $row['PHAART'] ?? $row['precio_venta'] ?? 0),
                    'cost_price'  => (float) str_replace(',', '.', $row['PCOART'] ?? $row['precio_compra'] ?? 0),
                    'vat_rate'    => $vatRate,
                    'active'      => true,
                ];

                $article = Article::updateOrCreate(
                    ['company_id' => $this->companyId, 'code' => $code],
                    $data
                );

                if ($article->wasRecentlyCreated) {
                    $this->stats['articles_imported']++;
                } else {
                    $this->stats['articles_updated']++;
                }
            } catch (\Exception $e) {
                $this->stats['errors'][] = "Art fila {$i}: {$e->getMessage()}";
            }
        }

        return $this->stats;
    }

    // ════════════════════════════════════════════════════════════════
    // CLIENTES
    // ════════════════════════════════════════════════════════════════

    /**
     * Importa clientes desde CSV.
     * Columnas esperadas: CODCLI, NOMCLI, CIFCLI, DOMCLI, TELCLI, EMACLI
     */
    public function importCustomers(array $rows, int $companyId): array
    {
        $this->companyId = $companyId;
        $this->resetStats();

        foreach ($rows as $i => $row) {
            try {
                $code = trim($row['CODCLI'] ?? $row['codigo'] ?? '');
                if (empty($code)) continue;

                $cif = trim($row['CIFCLI'] ?? $row['cuit'] ?? '');
                $fiscalType = strlen(preg_replace('/\D/', '', $cif)) === 11 ? 'CUIT' : 'DNI';

                $data = [
                    'company_id'  => $this->companyId,
                    'code'        => $code,
                    'name'        => trim($row['NOMCLI'] ?? $row['nombre'] ?? ''),
                    'fiscal_id'   => $cif,
                    'fiscal_type' => ! empty($cif) ? $fiscalType : null,
                    'address'     => trim($row['DOMCLI'] ?? $row['domicilio'] ?? ''),
                    'phone'       => trim($row['TELCLI'] ?? $row['telefono'] ?? ''),
                    'email'       => trim($row['EMACLI'] ?? $row['email'] ?? ''),
                    'active'      => true,
                ];

                $customer = Customer::updateOrCreate(
                    ['company_id' => $this->companyId, 'code' => $code],
                    $data
                );

                if ($customer->wasRecentlyCreated) {
                    $this->stats['customers_imported']++;
                } else {
                    $this->stats['customers_updated']++;
                }
            } catch (\Exception $e) {
                $this->stats['errors'][] = "Cli fila {$i}: {$e->getMessage()}";
            }
        }

        return $this->stats;
    }

    // ════════════════════════════════════════════════════════════════
    // PROVEEDORES
    // ════════════════════════════════════════════════════════════════

    /**
     * Importa proveedores desde CSV.
     * Columnas esperadas: CODPRO, NOMPRO, CIFPRO, DOMPRO, TELPRO, EMAPRO
     */
    public function importSuppliers(array $rows, int $companyId): array
    {
        $this->companyId = $companyId;
        $this->resetStats();

        foreach ($rows as $i => $row) {
            try {
                $code = trim($row['CODPRO'] ?? $row['codigo'] ?? '');
                if (empty($code)) continue;

                $cif = trim($row['CIFPRO'] ?? $row['cuit'] ?? '');

                $data = [
                    'company_id'  => $this->companyId,
                    'code'        => $code,
                    'name'        => trim($row['NOMPRO'] ?? $row['nombre'] ?? ''),
                    'fiscal_id'   => $cif,
                    'fiscal_type' => ! empty($cif) ? 'CUIT' : null,
                    'address'     => trim($row['DOMPRO'] ?? $row['domicilio'] ?? ''),
                    'phone'       => trim($row['TELPRO'] ?? $row['telefono'] ?? ''),
                    'email'       => trim($row['EMAPRO'] ?? $row['email'] ?? ''),
                    'active'      => true,
                ];

                $supplier = Supplier::updateOrCreate(
                    ['company_id' => $this->companyId, 'code' => $code],
                    $data
                );

                if ($supplier->wasRecentlyCreated) {
                    $this->stats['suppliers_imported']++;
                } else {
                    $this->stats['suppliers_updated']++;
                }
            } catch (\Exception $e) {
                $this->stats['errors'][] = "Prov fila {$i}: {$e->getMessage()}";
            }
        }

        return $this->stats;
    }

    // ════════════════════════════════════════════════════════════════
    // FACTURAS DE VENTA (F_FAC + F_LFA)
    // ════════════════════════════════════════════════════════════════

    /**
     * Importa facturas de venta con sus líneas.
     *
     * @param array $headers  Filas F_FAC: TIPFAC, CODFAC, CLIFAC, CNOFAC, FECFAC, TOTFAC, etc.
     * @param array $lines    Filas F_LFA: TIPLFA, CODLFA, ARTLFA, DESLFA, CANLFA, PRELFA, TOTLFA, PIVLFA, DT1LFA, DT2LFA, DT3LFA
     */
    public function importSaleInvoices(array $headers, array $lines, int $companyId): array
    {
        $this->companyId = $companyId;
        $this->resetStats();

        // Indexar líneas por TIPFAC-CODFAC
        $linesByInvoice = [];
        foreach ($lines as $line) {
            $key = ($line['TIPLFA'] ?? '') . '-' . ($line['CODLFA'] ?? '');
            $linesByInvoice[$key][] = $line;
        }

        foreach ($headers as $row) {
            try {
                $tipfac = trim($row['TIPFAC'] ?? '1');
                $codfac = trim($row['CODFAC'] ?? '');
                if (empty($codfac)) continue;

                // Buscar cliente por código
                $customerCode = trim($row['CLIFAC'] ?? '');
                $customer = ! empty($customerCode)
                    ? Customer::where('company_id', $this->companyId)->where('code', $customerCode)->first()
                    : null;

                // Mapear tipo de comprobante Factusol → letra
                $invoiceCode = $this->mapInvoiceCode($tipfac);
                $date = $this->parseFactusolDate($row['FECFAC'] ?? '');
                $total = (float) str_replace(',', '.', $row['TOTFAC'] ?? 0);

                // Verificar si ya existe
                $exists = Invoice::where('company_id', $this->companyId)
                    ->where('type', 'sale')
                    ->where('invoice_code', $invoiceCode)
                    ->where('pos_number', (int) $tipfac)
                    ->where('number', (int) $codfac)
                    ->exists();

                if ($exists) {
                    $this->stats['invoices_skipped']++;
                    continue;
                }

                DB::transaction(function () use ($tipfac, $codfac, $invoiceCode, $date, $total, $customer, $row, $linesByInvoice) {
                    $invoice = Invoice::create([
                        'company_id'   => $this->companyId,
                        'type'         => 'sale',
                        'invoice_code' => $invoiceCode,
                        'pos_number'   => (int) $tipfac,
                        'number'       => (int) $codfac,
                        'date'         => $date,
                        'customer_id'  => $customer?->id,
                        'status'       => 'authorized', // Factusol = ya emitidos
                        'total'        => $total,
                        'notes'        => "Importado de Factusol {$tipfac}-{$codfac}",
                    ]);

                    // Importar líneas
                    $key = $tipfac . '-' . $codfac;
                    $invoiceLines = $linesByInvoice[$key] ?? [];
                    $net = 0;
                    $vat = 0;

                    foreach ($invoiceLines as $pos => $lfa) {
                        $artCode = trim($lfa['ARTLFA'] ?? '');
                        $article = ! empty($artCode)
                            ? Article::where('company_id', $this->companyId)->where('code', $artCode)->first()
                            : null;

                        $qty       = (float) str_replace(',', '.', $lfa['CANLFA'] ?? 0);
                        $unitPrice = (float) str_replace(',', '.', $lfa['PRELFA'] ?? 0);
                        $vatRate   = (float) str_replace(',', '.', $lfa['PIVLFA'] ?? 21);
                        $subtotal  = (float) str_replace(',', '.', $lfa['TOTLFA'] ?? ($qty * $unitPrice));

                        // Aplicar descuentos secuenciales si existen
                        $dt1 = (float) ($lfa['DT1LFA'] ?? 0);
                        $dt2 = (float) ($lfa['DT2LFA'] ?? 0);
                        $dt3 = (float) ($lfa['DT3LFA'] ?? 0);
                        if ($dt1 > 0 || $dt2 > 0 || $dt3 > 0) {
                            $discounted = $qty * $unitPrice;
                            if ($dt1 > 0) $discounted *= (1 - $dt1 / 100);
                            if ($dt2 > 0) $discounted *= (1 - $dt2 / 100);
                            if ($dt3 > 0) $discounted *= (1 - $dt3 / 100);
                            $subtotal = round($discounted, 2);
                        }

                        $net += $subtotal;
                        $vat += round($subtotal * ($vatRate / 100), 2);

                        InvoiceLine::create([
                            'invoice_id'  => $invoice->id,
                            'article_id'  => $article?->id,
                            'description' => trim($lfa['DESLFA'] ?? $artCode),
                            'quantity'    => $qty,
                            'unit_price'  => $unitPrice,
                            'vat_rate'    => $vatRate,
                            'subtotal'    => $subtotal,
                            'sort_order'  => (int) ($lfa['POSLFA'] ?? $pos),
                        ]);

                        $this->stats['lines_imported']++;
                    }

                    // Actualizar totales
                    $invoice->update([
                        'net' => round($net, 2),
                        'vat' => round($vat, 2),
                        'total' => $total ?: round($net + $vat, 2),
                    ]);
                });

                $this->stats['invoices_imported']++;
            } catch (\Exception $e) {
                $this->stats['errors'][] = "FAC {$row['TIPFAC']}-{$row['CODFAC']}: {$e->getMessage()}";
                Log::error("FactusolImport FAC error", ['row' => $row, 'error' => $e->getMessage()]);
            }
        }

        return $this->stats;
    }

    // ════════════════════════════════════════════════════════════════
    // FACTURAS RECIBIDAS (F_FRE + F_LFR)
    // ════════════════════════════════════════════════════════════════

    /**
     * Importa facturas de compra (recibidas) con sus líneas.
     */
    public function importPurchaseInvoices(array $headers, array $lines, int $companyId): array
    {
        $this->companyId = $companyId;
        $this->resetStats();

        $linesByInvoice = [];
        foreach ($lines as $line) {
            $key = ($line['TIPLFR'] ?? '') . '-' . ($line['CODLFR'] ?? '');
            $linesByInvoice[$key][] = $line;
        }

        foreach ($headers as $row) {
            try {
                $tipfre = trim($row['TIPFRE'] ?? '1');
                $codfre = trim($row['CODFRE'] ?? '');
                if (empty($codfre)) continue;

                $supplierCode = trim($row['PROFRE'] ?? '');
                $supplier = ! empty($supplierCode)
                    ? Supplier::where('company_id', $this->companyId)->where('code', $supplierCode)->first()
                    : null;

                $date  = $this->parseFactusolDate($row['FECFRE'] ?? '');
                $total = (float) str_replace(',', '.', $row['TOTFRE'] ?? 0);

                $exists = Invoice::where('company_id', $this->companyId)
                    ->where('type', 'purchase')
                    ->where('pos_number', (int) $tipfre)
                    ->where('number', (int) $codfre)
                    ->exists();

                if ($exists) {
                    $this->stats['invoices_skipped']++;
                    continue;
                }

                DB::transaction(function () use ($tipfre, $codfre, $date, $total, $supplier, $linesByInvoice) {
                    $invoice = Invoice::create([
                        'company_id'   => $this->companyId,
                        'type'         => 'purchase',
                        'invoice_code' => 'X',
                        'pos_number'   => (int) $tipfre,
                        'number'       => (int) $codfre,
                        'date'         => $date,
                        'supplier_id'  => $supplier?->id,
                        'status'       => 'authorized',
                        'total'        => $total,
                        'notes'        => "Importado de Factusol FRE {$tipfre}-{$codfre}",
                    ]);

                    $key = $tipfre . '-' . $codfre;
                    $invoiceLines = $linesByInvoice[$key] ?? [];
                    $net = 0;
                    $vat = 0;

                    foreach ($invoiceLines as $pos => $lfr) {
                        $artCode = trim($lfr['ARTLFR'] ?? '');
                        $article = ! empty($artCode)
                            ? Article::where('company_id', $this->companyId)->where('code', $artCode)->first()
                            : null;

                        $qty       = (float) str_replace(',', '.', $lfr['CANLFR'] ?? 0);
                        $unitPrice = (float) str_replace(',', '.', $lfr['PCOLFR'] ?? 0);
                        $subtotal  = (float) str_replace(',', '.', $lfr['TOTLFR'] ?? ($qty * $unitPrice));
                        $vatRate   = 21; // Default para compras

                        $net += $subtotal;
                        $vat += round($subtotal * ($vatRate / 100), 2);

                        InvoiceLine::create([
                            'invoice_id'  => $invoice->id,
                            'article_id'  => $article?->id,
                            'description' => trim($lfr['DESLFR'] ?? $artCode),
                            'quantity'    => $qty,
                            'unit_price'  => $unitPrice,
                            'vat_rate'    => $vatRate,
                            'subtotal'    => $subtotal,
                            'sort_order'  => (int) ($lfr['POSLFR'] ?? $pos),
                        ]);
                        $this->stats['lines_imported']++;
                    }

                    $invoice->update([
                        'net' => round($net, 2),
                        'vat' => round($vat, 2),
                        'total' => $total ?: round($net + $vat, 2),
                    ]);
                });

                $this->stats['invoices_imported']++;
            } catch (\Exception $e) {
                $this->stats['errors'][] = "FRE {$tipfre}-{$codfre}: {$e->getMessage()}";
            }
        }

        return $this->stats;
    }

    // ════════════════════════════════════════════════════════════════
    // CSV PARSER
    // ════════════════════════════════════════════════════════════════

    /**
     * Parsea un archivo CSV (con auto-detección de delimitador y encoding).
     */
    public static function parseCsv(string $filePath, ?string $expectedEncoding = null): array
    {
        $content = file_get_contents($filePath);

        // Auto-detect encoding (Factusol suele usar Windows-1252 o ISO-8859-1)
        $encoding = $expectedEncoding ?? mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Detect delimiter
        $firstLine = strtok($content, "\n");
        $delimiter  = str_contains($firstLine, "\t") ? "\t"
                    : (str_contains($firstLine, ';') ? ';' : ',');

        $lines = explode("\n", $content);
        $headers = str_getcsv(array_shift($lines), $delimiter);
        $headers = array_map('trim', $headers);

        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $values = str_getcsv($line, $delimiter);
            if (count($values) === count($headers)) {
                $rows[] = array_combine($headers, $values);
            }
        }

        return $rows;
    }

    // ════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════

    private function mapInvoiceCode(string $tipfac): string
    {
        // Factusol usa series numéricas (1-9). Mapeamos a letras.
        // En la implementación real esto es configurable por empresa.
        return match ($tipfac) {
            '1' => 'A',
            '2' => 'B',
            '3' => 'C',
            '4' => 'M',
            default => 'B',
        };
    }

    private function parseFactusolDate(string $date): string
    {
        // Factusol: dd/mm/yyyy o yyyy-mm-dd
        if (empty($date) || $date === '1900-01-01' || $date === '00/00/0000') {
            return today()->format('Y-m-d');
        }

        // dd/mm/yyyy
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Ya está en formato ISO
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) {
            return $date;
        }

        // Factusol date sin separadores: YYYYMMDD
        if (preg_match('#^(\d{4})(\d{2})(\d{2})$#', $date, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return today()->format('Y-m-d');
    }
}
