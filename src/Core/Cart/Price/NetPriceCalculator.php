<?php declare(strict_types=1);

namespace Shopware\Cart\Price;

use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Cart\Tax\TaxCalculator;

class NetPriceCalculator
{
    /**
     * @var TaxCalculator
     */
    private $taxCalculator;

    /**
     * @var PriceRounding
     */
    private $priceRounding;

    public function __construct(TaxCalculator $taxCalculator, PriceRounding $priceRounding)
    {
        $this->taxCalculator = $taxCalculator;
        $this->priceRounding = $priceRounding;
    }

    public function calculateCollection(PriceDefinitionCollection $collection): CalculatedPriceCollection
    {
        $prices = $collection->map(
            function (PriceDefinition $definition) {
                return $this->calculate($definition);
            }
        );

        return new CalculatedPriceCollection($prices);
    }

    public function calculate(PriceDefinition $definition): CalculatedPrice
    {
        $unitPrice = $this->getUnitPrice($definition);

        $price = $this->priceRounding->round(
            $unitPrice * $definition->getQuantity()
        );

        $taxRules = $definition->getTaxRules();

        $calculatedTaxes = $this->taxCalculator->calculateNetTaxes($price, $definition->getTaxRules());

        return new CalculatedPrice($unitPrice, $price, $calculatedTaxes, $taxRules, $definition->getQuantity());
    }

    private function getUnitPrice(PriceDefinition $definition): float
    {
        //unit price already calculated?
        if ($definition->isCalculated()) {
            return $definition->getPrice();
        }

        return $this->priceRounding->round($definition->getPrice());
    }
}
