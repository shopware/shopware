<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class PercentagePriceCalculator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CashRounding $rounding,
        private readonly QuantityPriceCalculator $priceCalculator,
        private readonly PercentageTaxRuleBuilder $percentageTaxRuleBuilder
    ) {
    }

    /**
     * Provide a negative percentage value for discount or a positive percentage value for a surcharge
     *
     * @param float $percentage 10.00 for 10%, -10.0 for -10%
     */
    public function calculate(float $percentage, PriceCollection $prices, SalesChannelContext $context): CalculatedPrice
    {
        $totalPrice = $prices->getTotalPriceAmount();
        $discount = $this->round(
            $totalPrice / 100 * $percentage,
            $context
        );

        $rules = $this->percentageTaxRuleBuilder->buildCollectionRules($prices->getCalculatedTaxes(), $totalPrice);

        if (Feature::isActive('v6.8.0.0')) {
            $taxes = $context->getTaxState() !== CartPrice::TAX_STATE_FREE ? $prices->getCalculatedTaxes() : new CalculatedTaxCollection();
            foreach ($taxes as $tax) {
                $tax->setTax($this->round($tax->getTax() / 100 * $percentage, $context));
                $tax->setPrice($this->round($tax->getPrice() / 100 * $percentage, $context));
            }

            return new CalculatedPrice(
                $discount,
                $discount,
                $taxes,
                $rules,
            );
        }
        $definition = new QuantityPriceDefinition($discount, $rules, 1);

        return $this->priceCalculator->calculate($definition, $context);
    }

    private function round(float $price, SalesChannelContext $context): float
    {
        if ($context->getTaxState() !== CartPrice::TAX_STATE_GROSS && !$context->getItemRounding()->roundForNet()) {
            return $this->rounding->mathRound($price, $context->getItemRounding());
        }

        return $this->rounding->cashRound($price, $context->getItemRounding());
    }
}
