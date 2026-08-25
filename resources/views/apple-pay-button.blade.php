@extends('layouts.app')

@section('title', 'Botón de Apple Pay')
@section('subtitle', 'El flujo real de punta a punta: el dispositivo produce el token, el servidor abre la sesión de comerciante y descifra lo que llega.')

@section('content')
    <div class="card">
        <h2>Prerrequisitos</h2>
        <table>
            @foreach ($checks as $check)
                <tr>
                    <th>{{ $check['ok'] ? '✅' : '❌' }} {{ $check['label'] }}</th>
                    <td>{{ $check['detail'] }}</td>
                </tr>
            @endforeach
            <tr>
                <th id="js-check">⏳ Apple Pay disponible en este navegador</th>
                <td id="js-check-detail">Comprobando…</td>
            </tr>
        </table>
    </div>

    <div class="card">
        <h2>Pago</h2>
        <label for="amount">Importe</label>
        <input id="amount" value="1.00">

        <label for="currency">Moneda</label>
        <input id="currency" value="USD">

        <label for="country">País del comercio</label>
        <input id="country" value="US">

        <div id="apple-pay-button-container" style="margin-top:20px"></div>
        <p class="muted" id="status"></p>
    </div>

    <div class="card" id="result-card" style="display:none">
        <h2>BrandToken</h2>
        <pre id="result"></pre>
    </div>

    <style>
        /* Native Safari appearance — no CDN script, no web component to register. */
        .apple-pay-button {
            -webkit-appearance: -apple-pay-button;
            -apple-pay-button-type: buy;
            -apple-pay-button-style: black;
            width: 220px;
            height: 44px;
            border-radius: 8px;
            cursor: pointer;
        }
    </style>

    <script>
        const MERCHANT_ID = @json($merchantId);
        const DISPLAY_NAME = @json($displayName);
        const CSRF = @json(csrf_token());

        const status = document.getElementById('status');
        const check = document.getElementById('js-check');
        const checkDetail = document.getElementById('js-check-detail');

        function post(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
                body: JSON.stringify(body),
            }).then(async (response) => {
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error((payload.exception ? payload.exception + ': ' : '') + (payload.error ?? response.status));
                }
                return payload;
            });
        }

        if (!window.ApplePaySession) {
            check.textContent = '❌ Apple Pay disponible en este navegador';
            checkDetail.textContent = 'ApplePaySession no existe. Apple Pay en web solo funciona en Safari, en macOS o iOS.';
        } else if (!ApplePaySession.canMakePayments()) {
            check.textContent = '❌ Apple Pay disponible en este navegador';
            checkDetail.textContent = 'Safari lo soporta pero el dispositivo no tiene ninguna tarjeta aprovisionada en Wallet.';
        } else {
            check.textContent = '✅ Apple Pay disponible en este navegador';
            checkDetail.textContent = 'ApplePaySession v3';
            renderButton();
        }

        function renderButton() {
            const button = document.createElement('button');
            button.className = 'apple-pay-button';
            button.addEventListener('click', startSession);
            document.getElementById('apple-pay-button-container').appendChild(button);
        }

        function startSession() {
            status.textContent = '';
            document.getElementById('result-card').style.display = 'none';

            const session = new ApplePaySession(3, {
                countryCode: document.getElementById('country').value,
                currencyCode: document.getElementById('currency').value,
                supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
                merchantCapabilities: ['supports3DS'],
                total: {label: DISPLAY_NAME, amount: document.getElementById('amount').value},
            });

            session.onvalidatemerchant = (event) => {
                status.textContent = 'Validando el comercio con Apple…';
                post('{{ route('button.validate') }}', {validationURL: event.validationURL})
                    .then((merchantSession) => session.completeMerchantValidation(merchantSession))
                    .catch((error) => {
                        session.abort();
                        status.textContent = 'Falló la validación de comerciante — ' + error.message;
                    });
            };

            session.onpaymentauthorized = (event) => {
                status.textContent = 'Descifrando el token…';
                post('{{ route('button.process') }}', {token: event.payment.token.paymentData})
                    .then((brandToken) => {
                        session.completePayment(ApplePaySession.STATUS_SUCCESS);
                        status.textContent = 'Token descifrado.';
                        document.getElementById('result').textContent = JSON.stringify(brandToken, null, 2);
                        document.getElementById('result-card').style.display = '';
                    })
                    .catch((error) => {
                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                        status.textContent = 'Falló el descifrado — ' + error.message;
                    });
            };

            session.oncancel = () => { status.textContent = 'Cancelado por el usuario.'; };

            session.begin();
        }
    </script>
@endsection
