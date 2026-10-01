<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class OrderPlaceResult extends Struct
{
    public function __construct(public string $orderId)
    {
    }
}
