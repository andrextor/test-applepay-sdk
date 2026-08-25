<?php

namespace App\Http\Controllers;

use App\Support\ApplePayConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Placetopay\ApplepaySdk\ApplePay;
use Placetopay\ApplepaySdk\Cases\ApplePayClientMock;
use Placetopay\ApplepaySdk\DTOs\MerchantValidationRequest;
use Placetopay\ApplepaySdk\Entities\Settings;
use Placetopay\ApplepaySdk\Exceptions\ApplepaySdkException;

class MerchantValidationController extends Controller
{
    public function show(): View
    {
        return view('merchant-validation', [
            'session' => session('merchantSession'),
            'hasCertificate' => (bool) config('applepay.cert_path') && (bool) config('applepay.cert_key_path'),
            'merchantId' => old('merchantId', config('applepay.merchant_id')),
        ]);
    }

    public function validateMerchant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'merchantId' => ['required', 'string'],
            'validationUrl' => ['required', 'url'],
            'domainName' => ['required', 'string'],
            'displayName' => ['required', 'string'],
            'mode' => ['required', 'in:real,mock'],
        ]);

        $request->flash();

        try {
            $applePay = new ApplePay(ApplePayConfig::make($data['merchantId'], $this->overridesFor($data)));

            $session = $applePay->validateMerchant(MerchantValidationRequest::fromArray($data));

            return redirect()->route('merchant.show')->with([
                'success' => true,
                'merchantSession' => $session->toArray(),
            ]);
        } catch (ApplepaySdkException $exception) {
            return redirect()->route('merchant.show')
                ->with('error', $exception->getMessage())
                ->with('exception', class_basename($exception));
        }
    }

    /**
     * In mock mode the client is built through Settings rather than handing ApplePayClientMock's
     * ready-made client straight over: an injected httpClient bypasses buildHttpClient(), and with
     * it the configured timeouts and the HTTP log middleware — two of the things this app exists
     * to verify.
     *
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    private function overridesFor(array $data): array
    {
        if ($data['mode'] !== 'mock') {
            return [];
        }

        $settings = Settings::fromArray(ApplePayConfig::make($data['merchantId'], [
            'certPath' => 'mock', 'certKeyPath' => 'mock',
        ]));

        $handler = ApplePayClientMock::new()->pushMerchantSession([
            'merchantIdentifier' => $data['merchantId'],
            'domainName' => $data['domainName'],
            'displayName' => $data['displayName'],
        ]);

        return [
            'certPath' => 'mock',
            'certKeyPath' => 'mock',
            'httpClient' => $settings->buildHttpClient(['handler' => $handler]),
        ];
    }
}
