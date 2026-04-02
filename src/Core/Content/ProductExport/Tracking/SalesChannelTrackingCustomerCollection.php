<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Tracking;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
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
