@extends('layouts.app')
@section('title', 'Importar desde Factusol')

@section('content')
<div x-data="factusolImport()">

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
        <div style="width:44px;height:44px;border-radius:var(--radius);background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:20px">
            📂
        </div>
        <div>
            <h2 style="margin:0;font-size:18px">Importar datos de Factusol</h2>
            <p style="margin:0;font-size:13px;color:var(--color-muted)">
                Subí archivos CSV exportados desde Factusol para migrar artículos, clientes, proveedores y facturas.
            </p>
        </div>
    </div>

    {{-- ── Success / Error ──────────────────────────────────────── --}}
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:20px">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-error" style="margin-bottom:20px">
            {{ session('error') }}
        </div>
    @endif

    {{-- ── Error details ────────────────────────────────────────── --}}
    @if(session('import_stats') && count(session('import_stats')['errors'] ?? []) > 0)
        <div class="card" style="margin-bottom:20px;border-left:3px solid var(--color-danger)">
            <strong style="color:var(--color-danger)">Errores durante la importación:</strong>
            <ul style="margin:8px 0 0;padding-left:20px;font-size:12px;color:var(--color-muted)">
                @foreach(array_slice(session('import_stats')['errors'], 0, 20) as $err)
                    <li>{{ $err }}</li>
                @endforeach
                @if(count(session('import_stats')['errors']) > 20)
                    <li>… y {{ count(session('import_stats')['errors']) - 20 }} errores más</li>
                @endif
            </ul>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

        {{-- ── Formulario de importación ─────────────────────────── --}}
        <div class="card">
            <strong style="display:block;margin-bottom:16px;font-size:15px">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-accent)" stroke-width="2" style="width:18px;height:18px;vertical-align:middle;margin-right:6px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Subir Archivo CSV
            </strong>

            <form method="POST" action="{{ route('factusol.import') }}" enctype="multipart/form-data"
                  @submit="importing = true">
                @csrf

                {{-- Tipo de importación --}}
                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">¿Qué querés importar?</label>
                    <select class="select" name="import_type" x-model="importType" required>
                        <option value="">— Seleccionar —</option>
                        <option value="articles">📦 Artículos (F_ART)</option>
                        <option value="customers">👤 Clientes</option>
                        <option value="suppliers">🏭 Proveedores</option>
                        <option value="sale_invoices">🧾 Facturas de Venta (F_FAC + F_LFA)</option>
                        <option value="purchase_invoices">📥 Facturas Recibidas (F_FRE + F_LFR)</option>
                    </select>
                </div>

                {{-- Archivo principal --}}
                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">
                        <span x-text="mainFileLabel()">Archivo CSV</span> *
                    </label>
                    <input class="input" type="file" name="file_main" accept=".csv,.txt,.tsv" required
                        style="padding:8px"
                        @change="previewFile($event)">
                    <p style="font-size:11px;color:var(--color-muted);margin-top:4px">
                        Máximo 20MB. Encoding: UTF-8, Windows-1252 o ISO-8859-1.
                    </p>
                </div>

                {{-- Archivo de líneas (solo para facturas) --}}
                <template x-if="importType === 'sale_invoices' || importType === 'purchase_invoices'">
                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label">
                            <span x-text="importType === 'sale_invoices' ? 'Líneas de detalle (F_LFA)' : 'Líneas de detalle (F_LFR)'"></span>
                        </label>
                        <input class="input" type="file" name="file_lines" accept=".csv,.txt,.tsv"
                            style="padding:8px">
                        <p style="font-size:11px;color:var(--color-muted);margin-top:4px">
                            Opcional. Si no lo subís, las facturas se importan sin el detalle de líneas.
                        </p>
                    </div>
                </template>

                <button type="submit" class="btn btn-primary" :disabled="importing || !importType"
                    style="width:100%;margin-top:4px">
                    <span x-show="!importing">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Importar
                    </span>
                    <span x-show="importing">⏳ Importando... por favor esperá</span>
                </button>
            </form>
        </div>

        {{-- ── Preview & Ayuda ──────────────────────────────────── --}}
        <div>
            {{-- Preview de datos --}}
            <template x-if="preview">
                <div class="card" style="margin-bottom:16px">
                    <strong style="display:block;margin-bottom:8px">
                        Vista previa: <span x-text="preview.total_rows"></span> registros
                    </strong>
                    <div style="font-size:11px;color:var(--color-muted);margin-bottom:8px">
                        Columnas: <span x-text="preview.columns.join(', ')"></span>
                    </div>
                    <div class="table-wrap" style="max-height:300px;overflow:auto">
                        <table style="font-size:11px">
                            <thead>
                                <tr>
                                    <template x-for="col in preview.columns" :key="col">
                                        <th style="white-space:nowrap;padding:4px 8px" x-text="col"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, idx) in preview.preview" :key="idx">
                                    <tr>
                                        <template x-for="col in preview.columns" :key="col">
                                            <td style="white-space:nowrap;padding:3px 8px;max-width:200px;overflow:hidden;text-overflow:ellipsis"
                                                x-text="row[col] || '—'"></td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            {{-- Guía de mapeo --}}
            <div class="card">
                <strong style="display:block;margin-bottom:12px">
                    📋 Guía de Columnas Esperadas
                </strong>

                <div x-show="!importType || importType === 'articles'" style="margin-bottom:12px">
                    <div class="form-label" style="margin-bottom:4px">Artículos (F_ART)</div>
                    <code style="font-size:11px;display:block;color:var(--color-success)">CODART, DESART, FAMART, PHAART, PCOART, IVALIN</code>
                    <p style="font-size:11px;color:var(--color-muted);margin-top:2px">
                        Código, Descripción, Familia, Precio Venta, Precio Compra, Tipo IVA
                    </p>
                </div>

                <div x-show="!importType || importType === 'customers'" style="margin-bottom:12px">
                    <div class="form-label" style="margin-bottom:4px">Clientes</div>
                    <code style="font-size:11px;display:block;color:var(--color-success)">CODCLI, NOMCLI, CIFCLI, DOMCLI, TELCLI, EMACLI</code>
                    <p style="font-size:11px;color:var(--color-muted);margin-top:2px">
                        Código, Nombre, CUIT/DNI, Domicilio, Teléfono, Email
                    </p>
                </div>

                <div x-show="!importType || importType === 'suppliers'" style="margin-bottom:12px">
                    <div class="form-label" style="margin-bottom:4px">Proveedores</div>
                    <code style="font-size:11px;display:block;color:var(--color-success)">CODPRO, NOMPRO, CIFPRO, DOMPRO, TELPRO, EMAPRO</code>
                </div>

                <div x-show="!importType || importType === 'sale_invoices'" style="margin-bottom:12px">
                    <div class="form-label" style="margin-bottom:4px">Facturas Venta — Cabecera (F_FAC)</div>
                    <code style="font-size:11px;display:block;color:var(--color-success)">TIPFAC, CODFAC, CLIFAC, CNOFAC, FECFAC, TOTFAC</code>
                    <div class="form-label" style="margin:8px 0 4px">Líneas (F_LFA)</div>
                    <code style="font-size:11px;display:block;color:var(--color-success)">TIPLFA, CODLFA, POSLFA, ARTLFA, DESLFA, CANLFA, PRELFA, TOTLFA, PIVLFA, DT1LFA, DT2LFA, DT3LFA</code>
                </div>

                <div x-show="!importType || importType === 'purchase_invoices'" style="margin-bottom:12px">
                    <div class="form-label" style="margin-bottom:4px">Facturas Recibidas — Cabecera (F_FRE)</div>
                    <code style="font-size:11px;display:block;color:var(--color-success)">TIPFRE, CODFRE, PROFRE, FECFRE, TOTFRE</code>
                    <div class="form-label" style="margin:8px 0 4px">Líneas (F_LFR)</div>
                    <code style="font-size:11px;display:block;color:var(--color-success)">TIPLFR, CODLFR, POSLFR, ARTLFR, CANLFR, PCOLFR, TOTLFR</code>
                </div>

                <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--color-border);font-size:12px;color:var(--color-muted);line-height:1.6">
                    <strong>¿Cómo exportar desde Factusol?</strong><br>
                    1. Abrí la base .mdb con Access o MDBViewer<br>
                    2. Seleccioná la tabla (ej: F_ART)<br>
                    3. Exportá como CSV (separador ; o , )<br>
                    4. Subí el archivo acá<br><br>
                    💡 Los registros existentes se actualizan automáticamente (no se duplican).
                </div>
            </div>
        </div>
    </div>
</div>

@push('head')
<script>
function factusolImport() {
    return {
        importType: '',
        importing: false,
        preview: null,

        mainFileLabel() {
            const labels = {
                articles: 'CSV de Artículos (F_ART)',
                customers: 'CSV de Clientes',
                suppliers: 'CSV de Proveedores',
                sale_invoices: 'CSV Cabeceras de Venta (F_FAC)',
                purchase_invoices: 'CSV Cabeceras de Compra (F_FRE)',
            };
            return labels[this.importType] || 'Archivo CSV *';
        },

        async previewFile(event) {
            const file = event.target.files[0];
            if (!file) return;

            this.preview = null;
            const formData = new FormData();
            formData.append('file', file);

            try {
                const res = await fetch('{{ route("factusol.preview") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const json = await res.json();
                if (json.success) {
                    this.preview = json;
                }
            } catch (e) {
                console.error('Preview error:', e);
            }
        },
    };
}
</script>
@endpush
@endsection
