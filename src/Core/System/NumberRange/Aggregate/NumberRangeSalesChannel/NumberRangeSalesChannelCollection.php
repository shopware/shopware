<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NumberRangeSalesChannelEntity>
 */
class NumberRangeSalesChannelCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'number_range_sales_channel_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeSalesChannelEntity::class;
    }
}
