<?php

namespace App\Http\Controllers;

use App\Services\FactusolImportService;
use Illuminate\Http\Request;

/**
 * FactusolController — Gestiona la importación de datos desde Factusol.
 *
 * Permite subir archivos CSV exportados desde Factusol y los importa
 * al ERP mapeando las tablas F_ART, F_FAC, F_LFA, F_FRE, F_LFR.
 */
class FactusolController extends Controller
{
    public function index()
    {
        return view('factusol.index');
    }

    /**
     * Procesa la importación de archivos CSV.
     */
    public function import(Request $request, FactusolImportService $service)
    {
        $request->validate([
            'import_type' => 'required|in:articles,customers,suppliers,sale_invoices,purchase_invoices',
            'file_main'   => 'required|file|max:20480', // 20MB max
            'file_lines'  => 'nullable|file|max:20480',
        ]);

        $companyId  = auth()->user()->company_id;
        $importType = $request->input('import_type');
        $mainFile   = $request->file('file_main');

        try {
            $mainRows = FactusolImportService::parseCsv($mainFile->getRealPath());

            $stats = match ($importType) {
                'articles'  => $service->importArticles($mainRows, $companyId),
                'customers' => $service->importCustomers($mainRows, $companyId),
                'suppliers' => $service->importSuppliers($mainRows, $companyId),
                'sale_invoices' => $this->handleInvoiceImport($request, $service, $mainRows, $companyId, 'sale'),
                'purchase_invoices' => $this->handleInvoiceImport($request, $service, $mainRows, $companyId, 'purchase'),
            };

            return redirect()->route('factusol.index')
                ->with('success', $this->formatStats($importType, $stats))
                ->with('import_stats', $stats);

        } catch (\Exception $e) {
            return redirect()->route('factusol.index')
                ->with('error', 'Error al importar: ' . $e->getMessage());
        }
    }

    /**
     * Preview: parsea un CSV sin importar, muestra los primeros registros.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        try {
            $rows = FactusolImportService::parseCsv($request->file('file')->getRealPath());

            return response()->json([
                'success'     => true,
                'total_rows'  => count($rows),
                'columns'     => ! empty($rows) ? array_keys($rows[0]) : [],
                'preview'     => array_slice($rows, 0, 10),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    private function handleInvoiceImport(Request $request, FactusolImportService $service, array $mainRows, int $companyId, string $type): array
    {
        $lineRows = [];

        if ($request->hasFile('file_lines')) {
            $lineRows = FactusolImportService::parseCsv(
                $request->file('file_lines')->getRealPath()
            );
        }

        return $type === 'sale'
            ? $service->importSaleInvoices($mainRows, $lineRows, $companyId)
            : $service->importPurchaseInvoices($mainRows, $lineRows, $companyId);
    }

    private function formatStats(string $type, array $stats): string
    {
        $msgs = [];

        if ($stats['articles_imported'] ?? 0)  $msgs[] = "{$stats['articles_imported']} artículos importados";
        if ($stats['articles_updated'] ?? 0)   $msgs[] = "{$stats['articles_updated']} artículos actualizados";
        if ($stats['customers_imported'] ?? 0) $msgs[] = "{$stats['customers_imported']} clientes importados";
        if ($stats['customers_updated'] ?? 0)  $msgs[] = "{$stats['customers_updated']} clientes actualizados";
        if ($stats['suppliers_imported'] ?? 0) $msgs[] = "{$stats['suppliers_imported']} proveedores importados";
        if ($stats['suppliers_updated'] ?? 0)  $msgs[] = "{$stats['suppliers_updated']} proveedores actualizados";
        if ($stats['invoices_imported'] ?? 0)  $msgs[] = "{$stats['invoices_imported']} facturas importadas";
        if ($stats['invoices_skipped'] ?? 0)   $msgs[] = "{$stats['invoices_skipped']} facturas ya existían (omitidas)";
        if ($stats['lines_imported'] ?? 0)     $msgs[] = "{$stats['lines_imported']} líneas de detalle importadas";

        $errorCount = count($stats['errors'] ?? []);
        if ($errorCount > 0) $msgs[] = "⚠️ {$errorCount} errores";

        return implode(' · ', $msgs) ?: 'Importación completada.';
    }
}
