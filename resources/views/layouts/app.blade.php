<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Apple Pay SDK')</title>
    <style>
        :root { color-scheme: light; }
        body { font-family: -apple-system, Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .container { max-width: 980px; margin: 32px auto 64px; padding: 0 20px; }
        h1 { margin: 0 0 8px; font-size: 24px; }
        h2 { margin: 0 0 12px; font-size: 18px; }
        nav { margin: 0 0 24px; font-size: 14px; }
        nav a { color: #2563eb; text-decoration: none; margin-right: 16px; }
        nav a.active { font-weight: 700; color: #0f172a; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 2px rgba(15,23,42,.06); margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 600; margin: 12px 0 4px; }
        textarea, input, select { width: 100%; box-sizing: border-box; font-family: ui-monospace, Menlo, monospace; font-size: 13px; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
        textarea { min-height: 120px; }
        button { margin-top: 16px; background: #0f172a; color: #fff; border: 0; border-radius: 8px; padding: 10px 18px; font-size: 14px; font-weight: 600; cursor: pointer; }
        pre { background: #0f172a; color: #e2e8f0; padding: 14px; border-radius: 8px; overflow-x: auto; font-size: 12px; margin: 0; }
        .alert { border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 14px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alert-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .muted { color: #64748b; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th { width: 240px; color: #475569; font-weight: 600; }
        td { font-family: ui-monospace, Menlo, monospace; word-break: break-all; }
    </style>
</head>
<body>
<div class="container">
    <nav>
        <a href="{{ route('real.show') }}" class="{{ request()->routeIs('real.*') ? 'active' : '' }}">Token real</a>
        <a href="{{ route('mock.show') }}" class="{{ request()->routeIs('mock.*') ? 'active' : '' }}">Token sintético</a>
        <a href="{{ route('merchant.show') }}" class="{{ request()->routeIs('merchant.*') ? 'active' : '' }}">Validación de comerciante</a>
        <a href="{{ route('button.show') }}" class="{{ request()->routeIs('button.*') ? 'active' : '' }}">Botón</a>
        <a href="{{ route('config.show') }}" class="{{ request()->routeIs('config.*') ? 'active' : '' }}">Configuración</a>
    </nav>

    <h1>@yield('title')</h1>
    <p class="muted">@yield('subtitle')</p>

    @if (session('error'))
        <div class="alert alert-error"><strong>{{ session('exception') ?: 'Error' }}</strong><br>{{ session('error') }}</div>
    @endif

    @if (session('success'))
        <div class="alert alert-ok">Correcto.</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $message) {{ $message }}<br> @endforeach
        </div>
    @endif

    @yield('content')
</div>
</body>
</html>
