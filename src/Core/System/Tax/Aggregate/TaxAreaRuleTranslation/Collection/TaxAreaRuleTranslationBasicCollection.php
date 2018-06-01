<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\Collection;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\Struct\TaxAreaRuleTranslationBasicStruct;

class TaxAreaRuleTranslationBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\Struct\TaxAreaRuleTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? TaxAreaRuleTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): TaxAreaRuleTranslationBasicStruct
    {
        return parent::current();
    }

    public function getTaxAreaRuleIds(): array
    {
        return $this->fmap(function (TaxAreaRuleTranslationBasicStruct $taxAreaRuleTranslation) {
            return $taxAreaRuleTranslation->getTaxAreaRuleId();
        });
    }

    public function filterByTaxAreaRuleId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleTranslationBasicStruct $taxAreaRuleTranslation) use ($id) {
            return $taxAreaRuleTranslation->getTaxAreaRuleId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (TaxAreaRuleTranslationBasicStruct $taxAreaRuleTranslation) {
            return $taxAreaRuleTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleTranslationBasicStruct $taxAreaRuleTranslation) use ($id) {
            return $taxAreaRuleTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return TaxAreaRuleTranslationBasicStruct::class;
    }
}
