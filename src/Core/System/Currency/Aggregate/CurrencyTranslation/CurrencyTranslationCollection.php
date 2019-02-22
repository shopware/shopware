<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                           add(CurrencyTranslationEntity $entity)
 * @method void                           set(string $key, CurrencyTranslationEntity $entity)
 * @method CurrencyTranslationEntity[]    getIterator()
 * @method CurrencyTranslationEntity[]    getElements()
 * @method CurrencyTranslationEntity|null get(string $key)
 * @method CurrencyTranslationEntity|null first()
 * @method CurrencyTranslationEntity|null last()
 */
class CurrencyTranslationCollection extends EntityCollection
{
    public function getCurrencyIds(): array
    {
        return $this->fmap(function (CurrencyTranslationEntity $currencyTranslation) {
            return $currencyTranslation->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(function (CurrencyTranslationEntity $currencyTranslation) use ($id) {
            return $currencyTranslation->getCurrencyId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CurrencyTranslationEntity $currencyTranslation) {
            return $currencyTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CurrencyTranslationEntity $currencyTranslation) use ($id) {
            return $currencyTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CurrencyTranslationEntity::class;
    }
}
