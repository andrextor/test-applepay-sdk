<?php

beforeEach(function () {
    configureApplePayWithFreshKey();
});

it('opens a merchant session against the mocked Apple gateway', function () {
    $response = $this->post(route('merchant.validate'), [
        'mode' => 'mock',
        'merchantId' => 'merchant.com.test.app',
        'validationUrl' => 'https://apple-pay-gateway.apple.com/paymentservices/paymentSession',
        'domainName' => 'checkout.test.app',
        'displayName' => 'Test Store',
    ]);

    $response->assertRedirect(route('merchant.show'))->assertSessionHas('success', true);

    expect(session('merchantSession'))
        ->toMatchArray([
            'merchantIdentifier' => 'merchant.com.test.app',
            'domainName' => 'checkout.test.app',
            'displayName' => 'Test Store',
        ])
        ->and(session('merchantSession')['merchantSessionIdentifier'])->not->toBeEmpty();
});

it('explains that the merchant identity certificate is missing in real mode', function () {
    config()->set('applepay.merchant_identity_cert', null);
    config()->set('applepay.merchant_identity_private_key', null);

    $this->post(route('merchant.validate'), [
        'mode' => 'real',
        'merchantId' => 'merchant.com.test.app',
        'validationUrl' => 'https://apple-pay-gateway.apple.com/paymentservices/paymentSession',
        'domainName' => 'checkout.test.app',
        'displayName' => 'Test Store',
    ])
        ->assertSessionHas('exception', 'InvalidSettingsException')
        ->assertSessionMissing('success');
});
