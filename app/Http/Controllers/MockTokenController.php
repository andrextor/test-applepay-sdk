<?php

namespace App\Http\Controllers;

use App\Support\ApplePayConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Placetopay\ApplepaySdk\ApplePay;
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
        $privateKey = (string) config('applepay.private_key');
        $overwrites = json_decode($data['overwrites'] ?? '', true) ?: [];

        $token = match ($data['scenario']) {
            'valid' => ApplePayTokenGeneratorMock::makeValid($merchantId, $privateKey, $overwrites),
            'tampered' => ApplePayTokenGeneratorMock::makeTampered($merchantId, $privateKey, $overwrites),
            'signature' => ApplePayTokenGeneratorMock::makeWithInvalidSignature($merchantId, $privateKey, $overwrites),
            'expired' => ApplePayTokenGeneratorMock::makeExpired(),
        };

        try {
            $applePay = new ApplePay(ApplePayConfig::make($merchantId, [
                'rootCertificate' => self::rootCertificateFor($data['scenario']),
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
     * The generator signs with a synthetic chain it builds in-process, so the SDK has to trust that
     * chain instead of Apple's real root — otherwise the token is rejected before decryption.
     * `makeExpired()` is the exception: it returns a real token captured from Apple, so it needs
     * Apple's real root to reach the signing-time check that the scenario is about.
     */
    private static function rootCertificateFor(string $scenario): string
    {
        return $scenario === 'expired'
            ? ApplePayTokenGeneratorMock::capturedRootCertificate()
            : ApplePayTokenGeneratorMock::rootCertificate();
    }
}
