<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * AfipPadronService — Consulta datos de contribuyentes desde AFIP.
 *
 * Usa la API pública de AFIP para obtener datos del padrón:
 * - Razón social / Nombre
 * - Condición ante IVA
 * - Domicilio fiscal
 * - Actividades económicas
 * - Estado del CUIT (activo/inactivo)
 *
 * Cachea resultados por 24hs para evitar saturar AFIP.
 */
class AfipPadronService
{
    // APIs públicas de AFIP para padrón
    private const AFIP_PADRON_URL = 'https://soa.afip.gob.ar/sr-padron/v2/persona/';
    
    // API alternativa (más confiable) - ARCA/AFIP datos públicos
    private const CUITONLINE_URL = 'https://afip.tangofactura.com/Rest/GetContribuyente';

    /**
     * Mapeo de tipos de responsable IVA.
     */
    private const IVA_TYPES = [
        1  => 'IVA Responsable Inscripto',
        4  => 'IVA Sujeto Exento',
        5  => 'Consumidor Final',
        6  => 'Responsable Monotributo',
        8  => 'Proveedor del Exterior',
        9  => 'Cliente del Exterior',
        10 => 'IVA Liberado - Ley Nº 19.640',
        11 => 'IVA Responsable Inscripto - Agente de Percepción',
        13 => 'Monotributista Social',
        15 => 'IVA No Alcanzado',
    ];

    /**
     * Mapeo condición IVA → tipo de factura.
     */
    private const IVA_TO_INVOICE_TYPE = [
        'IVA Responsable Inscripto' => 'A',
        'IVA Sujeto Exento'         => 'B',
        'Consumidor Final'          => 'B',
        'Responsable Monotributo'   => 'B',
        'Monotributista Social'     => 'B',
    ];

    /**
     * Consulta datos de un contribuyente por CUIT.
     *
     * @param string $cuit CUIT sin guiones (ej: 20123456789)
     * @return array Datos normalizados del contribuyente
     * @throws \Exception si el CUIT no es válido o no se encuentra
     */
    public function getContribuyente(string $cuit): array
    {
        $cuit = preg_replace('/\D/', '', $cuit);

        if (strlen($cuit) !== 11) {
            throw new \InvalidArgumentException('El CUIT debe tener 11 dígitos.');
        }

        if (! $this->validarCuit($cuit)) {
            throw new \InvalidArgumentException('El CUIT no es válido (dígito verificador incorrecto).');
        }

        // Cachear por 24 hs
        return Cache::remember("afip_padron_{$cuit}", 86400, function () use ($cuit) {
            return $this->fetchFromAfip($cuit);
        });
    }

    /**
     * Intenta obtener datos del contribuyente desde AFIP.
     * Prueba múltiples fuentes para mayor resiliencia.
     */
    private function fetchFromAfip(string $cuit): array
    {
        // Intento 1: API pública de facturación (más estable)
        try {
            return $this->fetchFromTangoApi($cuit);
        } catch (\Exception $e) {
            // Fallback: intentar con el SDK de AFIP
        }

        // Intento 2: API directa de AFIP
        try {
            return $this->fetchFromAfipDirect($cuit);
        } catch (\Exception $e) {
            throw new \RuntimeException("No se pudieron obtener datos para CUIT {$cuit}: {$e->getMessage()}");
        }
    }

    /**
     * Consulta via API de TangoFactura (proxy confiable de AFIP).
     */
    private function fetchFromTangoApi(string $cuit): array
    {
        $response = Http::timeout(10)
            ->get(self::CUITONLINE_URL, [
                'cuit' => $cuit,
            ]);

        if (! $response->ok()) {
            throw new \RuntimeException("API respondió con status {$response->status()}");
        }

        $data = $response->json();

        if (empty($data) || isset($data['errorGetData']) || ($data['Contribuyente'] ?? null) === null) {
            throw new \RuntimeException('Contribuyente no encontrado.');
        }

        $contrib = $data['Contribuyente'] ?? $data;

        return $this->normalizeData([
            'cuit'             => $cuit,
            'razon_social'     => $contrib['nombre'] ?? $contrib['razonSocial'] ?? '',
            'tipo_persona'     => $contrib['tipoPersona'] ?? '',
            'condicion_iva'    => $this->parseCondicionIva($contrib),
            'domicilio'        => $this->parseDomicilio($contrib),
            'actividad'        => $contrib['actividadPrincipal'] ?? '',
            'estado'           => $contrib['estadoClave'] ?? 'ACTIVO',
            'monotributo'      => $contrib['EsMonotributo'] ?? false,
            'empleador'        => $contrib['EsEmpleador'] ?? false,
            'fecha_inscripcion'=> $contrib['fechaInscripcion'] ?? null,
        ]);
    }

