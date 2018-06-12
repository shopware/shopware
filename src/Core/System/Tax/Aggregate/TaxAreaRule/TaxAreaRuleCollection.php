<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRule;

use Shopware\Core\Framework\ORM\EntityCollection;


class TaxAreaRuleCollection extends EntityCollection
{
    /**
     * @var TaxAreaRuleStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? TaxAreaRuleStruct
    {
        return parent::get($id);
    }

    public function current(): TaxAreaRuleStruct
    {
        return parent::current();
    }

    public function getCountryAreaIds(): array
    {
        return $this->fmap(function (TaxAreaRuleStruct $taxAreaRule) {
            return $taxAreaRule->getCountryAreaId();
        });
    }

    public function filterByCountryAreaId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleStruct $taxAreaRule) use ($id) {
            return $taxAreaRule->getCountryAreaId() === $id;
        });
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (TaxAreaRuleStruct $taxAreaRule) {
            return $taxAreaRule->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleStruct $taxAreaRule) use ($id) {
            return $taxAreaRule->getCountryId() === $id;
        });
    }

    public function getCountryStateIds(): array
    {
        return $this->fmap(function (TaxAreaRuleStruct $taxAreaRule) {
            return $taxAreaRule->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleStruct $taxAreaRule) use ($id) {
            return $taxAreaRule->getCountryStateId() === $id;
        });
    }

    public function getTaxIds(): array
    {
        return $this->fmap(function (TaxAreaRuleStruct $taxAreaRule) {
            return $taxAreaRule->getTaxId();
        });
    }

    public function filterByTaxId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleStruct $taxAreaRule) use ($id) {
            return $taxAreaRule->getTaxId() === $id;
        });
    }

    public function getCustomerGroupIds(): array
    {
        return $this->fmap(function (TaxAreaRuleStruct $taxAreaRule) {
            return $taxAreaRule->getCustomerGroupId();
        });
    }

    public function filterByCustomerGroupId(string $id): self
    {
        return $this->filter(function (TaxAreaRuleStruct $taxAreaRule) use ($id) {
            return $taxAreaRule->getCustomerGroupId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return TaxAreaRuleStruct::class;
    }
}
