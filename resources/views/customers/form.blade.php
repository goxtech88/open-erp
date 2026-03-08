@extends('layouts.app')
@section('title', $customer->exists ? 'Editar Cliente' : 'Nuevo Cliente')

@section('content')
<div class="card" style="max-width:680px">
    <form method="POST"
        action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
        @csrf
        @if($customer->exists) @method('PUT') @endif

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Código *</label>
                <input class="input" name="code" value="{{ old('code', $customer->code) }}" required maxlength="20">
                @error('code')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Razón Social / Nombre *</label>
                <input class="input" name="name" value="{{ old('name', $customer->name) }}" required>
                @error('name')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">ID Fiscal (DNI / CUIT)</label>
                <input class="input" name="fiscal_id" value="{{ old('fiscal_id', $customer->fiscal_id) }}" maxlength="20">
                @error('fiscal_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Tipo de ID Fiscal</label>
                <select class="select" name="fiscal_type">
                    <option value="">— Seleccionar —</option>
                    @foreach(['DNI','CUIT','PASSPORT'] as $t)
                        <option value="{{ $t }}" @selected(old('fiscal_type', $customer->fiscal_type) === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Domicilio</label>
                <input class="input" name="address" value="{{ old('address', $customer->address) }}">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input class="input" type="email" name="email" value="{{ old('email', $customer->email) }}">
                @error('email')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Teléfono</label>
                <input class="input" name="phone" value="{{ old('phone', $customer->phone) }}" maxlength="30">
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
@endsection
