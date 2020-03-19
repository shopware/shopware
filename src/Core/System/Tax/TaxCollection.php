<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void           add(TaxEntity $entity)
 * @method void           set(string $key, TaxEntity $entity)
 * @method TaxEntity[]    getIterator()
 * @method TaxEntity[]    getElements()
 * @method TaxEntity|null get(string $key)
 * @method TaxEntity|null first()
 * @method TaxEntity|null last()
 */
class TaxCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tax_collection';
    }

    protected function getExpectedClass(): string
    {
        return TaxEntity::class;
    }
}
