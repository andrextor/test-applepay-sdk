<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ApplePayConfig
{
    /**
     * Builds the settings array the SDK expects, from config/applepay.php.
     *
     * `logger` and `cache` are resolved here rather than from the environment: the SDK wants
     * live PSR-3 and PSR-16 instances, and Laravel's cache repository already is a PSR-16
     * CacheInterface. Passing it matters — without a cache the SDK re-downloads the Apple
     * Root CA on every new ApplePay instance.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function make(string $merchantId, array $overrides = []): array
    {
        $settings = [
            'merchantId' => $merchantId,
            'privateKey' => (string) config('applepay.private_key'),
            'rootCertificate' => (string) config('applepay.root_certificate'),
            'rootCertificateUrl' => (string) config('applepay.root_certificate_url'),
            'expirationTime' => (int) config('applepay.expiration_time'),
            'timeout' => (int) config('applepay.timeout'),
            'connectTimeout' => (int) config('applepay.connect_timeout'),
            'certPath' => config('applepay.cert_path'),
            'certKeyPath' => config('applepay.cert_key_path'),
            'certKeyPassword' => config('applepay.cert_key_password'),
            'httpLogger' => ['enabled' => (bool) config('applepay.http_logger')],
            'logger' => logger(),
            'cache' => Cache::store(),
        ];

        return array_merge(array_filter($settings, static fn ($value): bool => $value !== null && $value !== ''), $overrides);
    }
}
