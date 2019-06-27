<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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

    public function calculate(float $price, PriceCollection $prices, SalesChannelContext $context, int $quantity = 1): CalculatedPrice
    {
        $taxRules = $this->percentageTaxRuleBuilder->buildRules($prices->sum());

        $priceDefinition = new QuantityPriceDefinition($price, $taxRules, $context->getContext()->getCurrencyPrecision(), $quantity, true);

        return $this->priceCalculator->calculate($priceDefinition, $context);
    }
}
