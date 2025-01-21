<?php

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Framework\Struct\Struct;

class OrderPlaceResult extends Struct
{
    public function __construct(public string $orderId, public ?Struct $preOrderPayment = null)
    {
    }
}
