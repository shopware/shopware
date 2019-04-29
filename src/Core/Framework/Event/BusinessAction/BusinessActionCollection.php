<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\BusinessAction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                      add(BusinessActionEntity $entity)
 * @method void                      set(string $key, BusinessActionEntity $entity)
 * @method BusinessActionEntity[]    getIterator()
 * @method BusinessActionEntity[]    getElements()
 * @method BusinessActionEntity|null get(string $key)
 * @method BusinessActionEntity|null first()
 * @method BusinessActionEntity|null last()
 */
class BusinessActionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BusinessActionEntity::class;
    }
}
