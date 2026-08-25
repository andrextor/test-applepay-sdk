@extends('layouts.app')

@section('title', 'Token real de Apple Pay')
@section('subtitle', 'Descifra un token capturado de un dispositivo, con la configuración del .env y el Apple Root CA G3 descargado de Apple. Este es el camino de producción.')

@section('content')
    <form method="POST" action="{{ route('real.decrypt') }}" class="card">
        @csrf
        <label for="merchantId">merchantId</label>
        <input id="merchantId" name="merchantId" value="{{ $merchantId }}" placeholder="merchant.com.yourcompany.app">

        <label for="token">Token (JSON completo: data, header, signature, version)</label>
        <textarea id="token" name="token" placeholder='{"data":"...","header":{...},"signature":"...","version":"EC_v1"}'>{{ old('token') }}</textarea>

        <button type="submit">Descifrar</button>
    </form>

    @if ($brandToken)
        <div class="card">
            <h2>BrandToken</h2>
            <table>
                <tr><th>token() — PAN</th><td>{{ $brandToken['token'] }}</td></tr>
                <tr><th>expiration()</th><td>{{ $brandToken['expiration'] }}</td></tr>
                <tr><th>franchise()</th><td>{{ $brandToken['franchise'] ?? 'null' }}</td></tr>
                <tr><th>cvv()</th><td>{{ $brandToken['cvv'] ?? 'null' }}</td></tr>
            </table>

            <h2 style="margin-top:20px">additional()</h2>
            <pre>{{ json_encode($brandToken['additional'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif
@endsection
