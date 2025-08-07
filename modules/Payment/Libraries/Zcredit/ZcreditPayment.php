<?php

namespace Modules\Payment\Libraries\Zcredit;

use Illuminate\Support\Facades\Http;

class ZcreditPayment
{
    protected array $config;
    public function __construct(array $config){
        $this->config = $config;
    }

    function createZCreditSession(array $payloadOverrides = []): array
    {
        $basePayload = [
            "Key" => $this->config['key'],
            "Local" => "He",
            "UniqueId" => "2b0d7b3dcb3b",
            "SuccessUrl" => "",
            "CancelUrl" => "",
            "CallbackUrl" => "",
            "FailureCallBackUrl" => "",
            "FailureRedirectUrl" => "",
            "NumberOfFailures" => 5,
            "PaymentType" => "regular",
            "CreateInvoice" => "false",
            "AdditionalText" => "",
            "ShowCart" => "true",
            "ThemeColor" => "005ebb",
            "BitButtonEnabled" => "true",
            "ApplePayButtonEnabled" => "true",
            "GooglePayButtonEnabled" => "true",
            "Installments" => [
                "Type" => "regular",
                "MinQuantity" => "1",
                "MaxQuantity" => "12",
            ],
            "Customer" => [
                "Email" => "someone@gmail.com",
                "Name" => "Demo Client",
                "PhoneNumber" => "077-3233190",
                "Attributes" => [
                    "HolderId" => "none",
                    "Name" => "required",
                    "PhoneNumber" => "required",
                    "Email" => "optional",
                ],
            ],
            "CartItems" => [
                [
                    "Amount" => "10.20",
                    "Currency" => "ILS",
                    "Name" => "My Item1 Name",
                    "Description" => "My Item description , comes below the name",
                    "Quantity" => 2,
                    "Image" => "https://www.z-credit.com/site/wp-content/themes/z-credit/img/decisions/decision2.png",
                    "IsTaxFree" => "false",
                    "AdjustAmount" => "false",
                ],
                [
                    "Amount" => "2",
                    "Currency" => "ILS",
                    "Name" => "My Item2 Name",
                    "Description" => "My Item description , comes below the name",
                    "Quantity" => 1,
                    "Image" => "",
                    "IsTaxFree" => "false",
                    "AdjustAmount" => "false",
                ],
            ],
            "FocusType" => "None",
            "CardsIcons" => [
                "ShowVisaIcon" => "true",
                "ShowMastercardIcon" => "true",
                "ShowDinersIcon" => "true",
                "ShowAmericanExpressIcon" => "true",
                "ShowIsracardIcon" => "true",
            ],
            "IssuerWhiteList" => [1, 2, 3, 4, 5, 6],
            "BrandWhiteList" => [1, 2, 3, 4, 5, 6],
            "UseLightMode" => "false",
            "UseCustomCSS" => "false",
            "BackgroundColor" => "FFFFFF",
            "ShowTotalSumInPayButton" => "true",
            "ForceCaptcha" => "false",
            "CustomCSS" => "",
            "Bypass3DS" => "false",
        ];

        // Merge with any dynamic overrides
        $payload = array_replace_recursive($basePayload, $payloadOverrides);

        // Send the request
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://pci.zcredit.co.il/webcheckout/api/WebCheckout/CreateSession', $payload);

        // Return as array (or throw error if needed)
        return $response->json();
    }

}