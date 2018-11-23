<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\CheckoutContext;

class AbsolutePriceCalculator implements PriceCalculatorInterface
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

    public function supports(PriceDefinitionInterface $priceDefinition): bool
    {
        return $priceDefinition instanceof AbsolutePriceDefinition;
    }

    /**
     * @param AbsolutePriceDefinition $priceDefinition
     * @param PriceCollection         $prices
     * @param CheckoutContext         $context
     *
     * @return Price
     */
    public function calculate(PriceDefinitionInterface $priceDefinition, PriceCollection $prices, CheckoutContext $context): Price
    {
        $taxRules = $this->percentageTaxRuleBuilder->buildRules($prices->sum());

        $priceDefinition = new QuantityPriceDefinition($priceDefinition->getPrice(), $taxRules, 1, true);

        return $this->priceCalculator->calculate($priceDefinition, $context);
    }
}
