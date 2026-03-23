<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Tracking;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<SalesChannelTrackingOrderEntity>
 */
#[Package('discovery')]
class SalesChannelTrackingOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SalesChannelTrackingOrderEntity::class;
    }
}
