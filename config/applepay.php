<?php

/**
 * Only the settings that have no default in the SDK, plus the two switches worth flipping while
 * testing. Everything else — expirationTime, timeout, connectTimeout — is already defaulted by
 * Settings::fromArray(), and restating it here would only hide what the SDK actually does. Add a
 * key back the day you need to diverge from that default. The Apple Root CA is not here at all:
 * the SDK always downloads it from a fixed URL and it is not configurable.
 */
return [
    'merchant_id' => env('APPLEPAY_MERCHANT_ID', ''),
    'private_key' => str_replace('\n', "\n", (string) env('APPLEPAY_PRIVATE_KEY', '')),

    // Merchant Identity Certificate (mTLS). Only validateMerchant() needs it.
    'cert_path' => env('APPLEPAY_CERT_PATH'),
    'cert_key_path' => env('APPLEPAY_CERT_KEY_PATH'),

    'http_logger' => (bool) env('APPLEPAY_HTTP_LOGGER', false),
];
