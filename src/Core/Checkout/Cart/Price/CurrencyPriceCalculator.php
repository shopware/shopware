<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class CurrencyPriceCalculator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly QuantityPriceCalculator $priceCalculator,
        private readonly PercentageTaxRuleBuilder $percentageTaxRuleBuilder,
        private readonly AbstractPriceSelector $priceSelector
    ) {
    }

    public function calculate(PriceCollection $price, CalculatedPriceCollection $prices, SalesChannelContext $context, int $quantity = 1): CalculatedPrice
    {
        $currency = $price->getCurrencyPrice($context->getCurrencyId());

        if (!$currency) {
            throw CartException::invalidPriceDefinition();
        }

        $selected = $this->priceSelector->select($currency, $context);
        $value = $selected->getValue();

        if ($currency->getCurrencyId() !== $context->getCurrencyId()) {
            $value *= $context->getCurrency()->getFactor();
        }

        $taxRules = $this->percentageTaxRuleBuilder->buildCollectionRules($prices->getCalculatedTaxes(), $prices->getTotalPriceAmount());
        $definition = new QuantityPriceDefinition($value, $taxRules, $quantity);
        $definition->setIsCalculated($selected->isCalculated());

        return $this->priceCalculator->calculate($definition, $context);
    }
}
