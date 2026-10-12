<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final readonly class RestoredOrder
{
    public function __construct(
        public OrderEntity $order,
        public SalesChannelContext $context,
        public Cart $cart,
    ) {
    }
}
