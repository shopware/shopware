<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Tax\Struct\TaxAreaRuleTranslationBasicStruct;

class TaxAreaRuleTranslationBasicCollection extends EntityCollection
{
    /**
     * @var TaxAreaRuleTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? TaxAreaRuleTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): TaxAreaRuleTranslationBasicStruct
    {
        return parent::current();
    }

    public function getTaxAreaRuleUuids(): array
    {
        return $this->fmap(function (TaxAreaRuleTranslationBasicStruct $taxAreaRuleTranslation) {
            return $taxAreaRuleTranslation->getTaxAreaRuleUuid();
        });
    }

    public function filterByTaxAreaRuleUuid(string $uuid): self
    {
        return $this->filter(function (TaxAreaRuleTranslationBasicStruct $taxAreaRuleTranslation) use ($uuid) {
            return $taxAreaRuleTranslation->getTaxAreaRuleUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (TaxAreaRuleTranslationBasicStruct $taxAreaRuleTranslation) {
            return $taxAreaRuleTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): self
    {
        return $this->filter(function (TaxAreaRuleTranslationBasicStruct $taxAreaRuleTranslation) use ($uuid) {
            return $taxAreaRuleTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return TaxAreaRuleTranslationBasicStruct::class;
    }
}
