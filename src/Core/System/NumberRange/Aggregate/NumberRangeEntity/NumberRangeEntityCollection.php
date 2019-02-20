<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(NumberRangeEntityEntity $entity)
 * @method NumberRangeEntityEntity[]    getIterator()
 * @method NumberRangeEntityEntity[]    getElements()
 * @method NumberRangeEntityEntity|null get(string $key)
 * @method NumberRangeEntityEntity|null first()
 * @method NumberRangeEntityEntity|null last()
 */
class NumberRangeEntityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NumberRangeEntityEntity::class;
    }
}
