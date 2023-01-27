<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
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
        private readonly PercentageTaxRuleBuilder $percentageTaxRuleBuilder
    ) {
    }

    public function calculate(PriceCollection $price, CalculatedPriceCollection $prices, SalesChannelContext $context, int $quantity = 1): CalculatedPrice
    {
        $currency = $price->getCurrencyPrice($context->getCurrencyId());

        if (!$currency) {
            throw CartException::invalidPriceDefinition();
        }

        $value = $context->getTaxState() === CartPrice::TAX_STATE_GROSS ? $currency->getGross() : $currency->getNet();

        if ($currency->getCurrencyId() !== $context->getCurrencyId()) {
            $value *= $context->getCurrency()->getFactor();
        }

        $taxRules = $this->percentageTaxRuleBuilder->buildRules($prices->sum());

        $definition = new QuantityPriceDefinition($value, $taxRules, $quantity);

        return $this->priceCalculator->calculate($definition, $context);
    }
}
