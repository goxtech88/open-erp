@extends('layouts.app')
@section('title', ($invoice->exists ? 'Editar' : 'Nuevo') . ' ' . ($type === 'sale' ? 'Venta' : 'Compra'))

@section('content')
{{-- Alpine.js component: manages dynamic lines and totals --}}
<form
    method="POST"
    action="{{ $invoice->exists
        ? ($type === 'sale' ? route('invoices.sales.update', $invoice) : route('invoices.purchases.update', $invoice))
        : ($type === 'sale' ? route('invoices.sales.store')             : route('invoices.purchases.store')) }}"
    x-data="invoiceForm({{ $invoice->exists ? $invoice->lines->toJson() : '[]' }})"
>
    @csrf
    @if($invoice->exists) @method('PUT') @endif

    {{-- ── Header ──────────────────────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:16px">
        <div class="form-grid">
            {{-- Tipo de comprobante --}}
            <div class="form-group">
                <label class="form-label">Tipo *</label>
                <select class="select" name="invoice_code" required>
                    @foreach(['A','B','C','X'] as $code)
                        <option value="{{ $code }}" @selected(old('invoice_code', $invoice->invoice_code) === $code)>
                            Factura {{ $code }}
                        </option>
                    @endforeach
                </select>
                @error('invoice_code')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            {{-- Punto de venta --}}
            <div class="form-group">
                <label class="form-label">Punto de Venta *</label>
                <input class="input" type="number" name="pos_number" min="1"
                    value="{{ old('pos_number', $invoice->pos_number ?? 1) }}" required>
            </div>

            {{-- Fecha --}}
            <div class="form-group">
                <label class="form-label">Fecha *</label>
                <input class="input" type="date" name="date"
                    value="{{ old('date', $invoice->date?->format('Y-m-d') ?? today()->format('Y-m-d')) }}" required>
                @error('date')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            {{-- Cliente / Proveedor --}}
            @if($type === 'sale')
            <div class="form-group">
                <label class="form-label">Cliente *</label>
                <select class="select" name="customer_id" required>
                    <option value="">— Seleccionar cliente —</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" @selected(old('customer_id', $invoice->customer_id) == $c->id)>
                            {{ $c->name }} ({{ $c->fiscal_id }})
                        </option>
                    @endforeach
                </select>
                @error('customer_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            @else
            <div class="form-group">
                <label class="form-label">Proveedor *</label>
                <select class="select" name="supplier_id" required>
                    <option value="">— Seleccionar proveedor —</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" @selected(old('supplier_id', $invoice->supplier_id) == $s->id)>
                            {{ $s->name }} ({{ $s->fiscal_id }})
                        </option>
                    @endforeach
                </select>
                @error('supplier_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            @endif

            {{-- Notas --}}
            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Notas</label>
                <input class="input" name="notes" value="{{ old('notes', $invoice->notes) }}" maxlength="500">
            </div>
        </div>
    </div>

    {{-- ── Líneas de detalle ────────────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <strong>Ítems del Comprobante</strong>
            <button type="button" class="btn btn-outline btn-sm" @click="addLine()">
                + Agregar línea
            </button>
        </div>

        <div class="table-wrap">
            <table class="lines-table">
                <thead>
                    <tr>
                        <th style="width:220px">Artículo</th>
                        <th>Descripción</th>
                        <th style="width:90px;text-align:right">Cant.</th>
                        <th style="width:110px;text-align:right">P. Unit.</th>
                        <th style="width:90px;text-align:right">IVA %</th>
                        <th style="width:120px;text-align:right">Subtotal</th>
                        <th style="width:36px"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, idx) in lines" :key="idx">
                        <tr>
                            {{-- Artículo --}}
                            <td>
                                <select class="select" :name="`lines[${idx}][article_id]`"
                                    @change="fillFromArticle(idx, $event)" x-model="line.article_id">
                                    <option value="">— Libre —</option>
                                    @foreach($articles as $a)
                                    <option value="{{ $a->id }}"
                                        data-desc="{{ $a->description }}"
                                        data-price="{{ $a->sale_price }}">
                                        {{ $a->code }} — {{ $a->description }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                            {{-- Descripción --}}
                            <td>
                                <input class="input" :name="`lines[${idx}][description]`"
                                    x-model="line.description" placeholder="Descripción">
                            </td>
                            {{-- Cantidad --}}
                            <td>
                                <input class="input" type="number" step="0.0001"
                                    :name="`lines[${idx}][quantity]`"
                                    x-model.number="line.quantity"
                                    @input="calcLine(idx)"
                                    style="text-align:right">
                            </td>
                            {{-- Precio unitario --}}
                            <td>
                                <input class="input" type="number" step="0.01"
                                    :name="`lines[${idx}][unit_price]`"
                                    x-model.number="line.unit_price"
                                    @input="calcLine(idx)"
                                    style="text-align:right">
                            </td>
                            {{-- IVA % --}}
                            <td>
                                <select class="select" :name="`lines[${idx}][vat_rate]`"
                                    x-model.number="line.vat_rate" @change="calcLine(idx)"
                                    style="text-align:right">
                                    <option value="0">0 %</option>
                                    <option value="10.5">10.5 %</option>
                                    <option value="21">21 %</option>
                                    <option value="27">27 %</option>
                                </select>
                            </td>
                            {{-- Subtotal --}}
                            <td style="text-align:right">
                                <input type="hidden" :name="`lines[${idx}][subtotal]`" :value="line.subtotal">
                                <span x-text="formatMoney(line.subtotal)" style="font-weight:600"></span>
                            </td>
                            {{-- Remove --}}
                            <td style="text-align:center">
                                <button type="button" @click="removeLine(idx)"
                                    style="color:var(--color-danger);padding:2px 6px;border-radius:4px"
                                    :disabled="lines.length <= 1">✕</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="lines-total-row">
                        <td colspan="5" style="text-align:right;color:var(--color-muted)">Neto:</td>
                        <td style="text-align:right" x-text="formatMoney(totals.net)"></td>
                        <td></td>
                    </tr>
                    <tr class="lines-total-row">
                        <td colspan="5" style="text-align:right;color:var(--color-muted)">IVA:</td>
                        <td style="text-align:right" x-text="formatMoney(totals.vat)"></td>
                        <td></td>
                    </tr>
                    <tr class="lines-total-row" style="font-size:15px">
                        <td colspan="5" style="text-align:right">TOTAL:</td>
                        <td style="text-align:right;color:var(--color-accent)" x-text="formatMoney(totals.total)"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- ── Actions ──────────────────────────────────────────────────────── --}}
    <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">
            {{ $invoice->exists ? 'Guardar Cambios' : 'Guardar Borrador' }}
        </button>
        <a href="{{ $type === 'sale' ? route('invoices.sales') : route('invoices.purchases') }}"
           class="btn btn-outline">Cancelar</a>
    </div>
</form>

@push('head')
<script>
function invoiceForm(initialLines) {
    return {
        lines: initialLines.length > 0 ? initialLines.map(l => ({
            article_id:  l.article_id  ?? '',
            description: l.description ?? '',
            quantity:    parseFloat(l.quantity)   || 1,
            unit_price:  parseFloat(l.unit_price) || 0,
            vat_rate:    parseFloat(l.vat_rate)   || 21,
            subtotal:    parseFloat(l.subtotal)   || 0,
        })) : [ this.newLine() ],

        totals: { net: 0, vat: 0, total: 0 },

        newLine() {
            return { article_id: '', description: '', quantity: 1, unit_price: 0, vat_rate: 21, subtotal: 0 };
        },
        addLine()        { this.lines.push(this.newLine()); },
        removeLine(i)    { if (this.lines.length > 1) this.lines.splice(i, 1); this.calcTotals(); },

        calcLine(i) {
            const l = this.lines[i];
            l.subtotal = Math.round(l.quantity * l.unit_price * 100) / 100;
            this.calcTotals();
        },

        calcTotals() {
            let net = 0, vat = 0;
            this.lines.forEach(l => {
                net += l.subtotal;
                vat += l.subtotal * (l.vat_rate / 100);
            });
            this.totals.net   = Math.round(net * 100) / 100;
            this.totals.vat   = Math.round(vat * 100) / 100;
            this.totals.total = Math.round((net + vat) * 100) / 100;
        },

        fillFromArticle(i, event) {
            const opt = event.target.selectedOptions[0];
            if (!opt || !opt.value) return;
            this.lines[i].description = opt.dataset.desc  || '';
            this.lines[i].unit_price  = parseFloat(opt.dataset.price) || 0;
            this.calcLine(i);
        },

        formatMoney(v) {
            return '$ ' + Number(v || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        init() { this.calcTotals(); }
    };
}
</script>
@endpush
@endsection
