@extends('layouts.app')
@section('title', $supplier->exists ? 'Editar Proveedor' : 'Nuevo Proveedor')

@section('content')
<div class="card" style="max-width:680px">
    <form method="POST"
        action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
        @csrf
        @if($supplier->exists) @method('PUT') @endif

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Código *</label>
                <input class="input" name="code" value="{{ old('code', $supplier->code) }}" required maxlength="20">
                @error('code')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Razón Social *</label>
                <input class="input" name="name" value="{{ old('name', $supplier->name) }}" required>
                @error('name')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">CUIT / DNI</label>
                <input class="input" name="fiscal_id" value="{{ old('fiscal_id', $supplier->fiscal_id) }}" maxlength="20">
            </div>
            <div class="form-group">
                <label class="form-label">Tipo</label>
                <select class="select" name="fiscal_type">
                    <option value="">— Seleccionar —</option>
                    @foreach(['DNI','CUIT'] as $t)
                        <option value="{{ $t }}" @selected(old('fiscal_type', $supplier->fiscal_type) === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Domicilio</label>
                <input class="input" name="address" value="{{ old('address', $supplier->address) }}">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input class="input" type="email" name="email" value="{{ old('email', $supplier->email) }}">
            </div>
            <div class="form-group">
                <label class="form-label">Teléfono</label>
                <input class="input" name="phone" value="{{ old('phone', $supplier->phone) }}" maxlength="30">
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:4px">
            <button type="submit" class="btn btn-primary">
                {{ $supplier->exists ? 'Guardar Cambios' : 'Crear Proveedor' }}
            </button>
            <a href="{{ route('suppliers.index') }}" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
