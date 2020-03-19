<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                        add(NumberRangeStateEntity $entity)
 * @method NumberRangeStateEntity[]    getIterator()
 * @method NumberRangeStateEntity[]    getElements()
 * @method NumberRangeStateEntity|null get(string $key)
 * @method NumberRangeStateEntity|null first()
 * @method NumberRangeStateEntity|null last()
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
