<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\CheckoutContext;

class PercentagePriceCalculator implements PriceCalculatorInterface
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

    public function supports(PriceDefinitionInterface $priceDefinition): bool
    {
        return $priceDefinition instanceof PercentagePriceDefinition;
    }

    /**
     * Provide a negative percentage value for discount or a positive percentage value for a surcharge
     *
     * @param PercentagePriceDefinition $priceDefinition
     * @param PriceCollection           $prices
     * @param CheckoutContext           $context
     *
     * @return Price
     */
    public function calculate(PriceDefinitionInterface $priceDefinition, PriceCollection $prices, CheckoutContext $context): Price
    {
        $price = $prices->sum();

        $percentage = $priceDefinition->getPercentage();

        $discount = $this->rounding->round($price->getTotalPrice() / 100 * $percentage);

        $rules = $this->percentageTaxRuleBuilder->buildRules($price);

        $definition = new QuantityPriceDefinition($discount, $rules, 1, true);

        return $this->priceCalculator->calculate($definition, $context);
    }
}
