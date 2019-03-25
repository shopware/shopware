<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;

class GrossPriceCalculator
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

    public function calculateCollection(PriceDefinitionCollection $collection): PriceCollection
    {
        $prices = $collection->map(
            function (QuantityPriceDefinition $definition) {
                return $this->calculate($definition);
            }
        );

        return new PriceCollection($prices);
    }

    public function calculate(QuantityPriceDefinition $definition): CalculatedPrice
    {
        $unitPrice = $this->getUnitPrice($definition);

        $price = $this->priceRounding->round(
            $unitPrice * $definition->getQuantity(),
            $definition->getPrecision()
        );

        $calculatedTaxes = $this->taxCalculator->calculateGrossTaxes($price, $definition->getPrecision(), $definition->getTaxRules());

        return new CalculatedPrice(
            $unitPrice,
            $price,
            $calculatedTaxes,
            $definition->getTaxRules(),
            $definition->getQuantity()
        );
    }

    private function getUnitPrice(QuantityPriceDefinition $definition): float
    {
        //unit price already calculated?
        if ($definition->isCalculated()) {
            return $definition->getPrice();
        }

        $price = $this->taxCalculator->calculateGross(
            $definition->getPrice(),
            $definition->getPrecision(),
            $definition->getTaxRules()
        );

        return $this->priceRounding->round($price, $definition->getPrecision());
    }
}
