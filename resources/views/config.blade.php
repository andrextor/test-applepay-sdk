@extends('layouts.app')

@section('title', 'Configuración efectiva')
@section('subtitle', 'Lo que Settings resolvió a partir de config/applepay.php. Ni la llave privada ni el PEM del certificado se imprimen: solo lo que hace falta para diagnosticar.')

@section('content')
    @if ($error)
        <div class="alert alert-error"><strong>InvalidSettingsException</strong><br>{{ $error }}</div>
    @else
        <div class="card">
            <h2>Settings</h2>
            <table>
                @foreach ($settings as $key => $value)
                    <tr><th>{{ $key }}</th><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        </div>

        <div class="card">
            <h2>Llave privada</h2>
            <p class="muted">El <code>publicKeyHash</code> es el valor que el SDK compara contra
                <code>header.publicKeyHash</code> del token. Si no coincide, el token es de otro comercio.</p>
            <table>
                @foreach ($privateKey as $key => $value)
                    <tr><th>{{ $key }}</th><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        </div>

        <div class="card">
            <h2>Apple Root CA</h2>
            <p class="muted">Resuelto por la cascada: rootCertificate → caché PSR-16 → descarga. Con el caché
                activo, recargar esta página no vuelve a salir a la red.</p>
            <table>
                @foreach ($rootCertificate as $key => $value)
                    <tr><th>{{ $key }}</th><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        </div>
    @endif
@endsection
