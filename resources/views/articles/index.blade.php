@extends('layouts.app')
@section('title', 'Artículos')
@section('topbar-actions')
    <a href="{{ route('articles.create') }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuevo Artículo
    </a>
@endsection

@section('content')
<div class="card">
    <form class="toolbar" method="GET" action="{{ route('articles.index') }}">
        <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Código o descripción…">
        </div>
        <select class="select" name="category_id" style="width:180px">
            <option value="">Todos los rubros</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-outline">Filtrar</button>
        @if(request('q') || request('category_id'))
            <a href="{{ route('articles.index') }}" class="btn btn-outline">Limpiar</a>
        @endif
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th><th>Descripción</th><th>Rubro</th>
                    <th style="text-align:right">P. Costo</th>
                    <th style="text-align:right">P. Venta</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($articles as $a)
                <tr>
                    <td><code style="color:var(--color-accent)">{{ $a->code }}</code></td>
                    <td>{{ $a->description }}</td>
                    <td>{{ $a->category?->name ?? '—' }}</td>
                    <td style="text-align:right">$ {{ number_format($a->cost_price, 2, ',', '.') }}</td>
                    <td style="text-align:right">$ {{ number_format($a->sale_price, 2, ',', '.') }}</td>
                    <td class="td-actions">
                        <a href="{{ route('articles.edit', $a) }}" class="btn btn-outline btn-sm">Editar</a>
                        <form method="POST" action="{{ route('articles.destroy', $a) }}" onsubmit="return confirm('¿Dar de baja?')">
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
    {{ $articles->links() }}
</div>
@endsection
