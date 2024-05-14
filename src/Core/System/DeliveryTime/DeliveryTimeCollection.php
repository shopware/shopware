<?php declare(strict_types=1);

namespace Shopware\Core\System\DeliveryTime;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DeliveryTimeEntity>
 */
#[Package('checkout')]
class DeliveryTimeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'delivery_time_collection';
    }

    protected function getExpectedClass(): string
    {
        return DeliveryTimeEntity::class;
    }
}
