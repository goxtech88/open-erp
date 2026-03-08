<?php

namespace App\Services;

use App\Models\Invoice;
use Afip;

/**
 * AfipService — Encapsula la comunicación con los WebServices de AFIP.
 *
 * Dependencia: afipsdk/afip-php
 * Docs: https://github.com/afipsdk/afip.php
 *
 * Cada empresa tiene su propio CUIT + certificado, por eso el SDK
 * se instancia por empresa cuando se autoriza un comprobante.
 */
class AfipService
{
    /**
     * Instancia el SDK de AFIP para una empresa específica.
     */
    private function sdkForCompany(\App\Models\Company $company): Afip
    {
        // Guardar cert y key en archivos temporales si vienen en base64
        $certPath = $this->writeTempFile('cert_' . $company->id . '.crt', base64_decode($company->afip_cert));
        $keyPath  = $this->writeTempFile('key_'  . $company->id . '.key', base64_decode($company->afip_key));

        return new Afip([
            'CUIT'           => (int) $company->cuit,
            'cert'           => $certPath,
            'key'            => $keyPath,
            'production'     => $company->afip_mode === 'produccion',
            'res_folder'     => storage_path('app/afip_tokens'),
            'ws'             => 'wsfe',
        ]);
    }

    private function writeTempFile(string $name, string $content): string
    {
        $path = storage_path('app/afip_certs/' . $name);
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Autoriza un comprobante ante AFIP WSFEv1 y persiste CAE + vencimiento.
     *
     * @throws \Exception con el mensaje de error de AFIP.
     */
    public function authorize(Invoice $invoice): Invoice
    {
        $company = $invoice->company;

        $afip       = $this->sdkForCompany($company);
        $wsfe       = $afip->ElectronicBilling;

        // Obtener el último número de comprobante emitido
        $lastNumber = $wsfe->getLastVoucher(
            $invoice->pos_number,
            $this->afipVoucherType($invoice->invoice_code)
        );

        $newNumber  = $lastNumber + 1;

        // Construir la estructura del comprobante
        $voucherId  = $this->afipVoucherType($invoice->invoice_code);
        $docType    = $invoice->customer?->fiscal_type === 'CUIT' ? 80 : 96; // CUIT=80, DNI=96
        $docNumber  = (int) preg_replace('/\D/', '', $invoice->customer?->fiscal_id ?? '0');

        $coupon = [
            'CantReg'      => 1,
            'PtoVta'       => $invoice->pos_number,
            'CbteTipo'     => $voucherId,
            'Concepto'     => 1,          // 1=Productos 2=Servicios 3=Ambos
            'DocTipo'      => $docType,
            'DocNro'       => $docNumber,
            'CbteDesde'    => $newNumber,
            'CbteHasta'    => $newNumber,
            'CbteFch'      => $invoice->date->format('Ymd'),
            'ImpTotal'     => (float) $invoice->total,
            'ImpTotConc'   => 0,          // No gravado
            'ImpNeto'      => (float) $invoice->net,
            'ImpOpEx'      => 0,          // Exento
            'ImpIVA'       => (float) $invoice->vat,
            'ImpTrib'      => 0,
            'MonId'        => 'PES',
            'MonCotiz'     => 1,
            'Iva'          => $this->buildIvaArray($invoice),
        ];

        $result = $wsfe->createVoucher($coupon);

        // Verificar resultado
        $cae       = $result['CAE']       ?? null;
        $caeExpiry = $result['CAEFchVto'] ?? null;

        if (! $cae) {
            $obs = $result['Observaciones'] ?? json_encode($result);
            throw new \RuntimeException("AFIP rechazó el comprobante: {$obs}");
        }

        // Persistir
        $invoice->update([
            'number'     => $newNumber,
            'status'     => Invoice::STATUS_AUTHORIZED,
            'cae'        => $cae,
            'cae_expiry' => \Carbon\Carbon::createFromFormat('Ymd', $caeExpiry),
        ]);

        return $invoice->fresh();
    }

    // ── Mapeos AFIP ──────────────────────────────────────────────────────────

    /** Devuelve el CbteTipo AFIP según la letra del comprobante. */
    private function afipVoucherType(string $invoiceCode): int
    {
        return match (strtoupper($invoiceCode)) {
            'A'  => 1,
            'B'  => 6,
            'C'  => 11,
            'M'  => 51,
            'X'  => 0,   // Sin validación AFIP
            default => 6
        };
    }

    /** Agrupa las líneas por alícuota IVA para el array Iva de AFIP. */
    private function buildIvaArray(Invoice $invoice): array
    {
        $grouped = $invoice->lines->groupBy('vat_rate');
        $ivaMap  = [0 => 3, 10.5 => 4, 21 => 5, 27 => 6]; // Id AFIP por alícuota

        return $grouped->map(function ($lines, $rate) use ($ivaMap) {
            $base    = $lines->sum('subtotal');
            $vatAmt  = round($base * ($rate / 100), 2);
            return [
                'Id'      => $ivaMap[(float) $rate] ?? 5,
                'BaseImp' => (float) $base,
                'Importe' => $vatAmt,
            ];
        })->values()->toArray();
    }
}
