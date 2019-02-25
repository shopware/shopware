<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void            add(UnitEntity $entity)
 * @method void            set(string $key, UnitEntity $entity)
 * @method UnitEntity[]    getIterator()
 * @method UnitEntity[]    getElements()
 * @method UnitEntity|null get(string $key)
 * @method UnitEntity|null first()
 * @method UnitEntity|null last()
 */
class UnitCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return UnitEntity::class;
    }
}
