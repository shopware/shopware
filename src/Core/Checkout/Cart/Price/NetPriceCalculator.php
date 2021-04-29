<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;

class NetPriceCalculator
{
    /**
     * @var TaxCalculator
     */
    private $taxCalculator;

    /**
     * @var CashRounding
     */
    private $priceRounding;

    public function __construct(
        TaxCalculator $taxCalculator,
        CashRounding $priceRounding
    ) {
        $this->taxCalculator = $taxCalculator;
        $this->priceRounding = $priceRounding;
    }

    public function calculate(QuantityPriceDefinition $definition, CashRoundingConfig $config): CalculatedPrice
    {
        $unitPrice = $this->round($definition->getPrice(), $config);

        $taxRules = $definition->getTaxRules();

        $calculatedTaxes = $this->taxCalculator->calculateNetTaxes(
            $unitPrice,
            $definition->getTaxRules()
        );

        foreach ($calculatedTaxes as $tax) {
            $total = $this->priceRounding->mathRound(
                $tax->getTax() * $definition->getQuantity(),
                $config
            );
            $tax->setTax($total);
            $tax->setPrice($tax->getPrice() * $definition->getQuantity());
        }

        $price = $this->round(
            $unitPrice * $definition->getQuantity(),
            $config
        );

        $reference = $this->calculateReferencePrice($unitPrice, $definition->getReferencePriceDefinition(), $config);

        return new CalculatedPrice(
            $unitPrice,
            $price,
            $calculatedTaxes,
            $taxRules,
            $definition->getQuantity(),
            $reference,
            $this->calculateListPrice($unitPrice, $definition, $config)
        );
    }

    private function calculateListPrice(float $unitPrice, QuantityPriceDefinition $definition, CashRoundingConfig $config): ?ListPrice
    {
        $listPrice = $definition->getListPrice();
        if (!$listPrice) {
            return null;
        }

        if (!$definition->isCalculated()) {
            $listPrice = $this->round($listPrice, $config);
        }

        return ListPrice::createFromUnitPrice($unitPrice, $listPrice);
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

    private function round(float $price, CashRoundingConfig $config): float
    {
        if ($config->roundForNet()) {
            return $this->priceRounding->cashRound($price, $config);
        }

        return $this->priceRounding->mathRound($price, $config);
    }
}
