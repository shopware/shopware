<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PercentagePriceCalculator
{
    /**
     * @var PriceRoundingInterface
     */
    private $rounding;

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $taxRuleBuilder;

    public function __construct(PriceRoundingInterface $rounding, PercentageTaxRuleBuilder $taxRuleBuilder)
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

        $discount = $this->rounding->round(
            $total->getTotalPrice() / 100 * $percentage,
            $context->getContext()->getCurrencyPrecision()
        );

        $taxes = new CalculatedTaxCollection();
        foreach ($prices->getCalculatedTaxes() as $calculatedTax) {
            $tax = $this->rounding->round(
                $calculatedTax->getTax() / 100 * $percentage,
                $context->getContext()->getCurrencyPrecision()
            );

            $price = $this->rounding->round(
                $calculatedTax->getPrice() / 100 * $percentage,
                $context->getContext()->getCurrencyPrecision()
            );

            $taxes->add(
                new CalculatedTax($tax, $calculatedTax->getTaxRate(), $price)
            );
        }

        $rules = $this->taxRuleBuilder->buildRules($total);

        return new CalculatedPrice($discount, $discount, $taxes, $rules, 1);
    }
}
