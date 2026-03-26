<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Tracking;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<SalesChannelTrackingCustomerEntity>
 */
#[Package('discovery')]
class SalesChannelTrackingCustomerCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SalesChannelTrackingCustomerEntity::class;
    }
}
