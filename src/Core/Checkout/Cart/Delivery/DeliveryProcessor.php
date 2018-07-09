<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\CheckoutContext;

class DeliveryProcessor
{
    public function process(Cart $cart, CheckoutContext $context): DeliveryCollection
    {
        return new DeliveryCollection();
    }
}