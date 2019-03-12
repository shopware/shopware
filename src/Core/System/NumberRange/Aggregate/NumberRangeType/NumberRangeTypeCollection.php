<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                       add(NumberRangeTypeEntity $type)
 * @method NumberRangeTypeEntity[]    getIterator()
 * @method NumberRangeTypeEntity[]    getElements()
 * @method NumberRangeTypeEntity|null get(string $key)
 * @method NumberRangeTypeEntity|null first()
 * @method NumberRangeTypeEntity|null last()
 */
class NumberRangeTypeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NumberRangeTypeEntity::class;
    }
}
