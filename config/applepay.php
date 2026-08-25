<?php

return [
    'merchant_id' => env('APPLEPAY_MERCHANT_ID', ''),
    'private_key' => str_replace('\n', "\n", (string) env('APPLEPAY_PRIVATE_KEY', '')),
    'root_certificate' => str_replace('\n', "\n", (string) env('APPLEPAY_ROOT_CERTIFICATE', '')),
    'root_certificate_url' => env('APPLEPAY_ROOT_CERTIFICATE_URL', 'https://www.apple.com/certificateauthority/AppleRootCA-G3.cer'),
    'expiration_time' => (int) env('APPLEPAY_EXPIRATION_TIME', 300),
    'timeout' => (int) env('APPLEPAY_TIMEOUT', 10),
    'connect_timeout' => (int) env('APPLEPAY_CONNECT_TIMEOUT', 5),
    'cert_path' => env('APPLEPAY_CERT_PATH'),
    'cert_key_path' => env('APPLEPAY_CERT_KEY_PATH'),
    'cert_key_password' => env('APPLEPAY_CERT_KEY_PASSWORD'),
    'http_logger' => (bool) env('APPLEPAY_HTTP_LOGGER', false),
];
