<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class QuantityPriceCalculator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GrossPriceCalculator $grossPriceCalculator,
        private readonly NetPriceCalculator $netPriceCalculator
    ) {
    }

    public function calculate(QuantityPriceDefinition $definition, SalesChannelContext $context): CalculatedPrice
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $price = $this->grossPriceCalculator->calculate($definition, $context->getItemRounding());
        } else {
            $price = $this->netPriceCalculator->calculate($definition, $context->getItemRounding());
        }

        if ($context->getTaxState() === CartPrice::TAX_STATE_FREE) {
            $price->assign([
                'taxRules' => new TaxRuleCollection(),
                'calculatedTaxes' => new CalculatedTaxCollection(),
            ]);
        }

        return $price;
    }
}
