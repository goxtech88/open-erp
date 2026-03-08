@extends('layouts.app')
@section('title', 'AFIP — Facturación Electrónica')

@section('content')
<div x-data="afipPanel()" x-init="init()">

    {{-- ── Stats Cards ──────────────────────────────────────────────── --}}
    <div class="stats-grid" style="margin-bottom:24px">
        <div class="stat-card">
            <div class="stat-label">Comprobantes Autorizados</div>
            <div class="stat-value stat-success">{{ number_format($stats['total_authorized']) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Borradores Pendientes</div>
            <div class="stat-value stat-warn">{{ $stats['pending_draft'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Facturado este Mes</div>
            <div class="stat-value stat-accent">$ {{ number_format($stats['month_total'], 2, ',', '.') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Último CAE</div>
            <div class="stat-value" style="font-size:14px;color:var(--color-success)">
                {{ $stats['last_cae']?->cae ?? '—' }}
            </div>
            @if($stats['last_cae']?->cae_expiry)
                <div style="font-size:11px;color:var(--color-muted);margin-top:4px">
                    Vto: {{ $stats['last_cae']->cae_expiry->format('d/m/Y') }}
                </div>
            @endif
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

        {{-- ── Consulta CUIT (Padrón AFIP) ──────────────────────────── --}}
        <div class="card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-accent)" stroke-width="2" style="width:24px;height:24px">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <strong style="font-size:16px">Consultar CUIT en AFIP</strong>
            </div>

            <div style="display:flex;gap:10px;margin-bottom:16px">
                <div style="flex:1">
                    <input class="input" x-model="cuitQuery"
                        placeholder="Ej: 20-12345678-9 o 20123456789"
                        @keydown.enter.prevent="consultarCuit()"
                        maxlength="13"
                        style="font-size:16px;letter-spacing:1px">
                </div>
                <button type="button" class="btn btn-primary"
                    @click="consultarCuit()" :disabled="loading">
                    <span x-show="!loading">Buscar</span>
                    <span x-show="loading">Buscando…</span>
                </button>
            </div>

            {{-- Error --}}
            <template x-if="error">
                <div class="alert alert-error" style="margin:0 0 16px" x-text="error"></div>
            </template>

            {{-- Resultado --}}
            <template x-if="contribuyente">
                <div style="border:1px solid var(--color-border);border-radius:var(--radius);overflow:hidden">
                    {{-- Header con estado --}}
                    <div :style="`padding:14px 16px;background:${contribuyente.activo ? 'rgba(34,197,94,.1)' : 'rgba(239,68,68,.1)'};display:flex;justify-content:space-between;align-items:center`">
                        <div>
                            <div style="font-size:11px;color:var(--color-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em">Razón Social</div>
                            <div style="font-size:17px;font-weight:700" x-text="contribuyente.razon_social"></div>
                        </div>
                        <span class="badge" :class="contribuyente.activo ? 'badge-authorized' : 'badge-cancelled'"
                              x-text="contribuyente.activo ? 'ACTIVO' : 'INACTIVO'"></span>
                    </div>
                    {{-- Datos --}}
                    <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <div class="form-label">CUIT</div>
                            <div style="font-weight:600;font-size:15px;letter-spacing:1px" x-text="contribuyente.cuit"></div>
                        </div>
                        <div>
                            <div class="form-label">Condición IVA</div>
                            <div style="font-weight:600" x-text="contribuyente.condicion_iva"></div>
                        </div>
                        <div>
                            <div class="form-label">Tipo de Factura</div>
                            <div>
                                <span class="badge" style="background:rgba(99,102,241,.15);color:var(--color-accent);font-size:14px;padding:4px 12px"
                                      x-text="'Factura ' + contribuyente.tipo_factura"></span>
                            </div>
                        </div>
                        <div>
                            <div class="form-label">Tipo Persona</div>
                            <div x-text="contribuyente.tipo_persona || '—'"></div>
                        </div>
                        <div style="grid-column:1/-1">
                            <div class="form-label">Domicilio Fiscal</div>
                            <div x-text="contribuyente.domicilio || '—'"></div>
                        </div>
                        <div style="grid-column:1/-1">
                            <div class="form-label">Actividad Principal</div>
                            <div x-text="contribuyente.actividad || '—'" style="font-size:13px"></div>
                        </div>
                    </div>
                    {{-- Acciones --}}
                    <div style="padding:12px 16px;border-top:1px solid var(--color-border);display:flex;gap:10px">
                        <button type="button" class="btn btn-primary btn-sm" @click="crearClienteDesdeAfip()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                            Crear como Cliente
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" @click="copiarDatos()">
                            Copiar Datos
                        </button>
                    </div>
                </div>
            </template>

            {{-- Help --}}
            <div style="margin-top:16px;font-size:12px;color:var(--color-muted);line-height:1.6">
                <strong>¿Qué datos trae?</strong> Razón social, condición ante IVA, domicilio fiscal,
                actividad económica y tipo de factura recomendado. Los datos se obtienen del
                padrón público de AFIP y se cachean por 24hs.
            </div>
        </div>

        {{-- ── Configuración AFIP ───────────────────────────────────── --}}
        <div class="card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-accent)" stroke-width="2" style="width:24px;height:24px">
                    <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                </svg>
                <strong style="font-size:16px">Configuración AFIP</strong>
            </div>

            <form method="POST" action="{{ route('afip.config.update') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">CUIT de la Empresa *</label>
                        <input class="input" name="cuit"
                            value="{{ old('cuit', $company->cuit) }}"
                            placeholder="20-12345678-9" required
                            style="font-size:15px;letter-spacing:1px">
                        @error('cuit')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Modo</label>
                        <select class="select" name="afip_mode">
                            <option value="homologacion" @selected(old('afip_mode', $company->afip_mode) === 'homologacion')>
                                🧪 Homologación (Testing)
                            </option>
                            <option value="produccion" @selected(old('afip_mode', $company->afip_mode) === 'produccion')>
                                🏭 Producción
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Certificado AFIP (.crt / .pem)</label>
                        <input class="input" type="file" name="afip_cert" accept=".crt,.pem,.txt"
                            style="padding:6px">
                        @if($company->afip_cert)
                            <span style="font-size:11px;color:var(--color-success)">✓ Certificado cargado</span>
                        @endif
                    </div>

                    <div class="form-group">
                        <label class="form-label">Clave Privada (.key / .pem)</label>
                        <input class="input" type="file" name="afip_key" accept=".key,.pem,.txt"
                            style="padding:6px">
                        @if($company->afip_key)
                            <span style="font-size:11px;color:var(--color-success)">✓ Clave cargada</span>
                        @endif
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:4px">
                    <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                    <button type="button" class="btn btn-outline" @click="testConnection()">
                        Probar Conexión
                    </button>
                </div>
            </form>

            {{-- Resultado del test --}}
            <template x-if="testResult">
                <div :class="testResult.success ? 'alert alert-success' : 'alert alert-error'"
                     style="margin:16px 0 0">
                    <div x-text="testResult.success ? testResult.message : testResult.error"></div>
                    <template x-if="testResult.last_voucher !== undefined">
                        <div style="margin-top:4px;font-size:12px">
                            Último comprobante: <strong x-text="testResult.last_voucher"></strong>
                            | Modo: <strong x-text="testResult.mode"></strong>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Estado actual --}}
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--color-border)">
                <div class="form-label" style="margin-bottom:8px">Estado de Configuración</div>
                <div style="display:flex;flex-direction:column;gap:6px;font-size:13px">
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="color:{{ $company->cuit ? 'var(--color-success)' : 'var(--color-danger)' }}">
                            {{ $company->cuit ? '✓' : '✗' }}
                        </span>
                        CUIT: {{ $company->cuit ? $company->cuit : 'No configurado' }}
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="color:{{ $company->afip_cert ? 'var(--color-success)' : 'var(--color-danger)' }}">
                            {{ $company->afip_cert ? '✓' : '✗' }}
                        </span>
                        Certificado: {{ $company->afip_cert ? 'Cargado' : 'Pendiente' }}
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="color:{{ $company->afip_key ? 'var(--color-success)' : 'var(--color-danger)' }}">
                            {{ $company->afip_key ? '✓' : '✗' }}
                        </span>
                        Clave privada: {{ $company->afip_key ? 'Cargada' : 'Pendiente' }}
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="color:var(--color-accent)">⚙</span>
                        Modo: <strong>{{ $company->afip_mode === 'produccion' ? '🏭 Producción' : '🧪 Homologación' }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('head')
<script>
function afipPanel() {
    return {
        cuitQuery: '',
        contribuyente: null,
        loading: false,
        error: null,
        testResult: null,

        init() {},

        async consultarCuit() {
            if (!this.cuitQuery || this.cuitQuery.replace(/\D/g, '').length < 11) {
                this.error = 'Ingresá un CUIT válido de 11 dígitos.';
                return;
            }

            this.loading = true;
            this.error = null;
            this.contribuyente = null;

            try {
                const res = await fetch('{{ route("afip.consultar") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ cuit: this.cuitQuery }),
                });

                const json = await res.json();

                if (json.success) {
                    this.contribuyente = json.data;
                } else {
                    this.error = json.error || 'No se encontraron datos para ese CUIT.';
                }
            } catch (e) {
                this.error = 'Error de conexión. Verificá tu conexión a internet.';
            } finally {
                this.loading = false;
            }
        },

        crearClienteDesdeAfip() {
            if (!this.contribuyente) return;
            const c = this.contribuyente;
            // Redirigir al form de nuevo cliente con datos pre-cargados via query params
            const params = new URLSearchParams({
                name: c.razon_social,
                fiscal_id: c.cuit_raw,
                fiscal_type: 'CUIT',
                address: c.domicilio || '',
                code: c.cuit_raw,
            });
            window.location.href = '{{ route("customers.create") }}?' + params.toString();
        },

        copiarDatos() {
            if (!this.contribuyente) return;
            const c = this.contribuyente;
            const text = `CUIT: ${c.cuit}\nRazón Social: ${c.razon_social}\nCondición IVA: ${c.condicion_iva}\nDomicilio: ${c.domicilio}\nActividad: ${c.actividad}`;
            navigator.clipboard.writeText(text).then(() => {
                alert('Datos copiados al portapapeles');
            });
        },

        async testConnection() {
            this.testResult = null;
            try {
                const res = await fetch('{{ route("afip.test") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });
                this.testResult = await res.json();
            } catch (e) {
                this.testResult = { success: false, error: 'Error de red.' };
            }
        },
    };
}
</script>
@endpush
@endsection
