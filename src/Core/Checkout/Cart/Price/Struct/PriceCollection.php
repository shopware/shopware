<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<CalculatedPrice>
 */
#[Package('checkout')]
class PriceCollection extends Collection
{
    public function get($key): ?CalculatedPrice
    {
        $key = (int) $key;

        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    public function getTaxRules(): TaxRuleCollection
    {
        $rules = new TaxRuleCollection([]);

        foreach ($this->getIterator() as $price) {
            $rules = $rules->merge($price->getTaxRules());
        }

        return $rules;
    }

    public function sum(): CalculatedPrice
    {
        return new CalculatedPrice(
            $this->getUnitPriceAmount(),
            $this->getAmount(),
            $this->getCalculatedTaxes(),
            $this->getTaxRules()
        );
    }

    public function getCalculatedTaxes(): CalculatedTaxCollection
    {
        $taxes = new CalculatedTaxCollection([]);

        foreach ($this->getIterator() as $price) {
            $taxes->merge($price->getCalculatedTaxes());
        }

        return $taxes;
    }

    public function getHighestTaxRule(): TaxRuleCollection
    {
        $rules = new TaxRuleCollection();

        $highestRate = $this->getTaxRules()->highestRate();

        if ($highestRate !== null) {
            $rules->add(new TaxRule($highestRate->getTaxRate(), 100));
        }

        return $rules;
    }

    public function merge(self $prices): self
    {
        return new self(array_merge($this->elements, $prices->getElements()));
    }

    public function getApiAlias(): string
    {
        return 'cart_price_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return CalculatedPrice::class;
    }

    private function getUnitPriceAmount(): float
    {
        $prices = $this->map(fn (CalculatedPrice $price) => $price->getUnitPrice());

        return array_sum($prices);
    }

    private function getAmount(): float
    {
        $prices = $this->map(fn (CalculatedPrice $price) => $price->getTotalPrice());

        return array_sum($prices);
    }
}
