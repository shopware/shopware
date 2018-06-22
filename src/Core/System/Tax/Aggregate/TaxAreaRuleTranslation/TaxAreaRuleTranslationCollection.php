<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;

class TaxAreaRuleTranslationCollection extends EntityCollection
{
    /**
     * @var TaxAreaRuleTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? TaxAreaRuleTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): TaxAreaRuleTranslationStruct
    {
        return parent::current();
    }

    public function getTaxAreaRuleIds(): array
    {
        return $this->fmap(function (TaxAreaRuleTranslationStruct $taxAreaRuleTranslation) {
            return $taxAreaRuleTranslation->getTaxAreaRuleId();
        });
    }

    public function filterByTaxAreaRuleId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleTranslationStruct $taxAreaRuleTranslation) use ($id) {
            return $taxAreaRuleTranslation->getTaxAreaRuleId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (TaxAreaRuleTranslationStruct $taxAreaRuleTranslation) {
            return $taxAreaRuleTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleTranslationStruct $taxAreaRuleTranslation) use ($id) {
            return $taxAreaRuleTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return TaxAreaRuleTranslationStruct::class;
    }
}
