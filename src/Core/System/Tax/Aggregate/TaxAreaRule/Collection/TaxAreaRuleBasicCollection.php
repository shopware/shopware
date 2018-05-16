<?php declare(strict_types=1);

namespace Shopware\System\Tax\Aggregate\TaxAreaRule\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Tax\Aggregate\TaxAreaRule\Struct\TaxAreaRuleBasicStruct;

class TaxAreaRuleBasicCollection extends EntityCollection
{
    /**
     * @var TaxAreaRuleBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? TaxAreaRuleBasicStruct
    {
        return parent::get($id);
    }

    public function current(): TaxAreaRuleBasicStruct
    {
        return parent::current();
    }

    public function getCountryAreaIds(): array
    {
        return $this->fmap(function (TaxAreaRuleBasicStruct $taxAreaRule) {
            return $taxAreaRule->getCountryAreaId();
        });
    }

    public function filterByCountryAreaId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleBasicStruct $taxAreaRule) use ($id) {
            return $taxAreaRule->getCountryAreaId() === $id;
        });
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (TaxAreaRuleBasicStruct $taxAreaRule) {
            return $taxAreaRule->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleBasicStruct $taxAreaRule) use ($id) {
            return $taxAreaRule->getCountryId() === $id;
        });
    }

    public function getCountryStateIds(): array
    {
        return $this->fmap(function (TaxAreaRuleBasicStruct $taxAreaRule) {
            return $taxAreaRule->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleBasicStruct $taxAreaRule) use ($id) {
            return $taxAreaRule->getCountryStateId() === $id;
        });
    }

    public function getTaxIds(): array
    {
        return $this->fmap(function (TaxAreaRuleBasicStruct $taxAreaRule) {
            return $taxAreaRule->getTaxId();
        });
    }

    public function filterByTaxId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleBasicStruct $taxAreaRule) use ($id) {
            return $taxAreaRule->getTaxId() === $id;
        });
    }

    public function getCustomerGroupIds(): array
    {
        return $this->fmap(function (TaxAreaRuleBasicStruct $taxAreaRule) {
            return $taxAreaRule->getCustomerGroupId();
        });
    }

    public function filterByCustomerGroupId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleBasicStruct $taxAreaRule) use ($id) {
            return $taxAreaRule->getCustomerGroupId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return TaxAreaRuleBasicStruct::class;
    }
}
