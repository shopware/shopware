<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceRuleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductPriceDefinitionBuilder implements ProductPriceDefinitionBuilderInterface
{
    public function build(ProductEntity $product, SalesChannelContext $salesChannelContext, int $quantity = 1): ProductPriceDefinitions
    {
        $listingPrice = $this->buildListingPriceDefinition($product, $salesChannelContext);

        return new ProductPriceDefinitions(
            $this->buildPriceDefinition($product, $salesChannelContext),
            $this->buildPriceDefinitions($product, $salesChannelContext),
            $listingPrice['from'],
            $listingPrice['to'],
            $this->buildPriceDefinitionForQuantity($product, $salesChannelContext, $quantity)
        );
    }

    private function buildPriceDefinitions(ProductEntity $product, SalesChannelContext $salesChannelContext): PriceDefinitionCollection
    {
        $taxRules = $salesChannelContext->buildTaxRules($product->getTaxId());

        $prices = $this->getFirstMatchingPriceRule($product->getPrices(), $salesChannelContext);

        if (!$prices) {
            return new PriceDefinitionCollection();
        }

        $prices = $this->sortByQuantity($prices);

        $definitions = [];

        foreach ($prices as $price) {
            $quantity = $price->getQuantityEnd() ?? $price->getQuantityStart();

            $definitions[] = new QuantityPriceDefinition(
                $this->getCurrencyPrice($price, $salesChannelContext),
                $taxRules,
                $salesChannelContext->getContext()->getCurrencyPrecision(),
                $quantity,
                true,
                $this->buildReferencePriceDefinition($product)
            );
        }

        return new PriceDefinitionCollection($definitions);
    }

    private function buildPriceDefinition(ProductEntity $product, SalesChannelContext $salesChannelContext): QuantityPriceDefinition
    {
        $price = $this->getProductCurrencyPrice($product, $salesChannelContext);

        return new QuantityPriceDefinition(
            $price,
            $salesChannelContext->buildTaxRules($product->getTaxId()),
            $salesChannelContext->getContext()->getCurrencyPrecision(),
            1,
            true,
            $this->buildReferencePriceDefinition($product)
        );
    }

    private function buildListingPriceDefinition(ProductEntity $product, SalesChannelContext $salesChannelContext): array
    {
        $taxRules = $salesChannelContext->buildTaxRules($product->getTaxId());

        $currencyPrecision = $salesChannelContext->getContext()->getCurrencyPrecision();

        if ($product->getListingPrices()) {
            $listingPrice = $product->getListingPrices()->getContextPrice($salesChannelContext->getContext());

            if ($listingPrice) {
                // indexed listing prices are indexed for each currency
                $from = $this->getPriceForTaxState($listingPrice->getFrom(), $salesChannelContext);

                $to = $this->getPriceForTaxState($listingPrice->getTo(), $salesChannelContext);

                return [
                    'from' => new QuantityPriceDefinition($from, $taxRules, $currencyPrecision, 1, true, $this->buildReferencePriceDefinition($product)),
                    'to' => new QuantityPriceDefinition($to, $taxRules, $currencyPrecision, 1, true, $this->buildReferencePriceDefinition($product)),
                ];
            }
        }

        $prices = $this->getFirstMatchingPriceRule($product->getPrices(), $salesChannelContext);

        if (!$prices || count($prices) <= 0) {
            $price = $this->getProductCurrencyPrice($product, $salesChannelContext);

            $definition = new QuantityPriceDefinition($price, $taxRules, $currencyPrecision, 1, true, $this->buildReferencePriceDefinition($product));

            return ['from' => $definition, 'to' => $definition];
        }

        $highest = $this->getCurrencyPrice($prices[0], $salesChannelContext);
        $lowest = $highest;

        foreach ($prices as $price) {
            $value = $this->getCurrencyPrice($price, $salesChannelContext);

            $highest = $value > $highest ? $value : $highest;
            $lowest = $value < $lowest ? $value : $lowest;
        }

        return [
            'from' => new QuantityPriceDefinition($lowest, $taxRules, $currencyPrecision, 1, true, $this->buildReferencePriceDefinition($product)),
            'to' => new QuantityPriceDefinition($highest, $taxRules, $currencyPrecision, 1, true, $this->buildReferencePriceDefinition($product)),
        ];
    }

    private function buildPriceDefinitionForQuantity(ProductEntity $product, SalesChannelContext $salesChannelContext, int $quantity): QuantityPriceDefinition
    {
        $taxRules = $salesChannelContext->buildTaxRules($product->getTaxId());

        /** @var ProductPriceEntity[]|null $prices */
        $prices = $this->getFirstMatchingPriceRule($product->getPrices(), $salesChannelContext);

        if (!$prices) {
            $price = $this->getProductCurrencyPrice($product, $salesChannelContext);

            return new QuantityPriceDefinition(
                $price,
                $taxRules,
                $salesChannelContext->getContext()->getCurrencyPrecision(),
                $quantity,
                true,
                $this->buildReferencePriceDefinition($product)
            );
        }

        $prices = $this->getQuantityPrices($prices, $quantity);

        return new QuantityPriceDefinition(
            $this->getCurrencyPrice($prices[0], $salesChannelContext),
            $taxRules,
            $salesChannelContext->getContext()->getCurrencyPrecision(),
            $quantity,
            true,
            $this->buildReferencePriceDefinition($product)
        );
    }

    private function getQuantityPrices(array $prices, int $quantity): array
    {
        $filtered = [];

        /** @var ProductPriceEntity $price */
        foreach ($prices as $price) {
            $end = $price->getQuantityEnd() ?? $quantity + 1;

            if ($price->getQuantityStart() <= $quantity && $end >= $quantity) {
                $filtered[] = $price;
            }
        }

        return $filtered;
    }

    private function getFirstMatchingPriceRule(ProductPriceCollection $rules, SalesChannelContext $context): ?array
    {
        foreach ($context->getRuleIds() as $ruleId) {
            $filtered = $this->filterByRuleId($rules->getElements(), $ruleId);

            if (count($filtered) > 0) {
                return $filtered;
            }
        }

        return null;
    }

    private function filterByRuleId(array $rules, string $ruleId): array
    {
        $filtered = [];
        /** @var PriceRuleEntity $priceRule */
        foreach ($rules as $priceRule) {
            if ($priceRule->getRuleId() === $ruleId) {
                $filtered[] = $priceRule;
            }
        }

        return $filtered;
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

    private function sortByQuantity(array $prices): array
    {
        usort($prices, function (ProductPriceEntity $a, ProductPriceEntity $b) {
            return $a->getQuantityStart() <=> $b->getQuantityStart();
        });

        return $prices;
    }

    private function buildReferencePriceDefinition(ProductEntity $product): ?ReferencePriceDefinition
    {
        $referencePrice = null;
        if (
            $product->getPurchaseUnit()
            && $product->getReferenceUnit()
            && $product->getUnit() !== null
            && $product->getPurchaseUnit() !== $product->getReferenceUnit()
        ) {
            $referencePrice = new ReferencePriceDefinition(
                $product->getPurchaseUnit(),
                $product->getReferenceUnit(),
                (string) $product->getUnit()->getTranslation('name')
            );
        }

        return $referencePrice;
    }

    private function getProductCurrencyPrice(ProductEntity $product, SalesChannelContext $salesChannelContext): float
    {
        $productPrice = $product->getPrice()->getCurrencyPrice($salesChannelContext->getCurrency()->getId(), false);
        $isFallbackCurrency = false;

        if (!$productPrice) {
            $productPrice = $product->getPrice()->getCurrencyPrice($salesChannelContext->getCurrency()->getId());
            $isFallbackCurrency = true;
        }

        $price = $this->getPriceForTaxState(
            $productPrice,
            $salesChannelContext
        );

        if ($isFallbackCurrency) {
            $price *= $salesChannelContext->getContext()->getCurrencyFactor();
        }

        return $price;
    }
}
