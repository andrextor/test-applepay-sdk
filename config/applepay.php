<?php

/**
 * Only the settings that have no default in the SDK, plus the one switch worth flipping while
 * testing. Everything else — expirationTime, timeout, connectTimeout — is already defaulted by
 * Settings::fromArray(), and restating it here would only hide what the SDK actually does. Add a
 * key back the day you need to diverge from that default. The Apple Root CA is not here at all:
 * the SDK always downloads it from a fixed URL and it is not configurable.
 *
 * The SDK takes the three PEMs as content strings, never as file paths. Here that content is read
 * from APPLEPAY_CERTS_DIR — by default storage/app/private/apple-pay-certs, gitignored — holding
 * the test credentials for merchant.com.placetopay.checkout-test; the SDK only ever sees the
 * strings. Pasting the PEM blocks inline as heredocs instead of the file_get_contents() calls is
 * equally valid. Do not point this at ~/Downloads, ~/Desktop or ~/Documents: macOS privacy
 * protection (TCC) denies the web server's PHP access to those folders with "Operation not
 * permitted", and chmod cannot fix that.
 */
$certsDir = env('APPLEPAY_CERTS_DIR', storage_path('app/private/apple-pay-certs'));
$pem = static fn (string $file): string => is_file("$certsDir/$file") ? (string) file_get_contents("$certsDir/$file") : '';

return [
    'merchant_id' => env('APPLE_PAY_MERCHANT_ID'),
    'private_key' => env('APPLE_PAY_PRIVATE_KEY'),
    'merchant_certificate' => env('APPLE_PAY_MERCHANT_CERTIFICATE'),
    'merchant_certificate_key' => env('APPLE_PAY_MERCHANT_CERTIFICATE_KEY'),

    // Domain registered under that Merchant ID in the Apple portal (Merchant Domains). validateMerchant()
    // must send exactly this value, and Apple only honours it once the domain is verified.
    'domain_name' => 'checkout-test.placetopay.com',

    // Merchant name Apple shows in the payment sheet. Not the application name: renaming the app
    // must not change what the cardholder sees.
    'display_name' => 'Placetopay',

    // Payment Processing Certificate private key (EC P-256). Decrypts the token.

    // Merchant Identity Certificate (RSA 2048) and its key. Only validateMerchant() needs them, for mTLS.


    'http_logger' => (bool) env('APPLEPAY_HTTP_LOGGER', false),
];
