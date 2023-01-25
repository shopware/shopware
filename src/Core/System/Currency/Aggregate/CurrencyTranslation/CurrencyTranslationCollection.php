<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CurrencyTranslationEntity>
 */
#[Package('inventory')]
class CurrencyTranslationCollection extends EntityCollection
{
    public function getCurrencyIds(): array
    {
        return $this->fmap(fn (CurrencyTranslationEntity $currencyTranslation) => $currencyTranslation->getCurrencyId());
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(fn (CurrencyTranslationEntity $currencyTranslation) => $currencyTranslation->getCurrencyId() === $id);
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(fn (CurrencyTranslationEntity $currencyTranslation) => $currencyTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (CurrencyTranslationEntity $currencyTranslation) => $currencyTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'currency_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CurrencyTranslationEntity::class;
    }
}
