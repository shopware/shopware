<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\CheckoutContext;

class AbsolutePriceCalculator
{
    /**
     * @var QuantityPriceCalculator
     */
    private $priceCalculator;

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    public function __construct(QuantityPriceCalculator $priceCalculator, PercentageTaxRuleBuilder $percentageTaxRuleBuilder)
    {
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    public function calculate(float $price, PriceCollection $prices, CheckoutContext $context): Price
    {
        $taxRules = $this->percentageTaxRuleBuilder->buildRules($prices->sum());

        $priceDefinition = new QuantityPriceDefinition($price, $taxRules, 1, true);

        return $this->priceCalculator->calculate($priceDefinition, $context);
    }
}
