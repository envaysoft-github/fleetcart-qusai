<?php

namespace Modules\Payment\Responses;

use Modules\Order\Entities\Order;
use Modules\Payment\GatewayResponse;
use Modules\Payment\HasTransactionReference;

class ZcreditResponse extends GatewayResponse implements HasTransactionReference
{
    private $order;
    private $clientResponse;


    public function __construct(Order $order, array|object $clientResponse)
    {
//        dd($clientResponse);
//        dd($order->id);
        $this->order = $order;
        $this->clientResponse = $clientResponse;
    }


    public function getOrderId()
    {
//        dd($this->order->id);
        return $this->order->id;
    }


    public function getTransactionReference()
    {
//        dd($this->clientResponse);
        return $this->clientResponse->query('reference');
    }


    public function toArray()
    {

        $array['redirectUrl'] = $this->clientResponse['Data']['SessionUrl'];


        return $array;
    }
}
