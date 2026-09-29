<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderPriceModificationEntity>
 */
#[Package('checkout')]
class OrderPriceModificationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_price_modification_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderPriceModificationEntity::class;
    }
}
