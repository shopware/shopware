<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(SalutationEntity $entity)
 * @method void                  set(string $key, SalutationEntity $entity)
 * @method SalutationEntity[]    getIterator()
 * @method SalutationEntity[]    getElements()
 * @method SalutationEntity|null get(string $key)
 * @method SalutationEntity|null first()
 * @method SalutationEntity|null last()
 */
class SalutationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SalutationEntity::class;
    }
}
