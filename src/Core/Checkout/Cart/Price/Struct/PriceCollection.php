<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Struct\Collection;

class PriceCollection extends Collection
{
    /**
     * @var Price[]
     */
    protected $elements = [];

    public function add(Price $price): void
    {
        parent::doAdd($price);
    }

    public function remove(int $key): void
    {
        parent::doRemoveByKey($key);
    }

    public function get(int $key): ? Price
    {
        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    public function getTaxRules(): TaxRuleCollection
    {
        $rules = new TaxRuleCollection([]);
        foreach ($this->elements as $price) {
            $rules = $rules->merge($price->getTaxRules());
        }

        return $rules;
    }

    public function sum(): Price
    {
        return new Price(
            $this->getUnitPriceAmount(),
            $this->getAmount(),
            $this->getCalculatedTaxes(),
            $this->getTaxRules()
        );
    }

    public function getCalculatedTaxes(): CalculatedTaxCollection
    {
        $taxes = new CalculatedTaxCollection([]);
        foreach ($this->elements as $price) {
            $taxes = $taxes->merge($price->getCalculatedTaxes());
        }

        return $taxes;
    }

    public function merge(self $prices): self
    {
        return $this->doMerge($prices);
    }

    private function getUnitPriceAmount(): float
    {
        $prices = $this->map(function (Price $price) {
            return $price->getUnitPrice();
        });

        return array_sum($prices);
    }

    private function getAmount(): float
    {
        $prices = $this->map(function (Price $price) {
            return $price->getTotalPrice();
        });

        return array_sum($prices);
    }
}
