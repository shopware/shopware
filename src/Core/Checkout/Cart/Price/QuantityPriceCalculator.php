<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class QuantityPriceCalculator
{
    private GrossPriceCalculator $grossPriceCalculator;

    private NetPriceCalculator $netPriceCalculator;

    public function __construct(
        GrossPriceCalculator $grossPriceCalculator,
        NetPriceCalculator $netPriceCalculator
    ) {
        $this->grossPriceCalculator = $grossPriceCalculator;
        $this->netPriceCalculator = $netPriceCalculator;
    }

    public function calculate(QuantityPriceDefinition $definition, SalesChannelContext $context): CalculatedPrice
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $price = $this->grossPriceCalculator->calculate($definition, $context->getItemRounding());
        } else {
            $price = $this->netPriceCalculator->calculate($definition, $context->getItemRounding());
        }

        $taxRules = $price->getTaxRules();
        $calculatedTaxes = $price->getCalculatedTaxes();

        if ($context->getTaxState() === CartPrice::TAX_STATE_FREE) {
            $taxRules = new TaxRuleCollection();
            $calculatedTaxes = new CalculatedTaxCollection();
        }

        return new CalculatedPrice(
            $price->getUnitPrice(),
            $price->getTotalPrice(),
            $calculatedTaxes,
            $taxRules,
            $price->getQuantity(),
            $price->getReferencePrice(),
            $price->getListPrice()
        );
    }
}
