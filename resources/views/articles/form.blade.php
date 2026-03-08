@extends('layouts.app')
@section('title', $article->exists ? 'Editar Artículo' : 'Nuevo Artículo')

@section('content')
<div class="card" style="max-width:680px">
    <form method="POST"
        action="{{ $article->exists ? route('articles.update', $article) : route('articles.store') }}">
        @csrf
        @if($article->exists) @method('PUT') @endif

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Código *</label>
                <input class="input" name="code" value="{{ old('code', $article->code) }}" required maxlength="50">
                @error('code')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Descripción *</label>
                <input class="input" name="description" value="{{ old('description', $article->description) }}" required>
                @error('description')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Rubro</label>
                <select class="select" name="category_id">
                    <option value="">— Sin rubro —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id', $article->category_id) == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Precio de Costo *</label>
                <input class="input" type="number" step="0.01" name="cost_price"
                    value="{{ old('cost_price', $article->cost_price) }}" required min="0">
                @error('cost_price')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Precio de Venta *</label>
                <input class="input" type="number" step="0.01" name="sale_price"
                    value="{{ old('sale_price', $article->sale_price) }}" required min="0">
                @error('sale_price')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:4px">
            <button type="submit" class="btn btn-primary">
                {{ $article->exists ? 'Guardar Cambios' : 'Crear Artículo' }}
            </button>
            <a href="{{ route('articles.index') }}" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
