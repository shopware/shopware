<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\RegulationPrice;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class GrossPriceCalculator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly TaxCalculator $taxCalculator,
        private readonly CashRounding $priceRounding
    ) {
    }

    public function calculate(QuantityPriceDefinition $definition, CashRoundingConfig $config): CalculatedPrice
    {
        $unitPrice = $this->getUnitPrice($definition, $config);

        $unitTaxes = $this->taxCalculator->calculateGrossTaxes($unitPrice, $definition->getTaxRules());

        foreach ($unitTaxes as $tax) {
            $total = $this->priceRounding->mathRound(
                $tax->getTax() * $definition->getQuantity(),
                $config
            );

            $tax->setTax($total);

            $tax->setPrice($tax->getPrice() * $definition->getQuantity());
        }

        $price = $this->priceRounding->cashRound(
            $unitPrice * $definition->getQuantity(),
            $config
        );

        $reference = $this->calculateReferencePrice($unitPrice, $definition->getReferencePriceDefinition(), $config);

        return new CalculatedPrice(
            $unitPrice,
            $price,
            $unitTaxes,
            $definition->getTaxRules(),
            $definition->getQuantity(),
            $reference,
            $this->calculateListPrice($unitPrice, $definition, $config),
            $this->calculateRegulationPrice($definition, $config)
        );
    }

    private function getUnitPrice(QuantityPriceDefinition $definition, CashRoundingConfig $config): float
    {
        //item price already calculated?
        if ($definition->isCalculated()) {
            return $this->priceRounding->cashRound($definition->getPrice(), $config);
        }

        $price = $this->taxCalculator->calculateGross(
            $definition->getPrice(),
            $definition->getTaxRules()
        );

        return $this->priceRounding->cashRound($price, $config);
    }

    private function calculateListPrice(float $unitPrice, QuantityPriceDefinition $definition, CashRoundingConfig $config): ?ListPrice
    {
        $price = $definition->getListPrice();
        if (!$price) {
            return null;
        }

        if (!$definition->isCalculated()) {
            $price = $this->taxCalculator->calculateGross(
                $price,
                $definition->getTaxRules()
            );
        }

        $listPrice = $this->priceRounding->cashRound($price, $config);

        return ListPrice::createFromUnitPrice($unitPrice, $listPrice);
    }

    private function calculateRegulationPrice(QuantityPriceDefinition $definition, CashRoundingConfig $config): ?RegulationPrice
    {
        $price = $definition->getRegulationPrice();
        if (!$price) {
            return null;
        }

        if (!$definition->isCalculated()) {
            $price = $this->taxCalculator->calculateGross(
                $price,
                $definition->getTaxRules()
            );
        }

        $regulationPrice = $this->priceRounding->cashRound($price, $config);

        return new RegulationPrice($regulationPrice);
    }

    private function calculateReferencePrice(float $price, ?ReferencePriceDefinition $definition, CashRoundingConfig $config): ?ReferencePrice
    {
        if (!$definition) {
            return null;
        }

        if ($definition->getPurchaseUnit() <= 0 || $definition->getReferenceUnit() <= 0) {
            return null;
        }

        $price = $price / $definition->getPurchaseUnit() * $definition->getReferenceUnit();

        $price = $this->priceRounding->mathRound($price, $config);

        return new ReferencePrice(
            $price,
            $definition->getPurchaseUnit(),
            $definition->getReferenceUnit(),
            $definition->getUnitName()
        );
    }
}
