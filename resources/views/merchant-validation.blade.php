@extends('layouts.app')

@section('title', 'Validación de comerciante')
@section('subtitle', 'Abre una sesión de comerciante contra Apple con el Merchant Identity Certificate (mTLS), o contra ApplePayClientMock si no lo tienes a mano.')

@section('content')
    @unless ($hasCertificate)
        <div class="alert alert-warn">
            No hay <code>APPLEPAY_CERT_PATH</code> / <code>APPLEPAY_CERT_KEY_PATH</code> en el .env,
            así que el modo real fallará con <code>missingMerchantCertificate</code>. Ese certificado
            es el <strong>Merchant Identity Certificate</strong> (RSA), distinto del Payment
            Processing Certificate que usa el descifrado. El modo mock funciona sin él.
        </div>
    @endunless

    <form method="POST" action="{{ route('merchant.validate') }}" class="card">
        @csrf
        <label for="mode">Modo</label>
        <select id="mode" name="mode">
            <option value="mock" @selected(old('mode', 'mock') === 'mock')>Mock — ApplePayClientMock, sin red</option>
            <option value="real" @selected(old('mode') === 'real')>Real — POST a Apple con mTLS</option>
        </select>

        <label for="merchantId">merchantId</label>
        <input id="merchantId" name="merchantId" value="{{ $merchantId }}">

        <label for="validationUrl">validationUrl</label>
        <input id="validationUrl" name="validationUrl" value="{{ old('validationUrl', 'https://apple-pay-gateway.apple.com/paymentservices/paymentSession') }}">

        <label for="domainName">domainName</label>
        <input id="domainName" name="domainName" value="{{ old('domainName') }}" placeholder="checkout.yourcompany.com">

        <label for="displayName">displayName</label>
        <input id="displayName" name="displayName" value="{{ old('displayName') }}" placeholder="Your Company">

        <button type="submit">Validar</button>
    </form>

    @if ($session)
        <div class="card">
            <h2>MerchantSession</h2>
            <pre>{{ json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif
@endsection
