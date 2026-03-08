@extends('layouts.app')
@section('title', 'Proveedores')
@section('topbar-actions')
    <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuevo Proveedor
    </a>
@endsection

@section('content')
<div class="card">
    <form class="toolbar" method="GET" action="{{ route('suppliers.index') }}">
        <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Buscar proveedor…">
        </div>
        <button type="submit" class="btn btn-outline">Buscar</button>
        @if(request('q'))<a href="{{ route('suppliers.index') }}" class="btn btn-outline">Limpiar</a>@endif
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Código</th><th>Nombre</th><th>CUIT</th><th>Email</th><th>Teléfono</th><th></th></tr></thead>
            <tbody>
                @forelse($suppliers as $s)
                <tr>
                    <td><code style="color:var(--color-accent)">{{ $s->code }}</code></td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->fiscal_id ?: '—' }}</td>
                    <td>{{ $s->email ?: '—' }}</td>
                    <td>{{ $s->phone ?: '—' }}</td>
                    <td class="td-actions">
                        <a href="{{ route('suppliers.edit', $s) }}" class="btn btn-outline btn-sm">Editar</a>
                        <form method="POST" action="{{ route('suppliers.destroy', $s) }}" onsubmit="return confirm('¿Dar de baja?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Baja</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:var(--color-muted);padding:32px">Sin resultados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $suppliers->links() }}
</div>
@endsection
