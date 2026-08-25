<?php

namespace App\Http\Controllers;

use App\Support\ApplePayConfig;
use Illuminate\View\View;
use Placetopay\ApplepaySdk\Entities\Settings;
use Placetopay\ApplepaySdk\Exceptions\ApplepaySdkException;
use Placetopay\ApplepaySdk\Helpers\KeyManager;
use Throwable;

class ConfigController extends Controller
{
    public function show(): View
    {
        $merchantId = (string) config('applepay.merchant_id');

        try {
            $settings = Settings::fromArray(ApplePayConfig::make($merchantId));
        } catch (ApplepaySdkException $exception) {
            return view('config', [
                'error' => $exception->getMessage(),
                'settings' => null,
                'privateKey' => null,
                'rootCertificate' => null,
            ]);
        }

        return view('config', [
            'error' => null,
            'settings' => [
                'merchantId' => $settings->merchantId,
                'expirationTime' => $settings->expirationTime.' s',
                'timeout' => $settings->timeout.' s',
                'connectTimeout' => $settings->connectTimeout.' s',
                'rootCertificateUrl' => Settings::DEFAULT_ROOT_CERTIFICATE_URL.' (fija, no configurable)',
                'cache' => $settings->cache ? $settings->cache::class : 'sin caché — descarga el CA en cada instancia',
                'logger' => $settings->logger ? $settings->logger::class : 'sin logger',
                'httpLogger' => ($settings->httpLogger['enabled'] ?? false) ? 'activo' : 'inactivo',
                'certPath' => $settings->certPath ?? 'no configurado — validateMerchant() fallará',
                'certKeyPath' => $settings->certKeyPath ?? 'no configurado',
                'httpClient' => $settings->httpClient::class,
            ],
            'privateKey' => $this->describePrivateKey($settings->privateKey),
            'rootCertificate' => $this->describeRootCertificate($settings),
        ]);
    }

    /**
     * Derives the public key exactly the way SignatureVerifier does, so the resulting hash is the
     * value a token's `header.publicKeyHash` has to match. Comparing it by eye tells you whether a
     * given token was encrypted for this merchant before you even try to decrypt it.
     *
     * @return array<string, string>
     */
    private function describePrivateKey(string $privateKey): array
    {
        try {
            $key = KeyManager::loadPrivateKey($privateKey);
            $details = openssl_pkey_get_details($key);

            return [
                'curva' => $this->curveNameOf($details),
                'bits' => (string) ($details['bits'] ?? '?'),
                'publicKeyHash (sha256, base64)' => base64_encode(
                    hash('sha256', KeyManager::subjectPublicKeyInfoDer($details['key']), true)
                ),
            ];
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>|false  $details
     */
    private function curveNameOf(array|false $details): string
    {
        return match (true) {
            $details === false => '?',
            isset($details['ec']['curve_name']) => (string) $details['ec']['curve_name'],
            default => 'no es EC',
        };
    }

    /**
     * @return array<string, string>
     */
    private function describeRootCertificate(Settings $settings): array
    {
        try {
            $pem = $settings->resolveRootCertificate();
            $parsed = openssl_x509_parse($pem);

            return [
                'subject' => $parsed['name'] ?? '?',
                'válido desde' => date('Y-m-d H:i:s', $parsed['validFrom_time_t'] ?? 0),
                'válido hasta' => date('Y-m-d H:i:s', $parsed['validTo_time_t'] ?? 0),
                'fingerprint (sha256)' => strtoupper(openssl_x509_fingerprint($pem, 'sha256') ?: '?'),
            ];
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }
}
