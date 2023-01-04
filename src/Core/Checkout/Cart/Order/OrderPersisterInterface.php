<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
interface OrderPersisterInterface
{
    public function persist(Cart $cart, SalesChannelContext $context): string;
}
