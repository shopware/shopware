<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Tax\Struct\TaxAreaRuleBasicStruct;

class TaxAreaRuleBasicCollection extends EntityCollection
{
    /**
     * @var TaxAreaRuleBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? TaxAreaRuleBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): TaxAreaRuleBasicStruct
    {
        return parent::current();
    }

    public function getCountryAreaUuids(): array
    {
        return $this->fmap(function (TaxAreaRuleBasicStruct $taxAreaRule) {
            return $taxAreaRule->getCountryAreaUuid();
        });
    }

    public function filterByCountryAreaUuid(string $uuid): self
    {
        return $this->filter(function (TaxAreaRuleBasicStruct $taxAreaRule) use ($uuid) {
            return $taxAreaRule->getCountryAreaUuid() === $uuid;
        });
    }

    public function getCountryUuids(): array
    {
        return $this->fmap(function (TaxAreaRuleBasicStruct $taxAreaRule) {
            return $taxAreaRule->getCountryUuid();
        });
    }

    public function filterByCountryUuid(string $uuid): self
    {
        return $this->filter(function (TaxAreaRuleBasicStruct $taxAreaRule) use ($uuid) {
            return $taxAreaRule->getCountryUuid() === $uuid;
        });
    }

    public function getCountryStateUuids(): array
    {
        return $this->fmap(function (TaxAreaRuleBasicStruct $taxAreaRule) {
            return $taxAreaRule->getCountryStateUuid();
        });
    }

    public function filterByCountryStateUuid(string $uuid): self
    {
        return $this->filter(function (TaxAreaRuleBasicStruct $taxAreaRule) use ($uuid) {
            return $taxAreaRule->getCountryStateUuid() === $uuid;
        });
    }

    public function getTaxUuids(): array
    {
        return $this->fmap(function (TaxAreaRuleBasicStruct $taxAreaRule) {
            return $taxAreaRule->getTaxUuid();
        });
    }

    public function filterByTaxUuid(string $uuid): self
    {
        return $this->filter(function (TaxAreaRuleBasicStruct $taxAreaRule) use ($uuid) {
            return $taxAreaRule->getTaxUuid() === $uuid;
        });
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (TaxAreaRuleBasicStruct $taxAreaRule) {
            return $taxAreaRule->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): self
    {
        return $this->filter(function (TaxAreaRuleBasicStruct $taxAreaRule) use ($uuid) {
            return $taxAreaRule->getCustomerGroupUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return TaxAreaRuleBasicStruct::class;
    }
}
