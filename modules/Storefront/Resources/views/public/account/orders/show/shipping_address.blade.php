<div class="col-lg-6 col-sm-9">
    <div class="order-shipping-details">
        <h4>{{ trans('storefront::account.view_order.shipping_address') }}</h4>

        <address>
            <span>{{ $order->shipping_full_name }}</span>
            <span>{{ $order->shipping_address_1 }}</span>

            @if ($order->shipping_address_2)
                <span>{{ $order->shipping_address_2 }}</span>
            @endif


        </address>
    </div>
</div>
