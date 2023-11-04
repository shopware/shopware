<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionSalesChannelEntity>
 */
#[Package('checkout')]
class PromotionSalesChannelCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_sales_channel_collection';
    }

    protected function getExpectedClass(): string
    {
        return PromotionSalesChannelEntity::class;
    }
}
