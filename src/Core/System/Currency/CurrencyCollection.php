<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                add(CurrencyEntity $entity)
 * @method void                set(string $key, CurrencyEntity $entity)
 * @method CurrencyEntity[]    getIterator()
 * @method CurrencyEntity[]    getElements()
 * @method CurrencyEntity|null get(string $key)
 * @method CurrencyEntity|null first()
 * @method CurrencyEntity|null last()
 */
class CurrencyCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'currency_collection';
    }

    protected function getExpectedClass(): string
    {
        return CurrencyEntity::class;
    }
}
