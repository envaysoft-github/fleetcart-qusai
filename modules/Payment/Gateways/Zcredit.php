<?php

namespace Modules\Payment\Gateways;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Modules\Order\Entities\Order;
use Modules\Payment\GatewayInterface;
use Modules\Payment\Libraries\Zcredit\ZcreditPayment;
use Modules\Payment\Responses\StripeResponse;
use Modules\Payment\Responses\ZcreditResponse;
use Stripe\Coupon;
use Stripe\Exception\ApiErrorException;
use Stripe\TaxRate;

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
        $supported_currencies = ['USD', 'EUR', 'NIS', 'ILS'];

        if (!in_array(currency(), $supported_currencies)) {
            throw new Exception(trans('payment::messages.currency_not_supported'));
        }
        $totalLineAmount = $order->products->sum(function ($product) {
            return $product->line_total->amount();
        });
        $totalLineAmount = number_format($totalLineAmount, 2, '.', '');

        $shippingCost = $order->shipping_cost->amount();
        $totalDiscount = $order->discount->amount();

        $distributedDiscount = 0;
        $distributedShipping = 0;

        $lastIndex = $order->products->count() - 1;

        $items = $this->buildGatewayItems($order, false);

        /*$items = $order->products->map(function ($orderProduct, $index) use (
            $totalLineAmount,
            $totalDiscount,
            $shippingCost,
            &$distributedDiscount,
            &$distributedShipping,
            $lastIndex
        ) {
            $lineAmount = $orderProduct->line_total->amount();

            // --- Discount calculation ---
            if ($index === $lastIndex) {
                $discountAmount = $totalDiscount - $distributedDiscount;
            } else {
                $proportion = $lineAmount / $totalLineAmount;
                $discountAmount = round($totalDiscount * $proportion, 2);
                $distributedDiscount += $discountAmount;
            }

            // --- Shipping cost distribution ---
            if ($index === $lastIndex) {
                $shippingAmount = $shippingCost - $distributedShipping;
            } else {
                $proportion = $lineAmount / $totalLineAmount;
                $shippingAmount = round($shippingCost * $proportion, 2);
                $distributedShipping += $shippingAmount;
            }

            // --- Final amount after discount & shipping ---
            $finalAmount = $lineAmount - $discountAmount + $shippingAmount;

            return [
                "Amount" => number_format($finalAmount, 2, '.', ''),
                "Currency" => currency(),
                "Name" => $orderProduct->product->name ?? 'N/A',
                "Description" => $orderProduct->product->sku ?? '',
                "Quantity" => (int) $orderProduct->qty,
                "Image" => ($orderProduct->product->variant && $orderProduct->product->variant->base_image->id)
                    ? $orderProduct->product->variant->base_image?->path
                    : $orderProduct->product->base_image?->path ?? asset('build/assets/image-placeholder.png'),
                "IsTaxFree" => "false",
                "AdjustAmount" => "false",
            ];
        });*/

        /*        $items[] = [
                    "Amount" => number_format(-20, 2, '.', ''), // negative number
                    "Currency" => currency(),
                    "Name" => "Discount",
                    "Description" => "Promotional Discount",
                    "Quantity" => 1,
                    "Image" => asset('build/assets/discount.png'),
                    "IsTaxFree" => "true",
                    "AdjustAmount" => "false",
                ];*/


        $installment = [
            "Type" => setting('zcredit_installment_type', 'none'),
            "MinQuantity" => setting('zcredit_installment_min', 1),
            "MaxQuantity" => setting('zcredit_installment_max', 12),
        ];


        $config = [
            'key' => setting('zcredit_key'),
        ];

        $zcredit = new ZcreditPayment($config);

        $customer = [
            "Email" => $order->customer_email,
            "Name" => implode(" ", [$order->customer_first_name, $order->customer_last_name]),
            "PhoneNumber" => $order->customer_phone,
            "Attributes" => [
                "HolderId" => setting('zcredit_holderid', 'none'),
                "Name" => setting('zcredit_holder_name', 'none'),
                "PhoneNumber" => setting('zcredit_holder_phone', 'none'),
                "Email" => setting('zcredit_holder_email', 'none'),
            ],
        ];

        $payloads = [
            'Local' => ucfirst(locale()),
            'UniqueId' => implode('-', [$order->id, Carbon::now()->timestamp]),
            'SuccessUrl' => $this->getRedirectUrl($order),
            'CancelUrl' => $this->getPaymentFailedUrl($order),
            'CallbackUrl' => 'https://webhook.site/a0e5e69e-52e6-46fe-af34-f5970282a82d',
            'FailureCallBackUrl' => 'https://webhook.site/a0e5e69e-52e6-46fe-af34-f5970282a82d',
            'FailureRedirectUrl' => $this->getPaymentFailedUrl($order),
            'Customer' => $customer,
            'CartItems' => $items,
            'CreateInvoice' => false,
            'Installments' => $installment,
        ];

        $response = $zcredit->createZCreditSession($payloads);
        return new ZcreditResponse($order, $response);

    }

    function buildGatewayItems($order, $use_unit_amounts = true)
    {
        $products = $order->products; // collection of order products
        // collect line cents
        $lines = [];
        $totalLineCents = 0;
        foreach ($products as $p) {
            $lineCents = (int) round($p->line_total->amount() * 100); // price * qty in cents
            $lines[] = ['p' => $p, 'line_cents' => $lineCents];
            $totalLineCents += $lineCents;
        }

        $discountCents = (int) round($order->discount->amount() * 100); // total discount in cents
        // distribute discount proportionally in cents (floor for all except last)
        $distributed = 0;
        $items = [];
        foreach ($lines as $idx => $entry) {
            $p = $entry['p'];
            $lineCents = $entry['line_cents'];

            if ($totalLineCents > 0) {
                if ($idx < count($lines) - 1) {
                    $share = (int) floor($lineCents * $discountCents / $totalLineCents);
                    $distributed += $share;
                } else {
                    // last gets the remainder to ensure sums match
                    $share = $discountCents - $distributed;
                }
            } else {
                $share = 0;
            }

            $finalLineCents = $lineCents - $share; // final cents for this product (includes qty)

            // Create payload entries depending on strategy:
            if ($use_unit_amounts) {
                // split into qty x (Quantity=1) items so cents divide exactly
                $qty = (int) $p->qty;
                if ($qty <= 0) {
                    $qty = 1;
                }
                $baseUnit = intdiv($finalLineCents, $qty);     // cents per unit (floor)
                $remainder = $finalLineCents - ($baseUnit * $qty); // leftover cents

                // create $qty entries; first $remainder units get +1 cent
                for ($u = 0; $u < $qty; $u++) {
                    $unitCents = $baseUnit + ($u < $remainder ? 1 : 0);
                    $items[] = [
                        "Amount" => number_format($unitCents / 100, 2, '.', ''), // unit price
                        "Currency" => currency(),
                        "Name" => $p->product->name ?? 'N/A',
                        "Description" => $p->product->sku ?? '',
                        "Quantity" => 1,
                        "Image" => $p->product->variant && $p->product->variant->base_image->id
                            ? $p->product->variant->base_image?->path
                            : $p->product->base_image?->path ?? asset('build/assets/image-placeholder.png'),
                        "IsTaxFree" => "false",
                        "AdjustAmount" => "false",
                    ];
                }
            } else {
                // send the line total as Amount and set Quantity = 1
                $items[] = [
                    "Amount" => number_format($finalLineCents / 100, 2, '.', ''), // line total
                    "Currency" => currency(),
                    "Name" => $p->product->name ?? 'N/A',
                    "Description" => $p->product->sku ?? '',
                    "Quantity" => 1,
                    "Image" => $p->product->variant && $p->product->variant->base_image->id
                        ? $p->product->variant->base_image?->path
                        : $p->product->base_image?->path ?? asset('build/assets/image-placeholder.png'),
                    "IsTaxFree" => "false",
                    "AdjustAmount" => "false",
                ];
            }
        }
        return $items;
    }

    private function getRedirectUrl($order)
    {
        return route('checkout.complete.store',
            ['orderId' => $order->id, 'paymentMethod' => 'zcredit', 'reference' => uniqid('stripe_')]);
    }

    private function getPaymentFailedUrl($order)
    {
        return route('checkout.payment_canceled.store', ['orderId' => $order->id, 'paymentMethod' => 'zcredit']);
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
}
