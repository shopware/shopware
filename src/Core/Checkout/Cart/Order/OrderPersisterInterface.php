<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;

interface OrderPersisterInterface
{
    public function persist(Cart $cart, CheckoutContext $context): EntityWrittenContainerEvent;
}
