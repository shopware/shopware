<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NumberRangeEntity>
 */
class NumberRangeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'number_range_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeEntity::class;
    }
}
