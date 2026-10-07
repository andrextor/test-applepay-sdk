<?php

namespace App\Http\Controllers;

use App\Support\ApplePayConfig;
use GuzzleHttp\ClientInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Placetopay\ApplepaySdk\ApplePay;
use Placetopay\ApplepaySdk\Cases\ApplePayClientMock;
use Placetopay\ApplepaySdk\Cases\ApplePayTokenGeneratorMock;
use Placetopay\ApplepaySdk\Exceptions\ApplepaySdkException;

class MockTokenController extends Controller
{
    public const array SCENARIOS = [
        'valid' => 'Válido — verifica y descifra',
        'tampered' => 'Manipulado — el payload cambió después de firmarse',
        'signature' => 'Firma inválida — la firma ECDSA está corrupta',
        'expired' => 'Expirado — token real de Apple firmado en 2024',
    ];

    public function show(): View
    {
        return view('mock-token', [
            'scenarios' => self::SCENARIOS,
            'brandToken' => session('brandToken'),
            'generatedToken' => session('generatedToken'),
            'scenario' => old('scenario', 'valid'),
            'merchantId' => old('merchantId', config('applepay.merchant_id')),
        ]);
    }

    public function decrypt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scenario' => ['required', 'string', 'in:'.implode(',', array_keys(self::SCENARIOS))],
            'merchantId' => ['required', 'string'],
            'overwrites' => ['nullable', 'string', 'json'],
        ]);

        $request->flash();

        $merchantId = $data['merchantId'];
        $privateKey = (string) config('applepay.payment_processing_private_key');
        $overwrites = json_decode($data['overwrites'] ?? '', true) ?: [];

        $token = match ($data['scenario']) {
            'valid' => ApplePayTokenGeneratorMock::makeValid($merchantId, $privateKey, $overwrites),
            'tampered' => ApplePayTokenGeneratorMock::makeTampered($merchantId, $privateKey, $overwrites),
            'signature' => ApplePayTokenGeneratorMock::makeWithInvalidSignature($merchantId, $privateKey, $overwrites),
            'expired' => ApplePayTokenGeneratorMock::makeExpired(),
        };

        try {
            $applePay = new ApplePay(ApplePayConfig::make($merchantId, [
                'httpClient' => self::clientServing($data['scenario']),
                // No cache with a synthetic chain: the generator builds a new one every PHP
                // process, so a root cached by one request rejects the next request's token.
                'cache' => null,
            ]));

            $brandToken = $applePay->decrypt(json_decode($token, true));

            return redirect()->route('mock.show')->with([
                'success' => true,
                'generatedToken' => $token,
                'brandToken' => [
                    'token' => $brandToken->token(),
                    'expiration' => $brandToken->expiration(),
                    'franchise' => $brandToken->franchise(),
                    'additional' => $brandToken->additional(),
                ],
            ]);
        } catch (ApplepaySdkException $exception) {
            return redirect()->route('mock.show')
                ->with('generatedToken', $token)
                ->with('error', $exception->getMessage())
                ->with('exception', class_basename($exception));
        }
    }

    /**
     * The root CA is never configured: the SDK always downloads it. So the way to make it trust the
     * generator's synthetic chain is to answer that download with the synthetic root. `makeExpired()`
     * is the exception — it returns a real token captured from Apple, so it needs Apple's real root
     * to reach the signing-time check the scenario is about, which is the mock's default response.
     */
    private static function clientServing(string $scenario): ClientInterface
    {
        return $scenario === 'expired'
            ? ApplePayClientMock::rootCertificate()
            : ApplePayClientMock::syntheticRootCertificate();
    }
}
