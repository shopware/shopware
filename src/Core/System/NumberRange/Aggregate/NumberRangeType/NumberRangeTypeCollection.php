<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                       add(NumberRangeTypeEntity $type)
 * @method void                       set(string $key, NumberRangeTypeEntity $entity)
 * @method NumberRangeTypeEntity[]    getIterator()
 * @method NumberRangeTypeEntity[]    getElements()
 * @method NumberRangeTypeEntity|null get(string $key)
 * @method NumberRangeTypeEntity|null first()
 * @method NumberRangeTypeEntity|null last()
 */
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
