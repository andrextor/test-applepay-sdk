<?php

use Illuminate\Support\Facades\Cache;
use Placetopay\ApplepaySdk\Cases\ApplePayTokenGeneratorMock;

beforeEach(function () {
    configureApplePayWithFreshKey();
});

it('reports which prerequisites are still missing', function () {
    $this->get(route('button.show'))
        ->assertOk()
        ->assertSee('merchantId configurado')
        ->assertSee('Merchant Identity Certificate configurado')
        ->assertSee('Apple no puede verificar un dominio local', escape: false);
});

it('refuses merchant validation without the merchant identity certificate', function () {
    config()->set('applepay.merchant_certificate', null);
    config()->set('applepay.merchant_certificate_key', null);

    $this->postJson(route('button.validate'), [
        'validationURL' => 'https://apple-pay-gateway.apple.com/paymentservices/paymentSession',
    ])
        ->assertStatus(422)
        ->assertJson(['exception' => 'InvalidSettingsException']);
});

it('decrypts the token the device would post', function () {
    // Seeding the SDK's cache key is what keeps this test off the network: the resolution cascade
    // reads the cache before downloading, so the synthetic chain's root is already trusted.
    Cache::store()->put('apple_pay_root_certificate', ApplePayTokenGeneratorMock::rootCertificate(), 60);

    $token = ApplePayTokenGeneratorMock::makeValid(
        'merchant.com.test.app',
        config('applepay.private_key'),
        ['applicationPrimaryAccountNumber' => '4111111111111111'],
    );

    $this->postJson(route('button.process'), ['token' => json_decode($token, true)])
        ->assertOk()
        ->assertJson([
            'token' => '4111111111111111',
            'additional' => ['walletID' => 'applePay'],
        ]);
});

it('reports a decryption failure back to the page', function () {
    Cache::store()->put('apple_pay_root_certificate', ApplePayTokenGeneratorMock::rootCertificate(), 60);

    $token = ApplePayTokenGeneratorMock::makeTampered('merchant.com.test.app', config('applepay.private_key'));

    $this->postJson(route('button.process'), ['token' => json_decode($token, true)])
        ->assertStatus(422)
        ->assertJson(['exception' => 'SignatureValidationException']);
});
