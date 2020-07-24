<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PercentagePriceCalculator
{
    /**
     * @var CashRounding
     */
    private $rounding;

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $taxRuleBuilder;

    public function __construct(CashRounding $rounding, PercentageTaxRuleBuilder $taxRuleBuilder)
    {
        $this->rounding = $rounding;
        $this->taxRuleBuilder = $taxRuleBuilder;
    }

    /**
     * Provide a negative percentage value for discount or a positive percentage value for a surcharge
     *
     * @param float $percentage 10.00 for 10%, -10.0 for -10%
     */
    public function calculate(float $percentage, PriceCollection $prices, SalesChannelContext $context): CalculatedPrice
    {
        $total = $prices->sum();

        $discount = $this->round(
            $total->getTotalPrice() / 100 * $percentage,
            $context
        );

        $taxes = new CalculatedTaxCollection();
        foreach ($prices->getCalculatedTaxes() as $calculatedTax) {
            $tax = $this->round(
                $calculatedTax->getTax() / 100 * $percentage,
                $context
            );

            $price = $this->round(
                $calculatedTax->getPrice() / 100 * $percentage,
                $context
            );

            $taxes->add(
                new CalculatedTax($tax, $calculatedTax->getTaxRate(), $price)
            );
        }

        $rules = $this->taxRuleBuilder->buildRules($total);

        return new CalculatedPrice($discount, $discount, $taxes, $rules, 1);
    }

    private function round(float $price, SalesChannelContext $context): float
    {
        if ($context->getTaxState() !== CartPrice::TAX_STATE_GROSS && !$context->getItemRounding()->roundForNet()) {
            return $this->rounding->mathRound($price, $context->getItemRounding());
        }

        return $this->rounding->cashRound($price, $context->getItemRounding());
    }
}
