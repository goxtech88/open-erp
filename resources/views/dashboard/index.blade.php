@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Ventas del Mes</div>
        <div class="stat-value stat-success">$ --</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Facturas Emitidas</div>
        <div class="stat-value stat-accent">--</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Compras del Mes</div>
        <div class="stat-value stat-warn">$ --</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Clientes</div>
        <div class="stat-value">--</div>
    </div>
</div>

<div class="card">
    <p style="color:var(--color-muted); font-size:13px;">
        Bienvenido al ERP. Seleccioná un módulo en el menú lateral para comenzar.
    </p>
</div>
@endsection
