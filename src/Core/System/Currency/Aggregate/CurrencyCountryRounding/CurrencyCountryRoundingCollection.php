<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(CurrencyCountryRoundingEntity $entity)
 * @method void                               set(string $key, CurrencyCountryRoundingEntity $entity)
 * @method CurrencyCountryRoundingEntity[]    getIterator()
 * @method CurrencyCountryRoundingEntity[]    getElements()
 * @method CurrencyCountryRoundingEntity|null get(string $key)
 * @method CurrencyCountryRoundingEntity|null first()
 * @method CurrencyCountryRoundingEntity|null last()
 */
class CurrencyCountryRoundingCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'currency_country_rounding_collection';
    }

    protected function getExpectedClass(): string
    {
        return CurrencyCountryRoundingEntity::class;
    }
}
