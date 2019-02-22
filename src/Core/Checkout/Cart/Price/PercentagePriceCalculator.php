<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\CheckoutContext;

class PercentagePriceCalculator
{
    /**
     * @var PriceRounding
     */
    private $rounding;

    /**
     * @var QuantityPriceCalculator
     */
    private $priceCalculator;

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    public function __construct(
        PriceRounding $rounding,
        QuantityPriceCalculator $priceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder
    ) {
        $this->rounding = $rounding;
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    /**
     * Provide a negative percentage value for discount or a positive percentage value for a surcharge
     *
     * @param float $percentage 10.00 for 10%, -10.0 for -10%
     */
    public function calculate($percentage, PriceCollection $prices, CheckoutContext $context): CalculatedPrice
    {
        $price = $prices->sum();

        $discount = $this->rounding->round($price->getTotalPrice() / 100 * $percentage);

        $rules = $this->percentageTaxRuleBuilder->buildRules($price);

        $definition = new QuantityPriceDefinition($discount, $rules, 1, true);

        return $this->priceCalculator->calculate($definition, $context);
    }
}
