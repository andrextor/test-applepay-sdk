@extends('layouts.app')

@section('title', 'Token sintético')
@section('subtitle', 'Genera un token firmado y cifrado en el momento contra la llave del .env, y lo pasa por el SDK. Sirve para probar la integración sin capturar un token de un dispositivo.')

@section('content')
    <div class="alert alert-warn">
        <strong>El cliente mockeado no es opcional aquí.</strong> El generador firma con una cadena
        sintética que arma en memoria, y el SDK siempre descarga su raíz por HTTP: no hay forma de
        configurársela. Así que la única vía es responder esa descarga con la raíz sintética. El
        escenario <em>expirado</em> es la excepción: es un token real de Apple y usa la raíz real,
        que es justo lo que el mock devuelve por defecto.
    </div>

    <form method="POST" action="{{ route('mock.decrypt') }}" class="card">
        @csrf
        <label for="scenario">Escenario</label>
        <select id="scenario" name="scenario">
            @foreach ($scenarios as $key => $label)
                <option value="{{ $key }}" @selected($scenario === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <label for="merchantId">merchantId</label>
        <input id="merchantId" name="merchantId" value="{{ $merchantId }}" placeholder="merchant.com.yourcompany.app">

        <label for="overwrites">Overwrites del payload (JSON, opcional)</label>
        <textarea id="overwrites" name="overwrites" style="min-height:80px" placeholder='{"applicationPrimaryAccountNumber": "4111111111111111", "paymentData.eciIndicator": "7"}'>{{ old('overwrites') }}</textarea>
        <p class="muted">Las claves con punto direccionan campos anidados. Ignorado en el escenario expirado.</p>

        <button type="submit">Generar y descifrar</button>
    </form>

    @if ($brandToken)
        <div class="card">
            <h2>BrandToken</h2>
            <table>
                <tr><th>token() — PAN</th><td>{{ $brandToken['token'] }}</td></tr>
                <tr><th>expiration()</th><td>{{ $brandToken['expiration'] }}</td></tr>
                <tr><th>franchise()</th><td>{{ $brandToken['franchise'] ?? 'null' }}</td></tr>
            </table>
            <h2 style="margin-top:20px">additional()</h2>
            <pre>{{ json_encode($brandToken['additional'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif

    @if ($generatedToken)
        <div class="card">
            <h2>Token generado</h2>
            <p class="muted">Esto es exactamente lo que se le pasó al SDK.</p>
            <pre>{{ json_encode(json_decode($generatedToken, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif
@endsection
