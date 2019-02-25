<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(IntegrationEntity $entity)
 * @method void                   set(string $key, IntegrationEntity $entity)
 * @method IntegrationEntity[]    getIterator()
 * @method IntegrationEntity[]    getElements()
 * @method IntegrationEntity|null get(string $key)
 * @method IntegrationEntity|null first()
 * @method IntegrationEntity|null last()
 */
class IntegrationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return IntegrationEntity::class;
    }
}
