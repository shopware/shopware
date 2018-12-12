<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CurrencyTranslationCollection extends EntityCollection
{
    /**
     * @var CurrencyTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? CurrencyTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): CurrencyTranslationEntity
    {
        return parent::current();
    }

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
