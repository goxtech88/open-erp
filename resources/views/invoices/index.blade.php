@extends('layouts.app')
@section('title', $type === 'sale' ? 'Ventas' : 'Compras')

@section('topbar-actions')
    <a href="{{ $type === 'sale' ? route('invoices.sales.create') : route('invoices.purchases.create') }}"
       class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuevo Comprobante
    </a>
@endsection

@section('content')
<div class="card">
    {{-- Filters --}}
    <form class="toolbar" method="GET">
        <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="{{ $type === 'sale' ? 'Buscar cliente…' : 'Buscar proveedor…' }}">
        </div>
        <select class="select" name="status" style="width:140px">
            <option value="">— Estado —</option>
            <option value="draft"      @selected(request('status') === 'draft')>Borrador</option>
            <option value="authorized" @selected(request('status') === 'authorized')>Autorizado</option>
            <option value="cancelled"  @selected(request('status') === 'cancelled')>Anulado</option>
        </select>
        <input class="input" type="date" name="from" value="{{ request('from') }}" style="width:140px">
        <input class="input" type="date" name="to"   value="{{ request('to') }}"   style="width:140px">
        <button type="submit" class="btn btn-outline">Filtrar</button>
        <a href="{{ request()->url() }}" class="btn btn-outline">Limpiar</a>
    </form>

    {{-- Table --}}
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nro Comprobante</th>
                    <th>Fecha</th>
                    <th>{{ $type === 'sale' ? 'Cliente' : 'Proveedor' }}</th>
                    <th>Estado</th>
                    <th style="text-align:right">Neto</th>
                    <th style="text-align:right">IVA</th>
                    <th style="text-align:right">Total</th>
                    <th>CAE</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr>
                    <td><code style="color:var(--color-accent)">{{ $inv->formatted_number }}</code></td>
                    <td>{{ $inv->date->format('d/m/Y') }}</td>
                    <td>{{ $type === 'sale' ? $inv->customer?->name : $inv->supplier?->name }}</td>
                    <td>
                        <span class="badge badge-{{ $inv->status }}">
                            {{ match($inv->status) { 'draft'=>'Borrador','authorized'=>'Autorizado','cancelled'=>'Anulado',default=>$inv->status } }}
                        </span>
                    </td>
                    <td style="text-align:right">$ {{ number_format($inv->net,   2, ',', '.') }}</td>
                    <td style="text-align:right">$ {{ number_format($inv->vat,   2, ',', '.') }}</td>
                    <td style="text-align:right"><strong>$ {{ number_format($inv->total, 2, ',', '.') }}</strong></td>
                    <td>
                        @if($inv->cae)
                            <code style="font-size:11px;color:var(--color-success)">{{ $inv->cae }}</code>
                        @else
                            <span style="color:var(--color-muted)">—</span>
                        @endif
                    </td>
                    <td class="td-actions">
                        @if($inv->isDraft())
                            {{-- Editar --}}
                            <a href="{{ $type === 'sale'
                                ? route('invoices.sales.edit', $inv)
                                : route('invoices.purchases.edit', $inv) }}"
                               class="btn btn-outline btn-sm">Editar</a>
                            {{-- Autorizar AFIP --}}
                            <form method="POST"
                                action="{{ $type === 'sale'
                                    ? route('invoices.sales.authorize', $inv)
                                    : route('invoices.purchases.authorize', $inv) }}"
                                onsubmit="return confirm('¿Autorizar ante AFIP?')">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm">Autorizar AFIP</button>
                            </form>
                            {{-- Eliminar --}}
                            <form method="POST"
                                action="{{ $type === 'sale'
                                    ? route('invoices.sales.destroy', $inv)
                                    : route('invoices.purchases.destroy', $inv) }}"
                                onsubmit="return confirm('¿Eliminar borrador?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;color:var(--color-muted);padding:32px">Sin comprobantes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $invoices->links() }}
</div>
@endsection
