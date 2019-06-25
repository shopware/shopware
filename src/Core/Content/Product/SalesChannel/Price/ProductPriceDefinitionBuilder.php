<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Pricing\Price;
use Shopware\Core\Framework\Pricing\PriceRuleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductPriceDefinitionBuilder implements ProductPriceDefinitionBuilderInterface
{
    public function build(ProductEntity $product, SalesChannelContext $context, int $quantity = 1): ProductPriceDefinitions
    {
        $listingPrice = $this->buildListingPriceDefinition($product, $context);

        return new ProductPriceDefinitions(
            $this->buildPriceDefinition($product, $context),
            $this->buildPriceDefinitions($product, $context),
            $listingPrice['from'],
            $listingPrice['to'],
            $this->buildPriceDefinitionForQuantity($product, $context, $quantity)
        );
    }

    private function buildPriceDefinitions(ProductEntity $product, SalesChannelContext $context): PriceDefinitionCollection
    {
        $taxRules = $product->getTaxRuleCollection();

        $prices = $this->getFirstMatchingPriceRule($product->getPrices(), $context);

        if (!$prices) {
            return new PriceDefinitionCollection();
        }

        $prices->sortByQuantity();

        $definitions = [];

        foreach ($prices as $price) {
            $quantity = $price->getQuantityEnd() ?? $price->getQuantityStart();

            $definitions[] = new QuantityPriceDefinition(
                $this->getCurrencyPrice($price, $context),
                $taxRules,
                $context->getContext()->getCurrencyPrecision(),
                $quantity,
                true
            );
        }

        return new PriceDefinitionCollection($definitions);
    }

    private function buildPriceDefinition(ProductEntity $product, SalesChannelContext $context): QuantityPriceDefinition
    {
        $price = $this->getPriceForTaxState($product->getCurrencyPrice($context->getCurrency()->getId()), $context);

        return new QuantityPriceDefinition(
            $price * $context->getContext()->getCurrencyFactor(),
            $product->getTaxRuleCollection(),
            $context->getContext()->getCurrencyPrecision(),
            1,
            true
        );
    }

    private function buildListingPriceDefinition(ProductEntity $product, SalesChannelContext $context): array
    {
        $taxRules = $product->getTaxRuleCollection();

        $currencyPrecision = $context->getContext()->getCurrencyPrecision();

        $currencyId = $context->getCurrency()->getId();

        $factor = $context->getContext()->getCurrencyFactor();

        if ($product->getListingPrices()) {
            $listingPrice = $product->getListingPrices()->getContextPrice($context->getContext());

            if ($listingPrice) {
                // indexed listing prices are indexed for each currency
                $from = $this->getPriceForTaxState($listingPrice->getFrom(), $context);

                $to = $this->getPriceForTaxState($listingPrice->getTo(), $context);

                return [
                    'from' => new QuantityPriceDefinition($from, $taxRules, $currencyPrecision, 1, true),
                    'to' => new QuantityPriceDefinition($to, $taxRules, $currencyPrecision, 1, true),
                ];
            }
        }

        $prices = $this->getFirstMatchingPriceRule($product->getPrices(), $context);

        if (!$prices || $prices->count() <= 0) {
            $price = $this->getPriceForTaxState($product->getCurrencyPrice($currencyId), $context);

            $definition = new QuantityPriceDefinition($price * $factor, $taxRules, $currencyPrecision, 1, true);

            return ['from' => $definition, 'to' => $definition];
        }

        $highest = $this->getCurrencyPrice($prices->first(), $context);
        $lowest = $highest;

        foreach ($prices as $price) {
            $value = $this->getCurrencyPrice($price, $context);

            $highest = $value > $highest ? $value : $highest;
            $lowest = $value < $lowest ? $value : $lowest;
        }

        return [
            'from' => new QuantityPriceDefinition($lowest, $taxRules, $currencyPrecision, 1, true),
            'to' => new QuantityPriceDefinition($highest, $taxRules, $currencyPrecision, 1, true),
        ];
    }

    private function buildPriceDefinitionForQuantity(ProductEntity $product, SalesChannelContext $context, int $quantity): QuantityPriceDefinition
    {
        $taxRules = $product->getTaxRuleCollection();

        /** @var ProductPriceCollection|null $prices */
        $prices = $this->getFirstMatchingPriceRule($product->getPrices(), $context);

        if (!$prices) {
            $price = $this->getPriceForTaxState($product->getCurrencyPrice($context->getCurrency()->getId()), $context);

            return new QuantityPriceDefinition(
                $price * $context->getContext()->getCurrencyFactor(),
                $taxRules,
                $context->getContext()->getCurrencyPrecision(),
                $quantity,
                true
            );
        }

        $prices = $prices->getQuantityPrices($quantity);

        return new QuantityPriceDefinition(
            $this->getCurrencyPrice($prices->first(), $context),
            $taxRules,
            $context->getContext()->getCurrencyPrecision(),
            $quantity,
            true
        );
    }

    private function getFirstMatchingPriceRule(ProductPriceCollection $rules, SalesChannelContext $context): ?ProductPriceCollection
    {
        foreach ($context->getRuleIds() as $ruleId) {
            $filtered = $rules->filterByRuleId($ruleId);

            if ($filtered->count() > 0) {
                /* @var ProductPriceCollection $filtered */
                return $filtered;
            }
        }

        return null;
    }

    private function getCurrencyPrice(PriceRuleEntity $rule, SalesChannelContext $context): float
    {
        $price = $rule->getPrice()->getCurrencyPrice($context->getCurrency()->getId());

        $value = $this->getPriceForTaxState($price, $context);

        if ($price->getCurrencyId() === Defaults::CURRENCY) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    private function getPriceForTaxState(Price $price, SalesChannelContext $context): float
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return $price->getGross();
        }

        return $price->getNet();
    }
}
