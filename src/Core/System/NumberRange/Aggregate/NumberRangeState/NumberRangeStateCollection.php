<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NumberRangeStateEntity>
 */
class NumberRangeStateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'number_range_state_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeStateEntity::class;
    }
}
