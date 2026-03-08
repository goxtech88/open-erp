<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse — Open.ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css">
    <style>
        .auth-body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--color-bg);
            padding: 20px;
        }
        .auth-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 8px 40px rgba(0,0,0,.5);
        }
        .auth-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 32px;
            justify-content: center;
        }
        .auth-logo svg { width: 36px; height: 36px; color: var(--color-accent); }
        .auth-logo span { font-size: 22px; font-weight: 700; color: var(--color-accent); }
        .auth-title {
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 24px;
            color: var(--color-text);
        }
        .auth-btn {
            width: 100%;
            padding: 12px;
            background: var(--color-accent);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background var(--transition);
            margin-top: 8px;
        }
        .auth-btn:hover { background: var(--color-accent-h); }
        .auth-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--color-muted);
        }
        .auth-footer a { color: var(--color-accent); text-decoration: underline; }
    </style>
</head>
<body class="auth-body" style="display:flex;">
    <div class="auth-card">
        <div class="auth-logo">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
            </svg>
            <span>Open.ERP</span>
        </div>

        <h2 class="auth-title">Crear cuenta</h2>

        @if($errors->any())
            <div class="alert alert-error" style="margin:0 0 16px;">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="name">Nombre</label>
                <input class="input" type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Contraseña</label>
                <input class="input" type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirmation">Confirmar Contraseña</label>
                <input class="input" type="password" id="password_confirmation" name="password_confirmation" required>
            </div>

            <button type="submit" class="auth-btn">Registrarme</button>
        </form>

        <div class="auth-footer">
            ¿Ya tenés cuenta? <a href="{{ route('login') }}">Iniciar sesión</a>
        </div>
    </div>
</body>
</html>
