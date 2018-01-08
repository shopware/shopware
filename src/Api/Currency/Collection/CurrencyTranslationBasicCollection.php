<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Collection;

use Shopware\Api\Currency\Struct\CurrencyTranslationBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class CurrencyTranslationBasicCollection extends EntityCollection
{
    /**
     * @var CurrencyTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CurrencyTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CurrencyTranslationBasicStruct
    {
        return parent::current();
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (CurrencyTranslationBasicStruct $currencyTranslation) {
            return $currencyTranslation->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(function (CurrencyTranslationBasicStruct $currencyTranslation) use ($id) {
            return $currencyTranslation->getCurrencyId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CurrencyTranslationBasicStruct $currencyTranslation) {
            return $currencyTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CurrencyTranslationBasicStruct $currencyTranslation) use ($id) {
            return $currencyTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CurrencyTranslationBasicStruct::class;
    }
}
