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
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceRuleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductPriceDefinitionBuilder implements ProductPriceDefinitionBuilderInterface
{
    public function build(ProductEntity $product, SalesChannelContext $context, int $quantity = 1): ProductPriceDefinitions
    {
        $matchingRulePrices = $this->getFirstMatchingPriceRule($product->getPrices(), $context);
        $listingPrice = $this->buildListingPriceDefinition($product, $matchingRulePrices, $context);
        $quantity = $this->getMinPurchase($product, $quantity, $matchingRulePrices);

        return new ProductPriceDefinitions(
            $this->buildPriceDefinition($product, $matchingRulePrices, $context),
            $this->buildPriceDefinitions($product, $matchingRulePrices, $context),
            $listingPrice['from'],
            $listingPrice['to'],
            $this->buildPriceDefinitionForQuantity($product, $matchingRulePrices, $context, $quantity)
        );
    }

    /**
     * @param ProductPriceEntity[]|null $matchingRulePrices
     */
    private function buildPriceDefinitions(ProductEntity $product, ?array $matchingRulePrices, SalesChannelContext $context): PriceDefinitionCollection
    {
        if ($matchingRulePrices === null || \count($matchingRulePrices) === 0) {
            return new PriceDefinitionCollection();
        }

        $taxRules = $context->buildTaxRules($product->getTaxId());

        $matchingRulePrices = $this->sortByQuantity($matchingRulePrices);

        $definitions = [];

        $relatedMinPurchase = $this->getMinPurchase($product, $matchingRulePrices[0]->getQuantityStart(), $matchingRulePrices);

        foreach ($matchingRulePrices as $price) {
            if ($price->getQuantityEnd() && $relatedMinPurchase > $price->getQuantityEnd()) {
                continue;
            }

            $quantity = $price->getQuantityEnd() ?? $price->getQuantityStart();
            if ($quantity < $relatedMinPurchase) {
                $quantity = $relatedMinPurchase;
            }

            $definitions[] = new QuantityPriceDefinition(
                $this->getCurrencyPrice($price, $context),
                $taxRules,
                $context->getContext()->getCurrencyPrecision(),
                $quantity,
                true,
                $this->buildReferencePriceDefinition($product),
                $this->getListPrice($price->getPrice(), $context)
            );
        }

        return new PriceDefinitionCollection($definitions);
    }

    /**
     * @param ProductPriceEntity[]|null $matchingRulePrices
     */
    private function buildPriceDefinition(ProductEntity $product, ?array $matchingRulePrices, SalesChannelContext $context): QuantityPriceDefinition
    {
        $price = $this->getProductCurrencyPrice($product, $context);
        $quantity = $this->getMinPurchase($product, 1, $matchingRulePrices);

        return new QuantityPriceDefinition(
            $price,
            $context->buildTaxRules($product->getTaxId()),
            $context->getContext()->getCurrencyPrecision(),
            $quantity,
            true,
            $this->buildReferencePriceDefinition($product),
            $this->getListPrice($product->getPrice(), $context)
        );
    }

    /**
     * @param ProductPriceEntity[]|null $matchingRulePrices
     */
    private function buildListingPriceDefinition(ProductEntity $product, ?array $matchingRulePrices, SalesChannelContext $context): array
    {
        $taxRules = $context->buildTaxRules($product->getTaxId());

        $currencyPrecision = $context->getContext()->getCurrencyPrecision();
        $quantity = $this->getMinPurchase($product, 1, $matchingRulePrices);

        if ($product->getListingPrices()) {
            $listingPrice = $product->getListingPrices()->getContextPrice($context->getContext());

            if ($listingPrice) {
                // indexed listing prices are indexed for each currency
                $from = $this->getPriceForTaxState($listingPrice->getFrom(), $context);

                $to = $this->getPriceForTaxState($listingPrice->getTo(), $context);

                if ($listingPrice->getCurrencyId() !== $context->getContext()->getCurrencyId()) {
                    $from *= $context->getContext()->getCurrencyFactor();
                    $to *= $context->getContext()->getCurrencyFactor();
                }

                return [
                    'from' => new QuantityPriceDefinition($from, $taxRules, $currencyPrecision, $quantity, true, $this->buildReferencePriceDefinition($product)),
                    'to' => new QuantityPriceDefinition($to, $taxRules, $currencyPrecision, $quantity, true, $this->buildReferencePriceDefinition($product)),
                ];
            }
        }

        if ($matchingRulePrices === null) {
            $price = $this->getProductCurrencyPrice($product, $context);

            $definition = new QuantityPriceDefinition($price, $taxRules, $currencyPrecision, $quantity, true, $this->buildReferencePriceDefinition($product));

            return ['from' => $definition, 'to' => $definition];
        }

        $highest = $this->getCurrencyPrice($matchingRulePrices[0], $context);
        $lowest = $highest;

        foreach ($matchingRulePrices as $price) {
            $value = $this->getCurrencyPrice($price, $context);

            $highest = $value > $highest ? $value : $highest;
            $lowest = $value < $lowest ? $value : $lowest;
        }

        return [
            'from' => new QuantityPriceDefinition($lowest, $taxRules, $currencyPrecision, $quantity, true, $this->buildReferencePriceDefinition($product)),
            'to' => new QuantityPriceDefinition($highest, $taxRules, $currencyPrecision, $quantity, true, $this->buildReferencePriceDefinition($product)),
        ];
    }

    /**
     * @param ProductPriceEntity[]|null $matchingRulePrices
     */
    private function buildPriceDefinitionForQuantity(ProductEntity $product, ?array $matchingRulePrices, SalesChannelContext $context, int $quantity): QuantityPriceDefinition
    {
        $taxRules = $context->buildTaxRules($product->getTaxId());

        if ($matchingRulePrices === null || \count($matchingRulePrices) === 0) {
            $price = $this->getProductCurrencyPrice($product, $context);

            return new QuantityPriceDefinition(
                $price,
                $taxRules,
                $context->getContext()->getCurrencyPrecision(),
                $quantity,
                true,
                $this->buildReferencePriceDefinition($product),
                $this->getListPrice($product->getPrice(), $context)
            );
        }

        $prices = $this->getQuantityPrices($matchingRulePrices, $quantity);

        return new QuantityPriceDefinition(
            $this->getCurrencyPrice($prices[0], $context),
            $taxRules,
            $context->getContext()->getCurrencyPrecision(),
            $quantity,
            true,
            $this->buildReferencePriceDefinition($product),
            $this->getListPrice($prices[0]->getPrice(), $context)
        );
    }

    private function getQuantityPrices(array $prices, int $quantity): array
    {
        $filtered = [];

        /** @var ProductPriceEntity $price */
        foreach ($prices as $price) {
            $end = $price->getQuantityEnd() ?? $quantity + 1;

            if ($end >= $quantity && $price->getQuantityStart() <= $quantity) {
                $filtered[] = $price;
            }
        }

        return $filtered;
    }

    private function getFirstMatchingPriceRule(ProductPriceCollection $rules, SalesChannelContext $context): ?array
    {
        foreach ($context->getRuleIds() as $ruleId) {
            $filtered = $this->filterByRuleId($rules->getElements(), $ruleId);

            if (\count($filtered) > 0) {
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
        usort($prices, static function (ProductPriceEntity $a, ProductPriceEntity $b) {
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

    private function getListPrice(?PriceCollection $prices, SalesChannelContext $context): ?float
    {
        if (!$prices) {
            return null;
        }

        $price = $prices->getCurrencyPrice($context->getCurrency()->getId());
        if (!$price || !$price->getListPrice()) {
            return null;
        }
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $value = $price->getListPrice()->getGross();
        } else {
            $value = $price->getListPrice()->getNet();
        }

        if ($price->getCurrencyId() !== $context->getCurrency()->getId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    private function getProductCurrencyPrice(ProductEntity $product, SalesChannelContext $context): float
    {
        $price = $product->getPrice()->getCurrencyPrice($context->getCurrency()->getId());

        if (!$price) {
            return 0.0;
        }

        $value = $this->getPriceForTaxState($price, $context);

        if ($price->getCurrencyId() !== $context->getCurrency()->getId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    /**
     * @param ProductPriceEntity[]|null $matchingRulePrices
     */
    private function getMinPurchase(ProductEntity $product, int $quantity, ?array $matchingRulePrices): int
    {
        $minPurchase = (int) $product->getMinPurchase();

        if ($quantity < $minPurchase) {
            $quantity = $minPurchase;
        }

        if ($matchingRulePrices === null || \count($matchingRulePrices) === 0) {
            return $quantity;
        }

        $minPurchaseByPrices = $this->getMinPurchaseByPrices($matchingRulePrices);
        if ($quantity < $minPurchaseByPrices) {
            $product->setMinPurchase($minPurchaseByPrices);
            $quantity = $minPurchaseByPrices;
        }

        return $quantity;
    }

    private function getMinPurchaseByPrices(array $matchingRulePrices): int
    {
        $sortedPrices = $this->sortByQuantity($matchingRulePrices);

        $firstPrice = $sortedPrices[0];

        return $firstPrice->getQuantityStart();
    }
}
