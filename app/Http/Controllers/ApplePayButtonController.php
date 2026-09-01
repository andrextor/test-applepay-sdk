<?php

namespace App\Http\Controllers;

use App\Support\ApplePayConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Placetopay\ApplepaySdk\ApplePay;
use Placetopay\ApplepaySdk\DTOs\MerchantValidationRequest;
use Placetopay\ApplepaySdk\Exceptions\ApplepaySdkException;

class ApplePayButtonController extends Controller
{
    private const string DOMAIN_ASSOCIATION_PATH = 'public/.well-known/apple-developer-merchantid-domain-association.txt';

    public function show(Request $request): View
    {
        return view('apple-pay-button', [
            'merchantId' => (string) config('applepay.merchant_id'),
            'domainName' => $request->getHost(),
            'displayName' => config('applepay.display_name'),
            'checks' => $this->prerequisites($request),
        ]);
    }

    /**
     * Called by ApplePaySession.onvalidatemerchant. Apple hands the page a one-shot validation URL;
     * the server POSTs to it with the Merchant Identity Certificate and returns the session
     * verbatim, which the page passes to completeMerchantValidation().
     */
    public function validateMerchant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'validationURL' => ['required', 'url'],
        ]);

        try {
            $applePay = new ApplePay(ApplePayConfig::make((string) config('applepay.merchant_id')));

            $session = $applePay->validateMerchant(MerchantValidationRequest::fromArray([
                'validationUrl' => $data['validationURL'],
                'domainName' => $request->getHost(),
                'displayName' => (string) config('applepay.display_name'),
            ]));

            return response()->json($session->toArray());
        } catch (ApplepaySdkException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'exception' => class_basename($exception),
            ], 422);
        }
    }

    /**
     * Called by ApplePaySession.onpaymentauthorized with the real token the device produced. This
     * is the whole point of the screen: everything before it exists to get a genuine token here.
     */
    public function process(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'array'],
        ]);

        try {
            $applePay = new ApplePay(ApplePayConfig::make((string) config('applepay.merchant_id')));

            $brandToken = $applePay->decrypt($data['token']);

            return response()->json([
                'token' => $brandToken->token(),
                'expiration' => $brandToken->expiration(),
                'franchise' => $brandToken->franchise(),
                'additional' => $brandToken->additional(),
            ]);
        } catch (ApplepaySdkException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'exception' => class_basename($exception),
            ], 422);
        }
    }

    /**
     * @return array<int, array{label: string, ok: bool, detail: string}>
     */
    private function prerequisites(Request $request): array
    {
        $host = $request->getHost();
        $isPublicHost = ! str_ends_with($host, '.test') && ! str_ends_with($host, '.localhost') && $host !== 'localhost';

        return [
            [
                'label' => 'merchantId configurado',
                'ok' => config('applepay.merchant_id') !== '',
                'detail' => 'merchant_id en config/applepay.php',
            ],
            [
                'label' => 'Payment Processing private key configurada',
                'ok' => config('applepay.private_key') !== '',
                'detail' => 'private_key en config/applepay.php — descifra el token',
            ],
            [
                'label' => 'Merchant Identity Certificate configurado',
                'ok' => (bool) config('applepay.merchant_certificate') && (bool) config('applepay.merchant_certificate_key'),
                'detail' => 'merchant_certificate y merchant_certificate_key en config/applepay.php — sin esto falla onvalidatemerchant',
            ],
            [
                'label' => 'Dominio público',
                'ok' => $isPublicHost,
                'detail' => $isPublicHost
                    ? $host
                    : $host.' — Apple no puede verificar un dominio local; necesitas un host público con certificado TLS de confianza',
            ],
            [
                'label' => 'Archivo de verificación de dominio publicado',
                'ok' => file_exists(base_path(self::DOMAIN_ASSOCIATION_PATH)),
                'detail' => 'Descárgalo del portal de Apple y colócalo en '.self::DOMAIN_ASSOCIATION_PATH,
            ],
        ];
    }
}
