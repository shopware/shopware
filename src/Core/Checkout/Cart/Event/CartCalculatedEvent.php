<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartCalculatedEvent
{
    public function __construct(
        public readonly Cart $cart,
        public readonly SalesChannelContext $context
    ) {
    }
}