    /**
     * Consulta directa a AFIP sr-padron.
     */
    private function fetchFromAfipDirect(string $cuit): array
    {
        $response = Http::timeout(15)
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->get(self::AFIP_PADRON_URL . $cuit);

        if (! $response->ok()) {
            throw new \RuntimeException("AFIP respondió con status {$response->status()}");
        }

        $data = $response->json();

        if (! isset($data['persona'])) {
            throw new \RuntimeException('CUIT no encontrado en el padrón de AFIP.');
        }

        $persona = $data['persona'];

        $nombre = $persona['tipoPersona'] === 'JURIDICA'
            ? $persona['razonSocial'] ?? ''
            : trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? ''));

        $idIva = collect($persona['impuestos'] ?? [])->first(fn ($i) => $i['idImpuesto'] === 32);
        $condicion = $idIva ? (self::IVA_TYPES[$idIva['estado'] ?? 0] ?? 'Desconocido') : 'Desconocido';

        // Detectar monotributo
        $esMono = collect($persona['impuestos'] ?? [])->contains(fn ($i) => $i['idImpuesto'] === 20);
        if ($esMono) {
            $condicion = 'Responsable Monotributo';
        }

        $domicilio = '';
        if (! empty($persona['domicilioFiscal'])) {
            $dom = $persona['domicilioFiscal'];
            $domicilio = trim(($dom['direccion'] ?? '') . ', ' . ($dom['localidad'] ?? '') . ', ' . ($dom['descripcionProvincia'] ?? ''));
        }

        return $this->normalizeData([
            'cuit'             => $cuit,
            'razon_social'     => $nombre,
            'tipo_persona'     => $persona['tipoPersona'] ?? '',
            'condicion_iva'    => $condicion,
            'domicilio'        => $domicilio,
            'actividad'        => $persona['descripcionActividadPrincipal'] ?? '',
            'estado'           => $persona['estadoClave'] ?? 'ACTIVO',
            'monotributo'      => $esMono,
            'empleador'        => false,
            'fecha_inscripcion'=> null,
        ]);
    }

    /**
     * Normaliza los datos para respuesta consistente.
     */
    private function normalizeData(array $raw): array
    {
        $condicion = $raw['condicion_iva'] ?? 'Consumidor Final';
        $tipoFactura = self::IVA_TO_INVOICE_TYPE[$condicion] ?? 'B';

        return [
            'cuit'              => $this->formatCuit($raw['cuit']),
            'cuit_raw'          => preg_replace('/\D/', '', $raw['cuit']),
            'razon_social'      => mb_convert_case(mb_strtolower($raw['razon_social']), MB_CASE_TITLE, 'UTF-8'),
            'tipo_persona'      => $raw['tipo_persona'],
            'condicion_iva'     => $condicion,
            'tipo_factura'      => $tipoFactura,
            'domicilio'         => $raw['domicilio'],
            'actividad'         => $raw['actividad'],
            'estado'            => $raw['estado'],
            'activo'            => strtoupper($raw['estado']) === 'ACTIVO',
            'monotributo'       => (bool) $raw['monotributo'],
            'empleador'         => (bool) $raw['empleador'],
            'fecha_inscripcion' => $raw['fecha_inscripcion'],
        ];
    }

    /**
     * Parsea la condición IVA desde distintas estructuras de respuesta.
     */
    private function parseCondicionIva(array $data): string
    {
        if (! empty($data['condicionIva'])) {
            return $data['condicionIva'];
        }

        if (isset($data['idTipoContribuyente'])) {
            return self::IVA_TYPES[$data['idTipoContribuyente']] ?? 'Consumidor Final';
        }

        if (! empty($data['EsMonotributo'])) {
            return 'Responsable Monotributo';
        }

        return 'Consumidor Final';
    }

    /**
     * Parsea el domicilio fiscal desde la respuesta.
     */
    private function parseDomicilio(array $data): string
    {
        if (! empty($data['domicilioFiscal'])) {
            if (is_string($data['domicilioFiscal'])) {
                return $data['domicilioFiscal'];
            }
            $dom = $data['domicilioFiscal'];
            return trim(($dom['direccion'] ?? '') . ', ' . ($dom['localidad'] ?? '') . ', ' . ($dom['provincia'] ?? ''));
        }

        return $data['domicilio'] ?? '';
    }

    /**
     * Formatea CUIT con guiones: XX-XXXXXXXX-X
     */
    public function formatCuit(string $cuit): string
    {
        $cuit = preg_replace('/\D/', '', $cuit);
        if (strlen($cuit) !== 11) return $cuit;
        return substr($cuit, 0, 2) . '-' . substr($cuit, 2, 8) . '-' . substr($cuit, 10, 1);
    }

    /**
     * Valida un CUIT argentino por dígito verificador.
     */
    public function validarCuit(string $cuit): bool
    {
        $cuit = preg_replace('/\D/', '', $cuit);
        if (strlen($cuit) !== 11) return false;

        $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum  = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $cuit[$i] * $mult[$i];
        }
        $mod = 11 - ($sum % 11);
        if ($mod === 11) $mod = 0;
        if ($mod === 10) $mod = 9;

        return (int) $cuit[10] === $mod;
    }
}
