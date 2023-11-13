<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NumberRangeTypeEntity>
 */
#[Package('checkout')]
class NumberRangeTypeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'number_range_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeTypeEntity::class;
    }
}
