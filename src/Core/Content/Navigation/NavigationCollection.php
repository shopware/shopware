<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(NavigationEntity $entity)
 * @method void                  set(string $key, NavigationEntity $entity)
 * @method NavigationEntity[]    getIterator()
 * @method NavigationEntity[]    getElements()
 * @method NavigationEntity|null get(string $key)
 * @method NavigationEntity|null first()
 * @method NavigationEntity|null last()
 */
class NavigationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NavigationEntity::class;
    }
}
