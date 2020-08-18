<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;

class NetPriceCalculator
{
    /**
     * @var TaxCalculator
     */
    private $taxCalculator;

    /**
     * @var PriceRoundingInterface
     */
    private $priceRounding;

    /**
     * @var ReferencePriceCalculator
     */
    private $referencePriceCalculator;

    public function __construct(
        TaxCalculator $taxCalculator,
        PriceRoundingInterface $priceRounding,
        ReferencePriceCalculator $referencePriceCalculator
    ) {
        $this->taxCalculator = $taxCalculator;
        $this->priceRounding = $priceRounding;
        $this->referencePriceCalculator = $referencePriceCalculator;
    }

    public function calculate(QuantityPriceDefinition $definition): CalculatedPrice
    {
        $unitPrice = $this->getUnitPrice($definition);

        $taxRules = $definition->getTaxRules();

        $calculatedTaxes = $this->taxCalculator->calculateNetTaxes(
            $unitPrice,
            $definition->getTaxRules()
        );

        foreach ($calculatedTaxes as $tax) {
            $total = $this->priceRounding->round($tax->getTax() * $definition->getQuantity(), $definition->getPrecision());
            $tax->setTax($total);
            $tax->setPrice($tax->getPrice() * $definition->getQuantity());
        }

        $price = $this->priceRounding->round(
            $unitPrice * $definition->getQuantity(),
            $definition->getPrecision()
        );

        return new CalculatedPrice(
            $unitPrice,
            $price,
            $calculatedTaxes,
            $taxRules,
            $definition->getQuantity(),
            $this->referencePriceCalculator->calculate($price, $definition),
            $this->calculateListPrice($unitPrice, $definition)
        );
    }

    private function getUnitPrice(QuantityPriceDefinition $definition): float
    {
        //item price already calculated?
        if ($definition->isCalculated()) {
            return $definition->getPrice();
        }

        return $this->priceRounding->round(
            $definition->getPrice(),
            $definition->getPrecision()
        );
    }

    private function calculateListPrice(float $unitPrice, QuantityPriceDefinition $definition): ?ListPrice
    {
        if (!$definition->getListPrice()) {
            return null;
        }

        $listPrice = $definition->getListPrice();
        if (!$definition->isCalculated()) {
            $listPrice = $this->priceRounding->round($definition->getListPrice(), $definition->getPrecision());
        }

        return ListPrice::createFromUnitPrice($unitPrice, $listPrice);
    }
}
