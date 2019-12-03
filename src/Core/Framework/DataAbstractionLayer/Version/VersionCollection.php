<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void               add(VersionEntity $entity)
 * @method void               set(string $key, VersionEntity $entity)
 * @method VersionEntity[]    getIterator()
 * @method VersionEntity[]    getElements()
 * @method VersionEntity|null get(string $key)
 * @method VersionEntity|null first()
 * @method VersionEntity|null last()
 */
class VersionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return VersionEntity::class;
    }
}
