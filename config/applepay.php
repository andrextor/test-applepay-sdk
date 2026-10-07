<?php

/**
 * Only the settings that have no default in the SDK, plus the one switch worth flipping while
 * testing. Everything else — expirationTime, timeout, connectTimeout — is already defaulted by
 * Settings::fromArray(). The Apple Root CA is not here at all: the SDK always downloads it.
 *
 * Same env names as redirection (config/services.php, apple_pay), so its .env block can be pasted
 * here as is. The three values are PEM contents; ApplePayConfig writes the merchant identity pair
 * to files because the SDK takes those two as paths (Guzzle cert/ssl_key).
 */
return [
    'merchant_id' => env('APPLE_PAY_MERCHANT_ID'),

    // Payment Processing Certificate private key (EC P-256). Decrypts the token.
    'payment_processing_private_key' => env('APPLE_PAY_PAYMENT_PROCESSING_PRIVATE_KEY'),

    // Merchant Identity Certificate (RSA 2048) and its key. Only validateMerchant() uses them, for mTLS.
    'merchant_identity_cert' => env('APPLE_PAY_MERCHANT_IDENTITY_CERT'),
    'merchant_identity_private_key' => env('APPLE_PAY_MERCHANT_IDENTITY_PRIVATE_KEY'),

    // Domain registered under that Merchant ID in the Apple portal (Merchant Domains). validateMerchant()
    // must send exactly this value, and Apple only honours it once the domain is verified.
    'domain_name' => 'checkout-test.placetopay.com',

    // Merchant name Apple shows in the payment sheet. Not the application name: renaming the app
    // must not change what the cardholder sees.
    'display_name' => 'Placetopay',

    'http_logger' => (bool) env('APPLEPAY_HTTP_LOGGER', false),
];
