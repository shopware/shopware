<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(NumberRangeEntity $entity)
 * @method NumberRangeEntity[]    getIterator()
 * @method NumberRangeEntity[]    getElements()
 * @method NumberRangeEntity|null get(string $key)
 * @method NumberRangeEntity|null first()
 * @method NumberRangeEntity|null last()
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
