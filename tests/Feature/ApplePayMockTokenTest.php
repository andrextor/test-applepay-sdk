<?php

use App\Support\ApplePayConfig;
use Placetopay\ApplepaySdk\ApplePay;
use Placetopay\ApplepaySdk\Cases\ApplePayTokenGeneratorMock;
use Placetopay\ApplepaySdk\Exceptions\DecryptionException;
use Placetopay\ApplepaySdk\Exceptions\SignatureValidationException;

beforeEach(function () {
    configureApplePayWithFreshKey();
});

it('decrypts a synthetic token generated for the configured key', function () {
    $response = $this->post(route('mock.decrypt'), [
        'scenario' => 'valid',
        'merchantId' => 'merchant.com.test.app',
        'overwrites' => json_encode(['applicationPrimaryAccountNumber' => '4111111111111111']),
    ]);

    $response->assertRedirect(route('mock.show'))->assertSessionHas('success', true);

    $brandToken = session('brandToken');

    expect($brandToken['token'])->toBe('4111111111111111')
        ->and($brandToken['additional']['walletID'])->toBe('applePay')
        ->and($brandToken['additional']['cryptogram'])->not->toBeEmpty();
});

it('surfaces the SDK message for each failure scenario', function (string $scenario, string $message) {
    $this->post(route('mock.decrypt'), [
        'scenario' => $scenario,
        'merchantId' => 'merchant.com.test.app',
    ])->assertSessionHas('error', fn (string $error): bool => str_contains($error, $message));
})->with([
    'tampered' => ['tampered', 'Digest message does not match signed data'],
    'invalid signature' => ['signature', 'Invalid ECDSA signature'],
    'expired' => ['expired', 'Signing time is older than'],
]);

it('fails to decrypt when the merchantId differs from the one the token was minted for', function () {
    // The merchantId is the shared info of the ANSI X9.63 KDF, so a different one derives a
    // different AES key and GCM authentication fails. This is what proves the setting travels
    // all the way into the key agreement, not just into the constructor.
    $token = ApplePayTokenGeneratorMock::makeValid('merchant.com.test.app', config('applepay.private_key'));

    $applePay = new ApplePay(ApplePayConfig::make('merchant.com.other.app', [
        'rootCertificate' => ApplePayTokenGeneratorMock::rootCertificate(),
    ]));

    expect(fn () => $applePay->decrypt(json_decode($token, true)))
        ->toThrow(DecryptionException::class);
});

it('rejects a token encrypted for a different private key', function () {
    $token = ApplePayTokenGeneratorMock::makeValid('merchant.com.test.app', config('applepay.private_key'));

    configureApplePayWithFreshKey();

    $applePay = new ApplePay(ApplePayConfig::make('merchant.com.test.app', [
        'rootCertificate' => ApplePayTokenGeneratorMock::rootCertificate(),
    ]));

    expect(fn () => $applePay->decrypt(json_decode($token, true)))
        ->toThrow(SignatureValidationException::class, 'Token public key hash does not match');
});

it('reports the effective configuration', function () {
    $this->get(route('config.show'))
        ->assertOk()
        ->assertSee('prime256v1')
        ->assertSee('merchant.com.test.app');
});
