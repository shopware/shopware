<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderStateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderStateEntity::class;
    }
}
