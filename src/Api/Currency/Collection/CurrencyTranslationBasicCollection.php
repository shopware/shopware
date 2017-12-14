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

    public function get(string $uuid): ? CurrencyTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CurrencyTranslationBasicStruct
    {
        return parent::current();
    }

    public function getCurrencyUuids(): array
    {
        return $this->fmap(function (CurrencyTranslationBasicStruct $currencyTranslation) {
            return $currencyTranslation->getCurrencyUuid();
        });
    }

    public function filterByCurrencyUuid(string $uuid): CurrencyTranslationBasicCollection
    {
        return $this->filter(function (CurrencyTranslationBasicStruct $currencyTranslation) use ($uuid) {
            return $currencyTranslation->getCurrencyUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (CurrencyTranslationBasicStruct $currencyTranslation) {
            return $currencyTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): CurrencyTranslationBasicCollection
    {
        return $this->filter(function (CurrencyTranslationBasicStruct $currencyTranslation) use ($uuid) {
            return $currencyTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return CurrencyTranslationBasicStruct::class;
    }
}
