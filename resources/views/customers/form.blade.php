@extends('layouts.app')
@section('title', $customer->exists ? 'Editar Cliente' : 'Nuevo Cliente')

@section('content')
<div class="card" style="max-width:780px" x-data="customerForm()">
    <form method="POST"
        action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
        @csrf
        @if($customer->exists) @method('PUT') @endif

        <div class="form-grid">
            {{-- CUIT con botón de consulta AFIP --}}
            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">CUIT / DNI — Buscar datos en AFIP</label>
                <div style="display:flex;gap:10px">
                    <input class="input" name="fiscal_id" x-model="fiscalId"
                        value="{{ old('fiscal_id', request('fiscal_id', $customer->fiscal_id)) }}"
                        placeholder="Ej: 20-12345678-9" maxlength="20"
                        @keydown.enter.prevent="buscarCuit()"
                        style="flex:1;font-size:15px;letter-spacing:1px">
                    <select class="select" name="fiscal_type" x-model="fiscalType" style="width:120px">
                        <option value="">— Tipo —</option>
                        @foreach(['DNI','CUIT','PASSPORT'] as $t)
                            <option value="{{ $t }}" @selected(old('fiscal_type', request('fiscal_type', $customer->fiscal_type)) === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary" @click="buscarCuit()" :disabled="loading"
                            title="Traer datos del padrón de AFIP">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <span x-show="!loading">AFIP</span>
                        <span x-show="loading">…</span>
                    </button>
                </div>
                @error('fiscal_id')<p class="form-error">{{ $message }}</p>@enderror

                {{-- AFIP lookup result banner --}}
                <template x-if="afipResult">
                    <div style="margin-top:8px;padding:10px 14px;border-radius:var(--radius);font-size:12px"
                         :style="afipResult.success ? 'background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2)' : 'background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)'">
                        <template x-if="afipResult.success">
                            <div>
                                <strong style="color:var(--color-success)">✓ Datos cargados desde AFIP</strong>
                                <span style="margin-left:8px" x-text="afipResult.data.condicion_iva"></span>
                                <span style="margin-left:8px;color:var(--color-accent)" x-text="'→ Factura ' + afipResult.data.tipo_factura"></span>
                            </div>
                        </template>
                        <template x-if="!afipResult.success">
                            <div style="color:var(--color-danger)" x-text="afipResult.error"></div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="form-group">
                <label class="form-label">Código *</label>
                <input class="input" name="code" x-model="code"
                    value="{{ old('code', request('code', $customer->code)) }}" required maxlength="20">
                @error('code')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Razón Social / Nombre *</label>
                <input class="input" name="name" x-model="name"
                    value="{{ old('name', request('name', $customer->name)) }}" required>
                @error('name')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Domicilio</label>
                <input class="input" name="address" x-model="address"
                    value="{{ old('address', request('address', $customer->address)) }}">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input class="input" type="email" name="email"
                    value="{{ old('email', $customer->email) }}">
                @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Teléfono</label>
                <input class="input" name="phone"
                    value="{{ old('phone', $customer->phone) }}" maxlength="30">
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:4px">
            <button type="submit" class="btn btn-primary">
                {{ $customer->exists ? 'Guardar Cambios' : 'Crear Cliente' }}
            </button>
            <a href="{{ route('customers.index') }}" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>

@push('head')
<script>
function customerForm() {
    return {
        fiscalId:   '{{ old("fiscal_id", request("fiscal_id", $customer->fiscal_id ?? "")) }}',
        fiscalType: '{{ old("fiscal_type", request("fiscal_type", $customer->fiscal_type ?? "")) }}',
        name:       '{{ old("name", request("name", $customer->name ?? "")) }}',
        code:       '{{ old("code", request("code", $customer->code ?? "")) }}',
        address:    '{{ old("address", request("address", $customer->address ?? "")) }}',
        loading: false,
        afipResult: null,

        async buscarCuit() {
            const cuit = this.fiscalId.replace(/\D/g, '');
            if (cuit.length < 11) {
                this.afipResult = { success: false, error: 'Ingresá un CUIT válido de 11 dígitos.' };
                return;
            }

            this.loading = true;
            this.afipResult = null;

            try {
                const res = await fetch('{{ route("afip.consultar") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ cuit: cuit }),
                });
                const json = await res.json();

                if (json.success && json.data) {
                    this.afipResult = json;
                    // Auto-fill fields
                    this.name = json.data.razon_social;
                    this.address = json.data.domicilio || '';
                    this.fiscalType = 'CUIT';
                    this.fiscalId = json.data.cuit;
                    if (!this.code) {
                        this.code = json.data.cuit_raw;
                    }
                } else {
                    this.afipResult = { success: false, error: json.error || 'No se encontraron datos.' };
                }
            } catch (e) {
                this.afipResult = { success: false, error: 'Error de conexión con AFIP.' };
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
@endpush
@endsection
