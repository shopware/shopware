<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Pricing\Price;
use Shopware\Core\Framework\Pricing\PriceRuleCollection;
use Shopware\Core\Framework\Pricing\PriceRuleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductPriceDefinitionBuilder implements ProductPriceDefinitionBuilderInterface
{
    public function buildPriceDefinitions(ProductEntity $product, SalesChannelContext $context): PriceDefinitionCollection
    {
        $taxRules = $product->getTaxRuleCollection();

        $prices = $this->getFirstMatchingPriceRule($product->getPrices(), $context);

        if (!$prices) {
            return new PriceDefinitionCollection();
        }

        if (\in_array($context->getContext()->getCurrencyId(), $prices->getCurrencyIds(), true)) {
            /** @var ProductPriceCollection $prices */
            $prices = $prices->filterByCurrencyId($context->getContext()->getCurrencyId());
        } else {
            /** @var ProductPriceCollection $prices */
            $prices = $prices->filterByCurrencyId(Defaults::CURRENCY);
        }

        $prices->sortByQuantity();

        $definitions = $prices->map(function (ProductPriceEntity $rule) use ($taxRules, $context) {
            $quantity = $rule->getQuantityEnd() ?? $rule->getQuantityStart();

            return new QuantityPriceDefinition(
                $this->getRulePrice($rule, $context),
                $taxRules,
                $context->getContext()->getCurrencyPrecision(),
                $quantity,
                true
            );
        });

        return new PriceDefinitionCollection($definitions);
    }

    public function buildPriceDefinition(ProductEntity $product, SalesChannelContext $context): QuantityPriceDefinition
    {
        return new QuantityPriceDefinition(
            $this->getPriceForTaxState($product->getPrice(), $context) * $context->getContext()->getCurrencyFactor(),
            $product->getTaxRuleCollection(),
            $context->getContext()->getCurrencyPrecision(),
            1,
            true
        );
    }

    public function buildListingPriceDefinition(ProductEntity $product, SalesChannelContext $context): QuantityPriceDefinition
    {
        $taxRules = $product->getTaxRuleCollection();

        if ($product->getListingPrices()) {
            $prices = $product->getListingPrices();
        } else {
            $prices = $product->getPrices()->filter(
                function (ProductPriceEntity $price) {
                    return $price->getQuantityEnd() === null;
                }
            );
        }

        if (!$prices || $prices->count() <= 0) {
            return new QuantityPriceDefinition(
                $this->getPriceForTaxState($product->getPrice(), $context) * $context->getContext()->getCurrencyFactor(),
                $taxRules,
                $context->getContext()->getCurrencyPrecision(),
                1,
                true
            );
        }

        return new QuantityPriceDefinition(
            $this->getPriceForContext($prices, $context),
            $taxRules,
            $context->getContext()->getCurrencyPrecision(),
            1,
            true
        );
    }

    public function buildPriceDefinitionForQuantity(ProductEntity $product, SalesChannelContext $context, int $quantity): QuantityPriceDefinition
    {
        $taxRules = $product->getTaxRuleCollection();

        /** @var ProductPriceCollection|null $prices */
        $prices = $this->getFirstMatchingPriceRule($product->getPrices(), $context);

        if (!$prices) {
            return new QuantityPriceDefinition(
                $this->getPriceForTaxState($product->getPrice(), $context) * $context->getContext()->getCurrencyFactor(),
                $taxRules,
                $context->getContext()->getCurrencyPrecision(),
                $quantity,
                true
            );
        }

        $prices = $prices->getQuantityPrices($quantity);

        return new QuantityPriceDefinition(
            $this->getPriceForContext($prices, $context),
            $taxRules,
            $context->getContext()->getCurrencyPrecision(),
            $quantity,
            true
        );
    }

    private function getFirstMatchingPriceRule(PriceRuleCollection $rules, SalesChannelContext $context): ?PriceRuleCollection
    {
        foreach ($context->getRuleIds() as $ruleId) {
            $rules = $rules->filterByRuleId($ruleId);

            if ($rules->count() > 0) {
                return $rules;
            }
        }

        return null;
    }

    private function getPriceForContext(PriceRuleCollection $rules, SalesChannelContext $context): float
    {
        if ($context->getContext()->getCurrencyId() !== Defaults::CURRENCY) {
            $currencyRules = $rules->filterByCurrencyId($context->getContext()->getCurrencyId());
            if ($currencyRules->count() > 0) {
                return $this->getRulePrice($currencyRules->first(), $context);
            }
        }

        $defaultRules = $rules->filterByCurrencyId(Defaults::CURRENCY);
        if ($defaultRules->count() > 0) {
            return $this->getRulePrice($defaultRules->first(), $context);
        }

        throw new \RuntimeException(sprintf('PriceRule for RuleId "%s" not found for default currency', $rules->first()->getRuleId()));
    }

    private function getRulePrice(PriceRuleEntity $rule, SalesChannelContext $context): float
    {
        $price = $this->getPriceForTaxState($rule->getPrice(), $context);

        if ($rule->getCurrencyId() === Defaults::CURRENCY) {
            $price *= $context->getContext()->getCurrencyFactor();
        }

        return $price;
    }

    private function getPriceForTaxState(Price $price, SalesChannelContext $context): float
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return $price->getGross();
        }

        return $price->getNet();
    }
}
