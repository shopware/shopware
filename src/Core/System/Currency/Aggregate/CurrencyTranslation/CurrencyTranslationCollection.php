<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;

class CurrencyTranslationCollection extends EntityCollection
{
    /**
     * @var CurrencyTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CurrencyTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): CurrencyTranslationStruct
    {
        return parent::current();
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (CurrencyTranslationStruct $currencyTranslation) {
            return $currencyTranslation->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(function (CurrencyTranslationStruct $currencyTranslation) use ($id) {
            return $currencyTranslation->getCurrencyId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CurrencyTranslationStruct $currencyTranslation) {
            return $currencyTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CurrencyTranslationStruct $currencyTranslation) use ($id) {
            return $currencyTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CurrencyTranslationStruct::class;
    }
}
