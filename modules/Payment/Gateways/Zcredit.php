<?php

namespace Modules\Payment\Gateways;

use Carbon\Carbon;
use Exception;
use Modules\Payment\Libraries\Zcredit\ZcreditPayment;
use Modules\Payment\Responses\ZcreditResponse;
use Stripe\Coupon;
use Stripe\TaxRate;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Modules\Order\Entities\Order;
use Modules\Payment\GatewayInterface;
use Stripe\Exception\ApiErrorException;
use Modules\Payment\Responses\StripeResponse;
use Stripe\StripeClient;

class Zcredit implements GatewayInterface
{
    public $label;
    public $description;


    public function __construct()
    {
        $this->label = setting('zcredit_label');
        $this->description = setting('zcredit_description');
    }


    public function purchase(Order $order, Request $request): ZcreditResponse
    {
//        dd($order->products);
//        dd(currency());

            $items = $order->products->map(function ($orderProduct) {
//                dd($orderProduct->product->name);
                $amount = number_format($orderProduct->line_total->amount(), 2, '.', '');
                return [
                    "Amount" => $amount,
                    "Currency" => currency(),
                    "Name" => $orderProduct->product->name ?? 'N/A',
                    "Description" => $orderProduct->product->sku ?? '',
                    "Quantity" => (int) $orderProduct->qty,
                    "Image" => "",
                    "IsTaxFree" => "false",
                    "AdjustAmount" => "false",
                ];
            });





        $config = [
            'key' => setting('zcredit_key'),
        ];

      $zcredit = new ZcreditPayment($config);

      $customer = [
          "Email" => $order->customer_email,
          "Name" => implode(" ", [$order->customer_first_name, $order->customer_last_name]),
          "PhoneNumber" => $order->customer_phone,
          "Attributes" => [
              "HolderId" => "none",
              "Name" => "required",
              "PhoneNumber" => "required",
              "Email" => "optional",
          ],
      ];

//      dd(locale());

      $payloads = [
          'Local'=> ucfirst(locale()),
          'UniqueId'=> implode('-',[$order->id,Carbon::now()->timestamp]),
          'SuccessUrl'=> $this->getRedirectUrl($order),
          'CancelUrl'=> $this->getPaymentFailedUrl($order),
          'CallbackUrl'=> 'https://webhook.site/a0e5e69e-52e6-46fe-af34-f5970282a82d',
          'FailureCallBackUrl'=> 'https://webhook.site/a0e5e69e-52e6-46fe-af34-f5970282a82d',
          'FailureRedirectUrl'=> $this->getPaymentFailedUrl($order),
          'Customer'=> $customer,
          'CartItems'=>  $items,
          'CreateInvoice'=> false,
      ];

       $response = $zcredit->createZCreditSession($payloads);
//       dd($response );

        return new ZcreditResponse($order, $response);

    }


    private function getShippingOptions($order): array
    {
        if ($order->hasShippingMethod()) {
            return [
                'shipping_options' => [
                    [
                        'shipping_rate_data' => [
                            'display_name' => $order->shipping_method,
                            'type' => 'fixed_amount',
                            'fixed_amount' => [
                                'amount' => (int) ($order->shipping_cost->amount() * 100),
                                'currency' => currency(),
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [];
    }


    /**
     * @throws ApiErrorException
     */
    private function getDiscounts($order): array
    {
        if ($order->discount->amount() > 0) {
            $coupon = Coupon::create([
                'currency' => currency(),
                'amount_off' => (int) ($order->discount->amount() * 100),
            ]);

            return [
                'discounts' => [
                    [
                        'coupon' => $coupon->id,
                    ],
                ],
            ];
        }


        return [];
    }


    public function complete(Order $order): StripeResponse
    {
        return new StripeResponse($order, request());
    }


    /**
     * @throws ApiErrorException
     */
    public function prepareLineItems($order): array
    {
        $lineItems = [];

        foreach ($order->products as $orderProduct) {
            $lineItems[] = $this->prepareLineItem($orderProduct);
        }

        return $lineItems;
    }


    /**
     * @throws ApiErrorException
     */
    private function prepareLineItem($orderProduct): array
    {
        $item = [];
        $item['price_data'] = [
            'currency' => currency(),
            'unit_amount' => (int) ($orderProduct->unit_price->convertToCurrentCurrency()->amount() * 100),
            'product_data' => [
                'name' => $orderProduct->product->name,
                'images' => [
                    $orderProduct->product_variant?->base_image?->path
                        ?? $orderProduct->product?->base_image?->path
                        ?? asset('build/assets/image-placeholder.png')
                ],
            ],
        ];
        $item['quantity'] = $orderProduct->qty;

        $tax = $orderProduct->product->taxClass
            ->findTaxRate(
                request('billing'),
                request('shipping')
            );

        if ($tax) {
            $taxRate = TaxRate::create([
                'display_name' => 'Tax',
                'percentage' => $tax->rate,
                'inclusive' => false,
            ])->id;
            $item['tax_rates'] = [$taxRate];
        }

        return $item;
    }


    private function getRedirectUrl($order)
    {
        return route('checkout.complete.store', ['orderId' => $order->id, 'paymentMethod' => 'zcredit','reference' => uniqid('stripe_')]);
    }


    private function getPaymentFailedUrl($order)
    {
        return route('checkout.payment_canceled.store', ['orderId' => $order->id, 'paymentMethod' => 'zcredit']);
    }
}
