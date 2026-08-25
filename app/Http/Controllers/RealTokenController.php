<?php

namespace App\Http\Controllers;

use App\Support\ApplePayConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Placetopay\ApplepaySdk\ApplePay;
use Placetopay\ApplepaySdk\Exceptions\ApplepaySdkException;

class RealTokenController extends Controller
{
    public function show(): View
    {
        return view('real-token', [
            'brandToken' => session('brandToken'),
            'merchantId' => old('merchantId', config('applepay.merchant_id')),
        ]);
    }

    public function decrypt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'json'],
            'merchantId' => ['required', 'string'],
        ]);

        try {
            $applePay = new ApplePay(ApplePayConfig::make($data['merchantId']));

            $brandToken = $applePay->decrypt(json_decode($data['token'], true));

            return redirect()->route('real.show')->with([
                'success' => true,
                'brandToken' => [
                    'token' => $brandToken->token(),
                    'expiration' => $brandToken->expiration(),
                    'franchise' => $brandToken->franchise(),
                    'cvv' => $brandToken->cvv(),
                    'additional' => $brandToken->additional(),
                ],
            ]);
        } catch (ApplepaySdkException $exception) {
            $request->flash();

            return redirect()->route('real.show')
                ->with('error', $exception->getMessage())
                ->with('exception', class_basename($exception));
        }
    }
}
