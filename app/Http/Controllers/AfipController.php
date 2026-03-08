<?php

namespace App\Http\Controllers;

use App\Services\AfipPadronService;
use App\Services\AfipService;
use App\Models\Invoice;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * AfipController — Gestiona la integración con AFIP.
 * - Consulta padrón de contribuyentes (por CUIT)
 * - Panel de configuración AFIP de la empresa
 * - Estado de facturación electrónica
 */
class AfipController extends Controller
{
    /**
     * Panel principal AFIP — configuración y estado.
     */
    public function index()
    {
        $company = auth()->user()->company;
        $companyId = $company->id;

        // Stats de facturación
        $stats = [
            'total_authorized' => Invoice::where('company_id', $companyId)
                                    ->where('status', 'authorized')
                                    ->count(),
            'pending_draft'    => Invoice::where('company_id', $companyId)
                                    ->where('status', 'draft')
                                    ->where('type', 'sale')
                                    ->count(),
            'last_cae'         => Invoice::where('company_id', $companyId)
                                    ->whereNotNull('cae')
                                    ->latest('updated_at')
                                    ->first(),
            'month_total'      => Invoice::where('company_id', $companyId)
                                    ->where('status', 'authorized')
                                    ->where('type', 'sale')
                                    ->whereMonth('date', now()->month)
                                    ->whereYear('date', now()->year)
                                    ->sum('total'),
        ];

        // Verificar configuración AFIP
        $afipConfigured = ! empty($company->cuit)
                       && ! empty($company->afip_cert)
                       && ! empty($company->afip_key);

        return view('afip.index', compact('company', 'stats', 'afipConfigured'));
    }

    /**
     * AJAX: Consultar datos de contribuyente por CUIT.
     */
    public function consultarCuit(Request $request, AfipPadronService $padron): JsonResponse
    {
        $request->validate([
            'cuit' => 'required|string|min:10|max:13',
        ]);

        try {
            $data = $padron->getContribuyente($request->input('cuit'));

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar configuración AFIP de la empresa.
     */
    public function updateConfig(Request $request)
    {
        $request->validate([
            'cuit'      => 'required|string|min:11|max:13',
            'afip_mode' => 'required|in:homologacion,produccion',
            'afip_cert' => 'nullable|file|mimes:crt,pem,txt|max:10',
            'afip_key'  => 'nullable|file|mimes:key,pem,txt|max:10',
        ]);

        $company = auth()->user()->company;

        $company->cuit = preg_replace('/\D/', '', $request->input('cuit'));
        $company->afip_mode = $request->input('afip_mode');

        if ($request->hasFile('afip_cert')) {
            $company->afip_cert = base64_encode(
                file_get_contents($request->file('afip_cert')->getRealPath())
            );
        }

        if ($request->hasFile('afip_key')) {
            $company->afip_key = base64_encode(
                file_get_contents($request->file('afip_key')->getRealPath())
            );
        }

        $company->save();

        return redirect()->route('afip.index')
            ->with('success', 'Configuración AFIP actualizada correctamente.');
    }

    /**
     * Validar conectividad con AFIP WebServices.
     */
    public function testConnection(AfipService $afip): JsonResponse
    {
        $company = auth()->user()->company;

        if (empty($company->afip_cert) || empty($company->afip_key)) {
            return response()->json([
                'success' => false,
                'error'   => 'Falta certificado o clave privada. Configurá los datos AFIP primero.',
            ], 422);
        }

        try {
            // Intentar obtener el último comprobante del punto de venta 1
            $afipSdk = new \Afip([
                'CUIT'       => (int) $company->cuit,
                'cert'       => $this->writeTempCert($company, 'cert'),
                'key'        => $this->writeTempCert($company, 'key'),
                'production' => $company->afip_mode === 'produccion',
            ]);

            $lastVoucher = $afipSdk->ElectronicBilling->getLastVoucher(1, 6); // PdV 1, Factura B

            return response()->json([
                'success'      => true,
                'message'      => 'Conexión exitosa con AFIP WSFEv1.',
                'last_voucher' => $lastVoucher,
                'mode'         => $company->afip_mode,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Error de conexión: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function writeTempCert(Company $company, string $type): string
    {
        $content = $type === 'cert' ? $company->afip_cert : $company->afip_key;
        $ext     = $type === 'cert' ? '.crt' : '.key';
        $path    = storage_path("app/afip_certs/{$type}_{$company->id}{$ext}");
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, base64_decode($content));
        return $path;
    }
}
