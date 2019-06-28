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
    public function build(
        ProductEntity $product,
        SalesChannelContext $salesChannelContext,
        int $quantity = 1
    ): ProductPriceDefinitions {
        $listingPrice = $this->buildListingPriceDefinition($product, $salesChannelContext);

        return new ProductPriceDefinitions(
            $this->buildPriceDefinition($product, $salesChannelContext),
            $this->buildPriceDefinitions($product, $salesChannelContext),
            $listingPrice['from'],
            $listingPrice['to'],
            $this->buildPriceDefinitionForQuantity($product, $salesChannelContext, $quantity)
        );
    }

    private function buildPriceDefinitions(
        ProductEntity $product,
        SalesChannelContext $salesChannelContext
    ): PriceDefinitionCollection {
        $taxRules = $product->getTaxRuleCollection();

        $prices = $this->getFirstMatchingPriceRule($product->getPrices(), $salesChannelContext);

        if (!$prices) {
            return new PriceDefinitionCollection();
        }

        $prices->sortByQuantity();

        $definitions = [];

        foreach ($prices as $price) {
            $quantity = $price->getQuantityEnd() ?? $price->getQuantityStart();

            $definitions[] = new QuantityPriceDefinition(
                $this->getCurrencyPrice($price, $salesChannelContext),
                $taxRules,
                $salesChannelContext->getContext()->getCurrencyPrecision(),
                $quantity,
                true
            );
        }

        return new PriceDefinitionCollection($definitions);
    }

    private function buildPriceDefinition(
        ProductEntity $product,
        SalesChannelContext $salesChannelContext
    ): QuantityPriceDefinition {
        $price = $this->getPriceForTaxState(
            $product->getCurrencyPrice($salesChannelContext->getCurrency()->getId()),
            $salesChannelContext
        );

        return new QuantityPriceDefinition(
            $price * $salesChannelContext->getContext()->getCurrencyFactor(),
            $product->getTaxRuleCollection(),
            $salesChannelContext->getContext()->getCurrencyPrecision(),
            1,
            true
        );
    }

    private function buildListingPriceDefinition(ProductEntity $product, SalesChannelContext $salesChannelContext): array
    {
        $taxRules = $product->getTaxRuleCollection();

        $currencyPrecision = $salesChannelContext->getContext()->getCurrencyPrecision();

        $currencyId = $salesChannelContext->getCurrency()->getId();

        $factor = $salesChannelContext->getContext()->getCurrencyFactor();

        if ($product->getListingPrices()) {
            $listingPrice = $product->getListingPrices()->getContextPrice($salesChannelContext->getContext());

            if ($listingPrice) {
                // indexed listing prices are indexed for each currency
                $from = $this->getPriceForTaxState($listingPrice->getFrom(), $salesChannelContext);

                $to = $this->getPriceForTaxState($listingPrice->getTo(), $salesChannelContext);

                return [
                    'from' => new QuantityPriceDefinition($from, $taxRules, $currencyPrecision, 1, true),
                    'to' => new QuantityPriceDefinition($to, $taxRules, $currencyPrecision, 1, true),
                ];
            }
        }

        $prices = $this->getFirstMatchingPriceRule($product->getPrices(), $salesChannelContext);

        if (!$prices || $prices->count() <= 0) {
            $price = $this->getPriceForTaxState($product->getCurrencyPrice($currencyId), $salesChannelContext);

            $definition = new QuantityPriceDefinition($price * $factor, $taxRules, $currencyPrecision, 1, true);

            return ['from' => $definition, 'to' => $definition];
        }

        $highest = $this->getCurrencyPrice($prices->first(), $salesChannelContext);
        $lowest = $highest;

        foreach ($prices as $price) {
            $value = $this->getCurrencyPrice($price, $salesChannelContext);

            $highest = $value > $highest ? $value : $highest;
            $lowest = $value < $lowest ? $value : $lowest;
        }

        return [
            'from' => new QuantityPriceDefinition($lowest, $taxRules, $currencyPrecision, 1, true),
            'to' => new QuantityPriceDefinition($highest, $taxRules, $currencyPrecision, 1, true),
        ];
    }

    private function buildPriceDefinitionForQuantity(
        ProductEntity $product,
        SalesChannelContext $salesChannelContext,
        int $quantity
    ): QuantityPriceDefinition {
        $taxRules = $product->getTaxRuleCollection();

        /** @var ProductPriceCollection|null $prices */
        $prices = $this->getFirstMatchingPriceRule($product->getPrices(), $salesChannelContext);

        if (!$prices) {
            $price = $this->getPriceForTaxState(
                $product->getCurrencyPrice($salesChannelContext->getCurrency()->getId()),
                $salesChannelContext
            );

            return new QuantityPriceDefinition(
                $price * $salesChannelContext->getContext()->getCurrencyFactor(),
                $taxRules,
                $salesChannelContext->getContext()->getCurrencyPrecision(),
                $quantity,
                true
            );
        }

        $prices = $prices->getQuantityPrices($quantity);

        return new QuantityPriceDefinition(
            $this->getCurrencyPrice($prices->first(), $salesChannelContext),
            $taxRules,
            $salesChannelContext->getContext()->getCurrencyPrecision(),
            $quantity,
            true
        );
    }

    private function getFirstMatchingPriceRule(
        ProductPriceCollection $rules,
        SalesChannelContext $context
    ): ?ProductPriceCollection {
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
