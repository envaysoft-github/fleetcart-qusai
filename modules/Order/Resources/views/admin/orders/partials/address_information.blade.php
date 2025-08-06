<div class="address-information-wrapper">
    <h4 class="section-title">{{ trans('order::orders.address_information') }}</h4>

    <div class="row">
        <div class="col-md-6">
            <div class="billing-address">
                <h5 class="pull-left">{{ trans('order::orders.billing_address') }}</h5>

                <span>
                    {{ $order->billing_full_name }}
                    <br>
                    {{ $order->billing_address_1 }}
                    <br>

                    @if ($order->billing_address_2)
                        {{ $order->billing_address_2 }}
                        <br>
                    @endif

                </span>
            </div>
        </div>

        <div class="col-md-6">
            <div class="shipping-address">
                <h5 class="pull-left">{{ trans('order::orders.shipping_address') }}</h5>

                <span>
                    {{ $order->shipping_full_name }}
                    <br>
                    {{ $order->shipping_address_1 }}
                    <br>

                    @if ($order->shipping_address_2)
                        {{ $order->shipping_address_2 }}
                        <br>
                    @endif

                </span>
            </div>
        </div>
    </div>
</div>
