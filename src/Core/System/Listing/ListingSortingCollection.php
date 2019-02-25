<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                      add(ListingSortingEntity $entity)
 * @method void                      set(string $key, ListingSortingEntity $entity)
 * @method ListingSortingEntity[]    getIterator()
 * @method ListingSortingEntity[]    getElements()
 * @method ListingSortingEntity|null get(string $key)
 * @method ListingSortingEntity|null first()
 * @method ListingSortingEntity|null last()
 */
class ListingSortingCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ListingSortingEntity::class;
    }
}
