<?php

namespace App\Modules\Migration\Services;

use App\Models\Article;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

/**
 * FactusolImporter — Migra datos de Factusol a Open.ERP
 *
 * Proceso:
 * 1. Usuario exporta CSV desde Factusol (Archivo → Exportar → CSV)
 * 2. Sube el archivo al wizard en Open.ERP
 * 3. Este servicio mapea y valida los datos
 * 4. ImportChunkJob los procesa en Redis Queue (en chunks de 100)
 *
 * Tablas Factusol → Open.ERP:
 * F_CLI → customers | F_PRO → suppliers | F_ART → articles | F_FAC → invoices
 */
class FactusolImporter
{
    private int   $companyId;
    private array $errors    = [];
    private int   $processed = 0;

    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    // ── Clientes (F_CLI) ──────────────────────────────────

    /**
     * Mapeo F_CLI → customers
     * Columnas Factusol: CODCLI, CIFCLI, NOFCLI, DIPCLI, POBCLI, TLFCLI, EMACLI
     */
    public function importCustomers(string $csvPath): array
    {
        $csv  = $this->readCsv($csvPath);
        $rows = iterator_to_array($csv->getRecords());

        DB::beginTransaction();
        try {
            foreach (array_chunk($rows, 100) as $chunk) {
                foreach ($chunk as $row) {
                    $this->upsertCustomer($row);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->summary();
    }

    private function upsertCustomer(array $row): void
    {
        try {
            Customer::updateOrCreate(
                ['company_id' => $this->companyId, 'cuit' => $this->cleanCuit($row['CIFCLI'] ?? '')],
                [
                    'name'     => trim($row['NOFCLI'] ?? 'Sin nombre'),
                    'address'  => trim(($row['DIPCLI'] ?? '') . ' ' . ($row['POBCLI'] ?? '')),
                    'phone'    => trim($row['TLFCLI'] ?? ''),
                    'email'    => strtolower(trim($row['EMACLI'] ?? '')),
                    'tax_type' => $this->guessTaxType($row['CIFCLI'] ?? ''),
                    'notes'    => "Importado desde Factusol (COD: {$row['CODCLI']})",
                ]
            );
            $this->processed++;
        } catch (\Throwable $e) {
            $this->errors[] = "Fila CODCLI={$row['CODCLI']}: {$e->getMessage()}";
        }
    }

    // ── Artículos (F_ART + F_STO) ────────────────────────

    /**
     * Mapeo F_ART → articles
     * Columnas: CODART, DESART, PREART, COSART, IVAART, STOMIN, FAMCOD
     */
    public function importArticles(string $csvArt, ?string $csvSto = null): array
    {
        $articles = $this->readCsv($csvArt);
        $stockMap = $csvSto ? $this->buildStockMap($csvSto) : [];

        DB::beginTransaction();
        try {
            foreach ($articles->getRecords() as $row) {
                $this->upsertArticle($row, $stockMap);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->summary();
    }

    private function upsertArticle(array $row, array $stockMap): void
    {
        try {
            $sku = trim($row['CODART'] ?? '');
            Article::updateOrCreate(
                ['company_id' => $this->companyId, 'sku' => $sku],
                [
                    'name'       => trim($row['DESART'] ?? $sku),
                    'sale_price' => (float) str_replace(',', '.', $row['PREART'] ?? 0),
                    'cost_price' => (float) str_replace(',', '.', $row['COSART'] ?? 0),
                    'vat_rate'   => $this->mapVat($row['IVAART'] ?? '1'),
                    'min_stock'  => (float) str_replace(',', '.', $row['STOMIN'] ?? 0),
                    'stock'      => (float) ($stockMap[$sku] ?? 0),
                    'notes'      => "Importado desde Factusol",
                ]
            );
            $this->processed++;
        } catch (\Throwable $e) {
            $this->errors[] = "Artículo {$row['CODART']}: {$e->getMessage()}";
        }
    }

    // ── Helpers ───────────────────────────────────────────

    private function buildStockMap(string $csvStoPath): array
    {
        // F_STO: CODSTO=código artículo, CANSTO=cantidad
        $map = [];
        foreach ($this->readCsv($csvStoPath)->getRecords() as $row) {
            $map[trim($row['CODSTO'] ?? '')] = (float) str_replace(',', '.', $row['CANSTO'] ?? 0);
        }
        return $map;
    }

    private function readCsv(string $path): Reader
    {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        $csv->setDelimiter(';'); // Factusol exporta con punto y coma
        return $csv;
    }

    private function cleanCuit(string $cuit): string
    {
        return preg_replace('/[^0-9]/', '', $cuit);
    }

    private function guessTaxType(string $cuit): string
    {
        $cleaned = $this->cleanCuit($cuit);
        if (strlen($cleaned) === 11) return 'RI';   // CUIT completo = Responsable Inscripto
        if (strlen($cleaned) === 11) return 'MO';   // Monotributo
        return 'CF';                                  // Consumidor Final por defecto
    }

    private function mapVat(string $factusolCode): float
    {
        // Factusol: 1=21%, 2=10.5%, 3=27%, 4=0%, 5=exento
        return match ($factusolCode) {
            '1' => 21.0, '2' => 10.5, '3' => 27.0, '4' => 0.0, '5' => 0.0,
            default => 21.0,
        };
    }

    private function summary(): array
    {
        return [
            'processed' => $this->processed,
            'errors'    => count($this->errors),
            'error_log' => $this->errors,
            'status'    => empty($this->errors) ? 'success' : 'partial',
        ];
    }
}
