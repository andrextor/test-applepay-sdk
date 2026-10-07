<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

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
            'paymentProcessingPrivateKey' => (string) config('applepay.payment_processing_private_key'),
            'merchantIdentityCertPath' => self::pemFile(config('applepay.merchant_identity_cert'), 'merchant_identity.pem'),
            'merchantIdentityPrivateKeyPath' => self::pemFile(config('applepay.merchant_identity_private_key'), 'merchant_identity.key'),
            'httpLogger' => ['enabled' => (bool) config('applepay.http_logger')],
            'logger' => logger(),
            'cache' => Cache::store(),
        ];

        return array_merge(array_filter($settings, static fn ($value): bool => $value !== null && $value !== ''), $overrides);
    }

    /**
     * Writes a PEM to storage and returns its path, the way redirection does with TemporaryFile:
     * the SDK takes the merchant identity pair as file paths, but the env holds their content.
     */
    public static function pemFile(?string $pem, string $name): ?string
    {
        if (! $pem) {
            return null;
        }

        $path = storage_path("app/private/apple-pay/$name");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $pem);

        return $path;
    }
}
