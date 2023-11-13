<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Unit\UnitCollection;

#[Package('inventory')]
class ProductPriceCalculator extends AbstractProductPriceCalculator
{
    private ?UnitCollection $units = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $unitRepository,
        private readonly QuantityPriceCalculator $calculator
    ) {
    }

    public function getDecorated(): AbstractProductPriceCalculator
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param Entity[] $products
     */
    public function calculate(iterable $products, SalesChannelContext $context): void
    {
        $units = $this->getUnits($context);

        /** @var Entity $product */
        foreach ($products as $product) {
            $this->calculatePrice($product, $context, $units);
            $this->calculateAdvancePrices($product, $context, $units);
            $this->calculateCheapestPrice($product, $context, $units);
        }
    }

    public function reset(): void
    {
        $this->units = null;
    }

    private function calculatePrice(Entity $product, SalesChannelContext $context, UnitCollection $units): void
    {
        $price = $product->get('price');
        $taxId = $product->get('taxId');

        if ($price === null || $taxId === null) {
            return;
        }
        $reference = ReferencePriceDto::createFromEntity($product);

        $definition = $this->buildDefinition($product, $price, $context, $units, $reference);

        $price = $this->calculator->calculate($definition, $context);

        $product->assign([
            'calculatedPrice' => $price,
        ]);
    }

    private function calculateAdvancePrices(Entity $product, SalesChannelContext $context, UnitCollection $units): void
    {
        $prices = $product->get('prices');

        $product->assign(['calculatedPrices' => new CalculatedPriceCollection()]);
        if ($prices === null) {
            return;
        }

        if (!$prices instanceof ProductPriceCollection) {
            return;
        }

        $prices = $this->filterRulePrices($prices, $context);
        if ($prices === null) {
            return;
        }
        $prices->sortByQuantity();

        $reference = ReferencePriceDto::createFromEntity($product);

        $calculated = new CalculatedPriceCollection();
        foreach ($prices as $price) {
            $quantity = $price->getQuantityEnd() ?? $price->getQuantityStart();

            $definition = $this->buildDefinition($product, $price->getPrice(), $context, $units, $reference, $quantity);

            $calculated->add($this->calculator->calculate($definition, $context));
        }

        $product->assign(['calculatedPrices' => $calculated]);
    }

    private function calculateCheapestPrice(Entity $product, SalesChannelContext $context, UnitCollection $units): void
    {
        $cheapest = $product->get('cheapestPrice');

        if ($product->get('taxId') === null) {
            return;
        }

        if (!$cheapest instanceof CheapestPrice) {
            $price = $product->get('price');
            if ($price === null) {
                return;
            }

            $reference = ReferencePriceDto::createFromEntity($product);

            $definition = $this->buildDefinition($product, $price, $context, $units, $reference);

            $calculated = CalculatedCheapestPrice::createFrom(
                $this->calculator->calculate($definition, $context)
            );

            $prices = $product->get('calculatedPrices');

            $hasRange = $prices instanceof CalculatedPriceCollection && $prices->count() > 1;

            $calculated->setHasRange($hasRange);

            $product->assign(['calculatedCheapestPrice' => $calculated]);

            return;
        }

        $reference = ReferencePriceDto::createFromCheapestPrice($cheapest);

        $definition = $this->buildDefinition($product, $cheapest->getPrice(), $context, $units, $reference);

        $calculated = CalculatedCheapestPrice::createFrom(
            $this->calculator->calculate($definition, $context)
        );
        $calculated->setVariantId($cheapest->getVariantId());

        $calculated->setHasRange($cheapest->hasRange());

        $product->assign(['calculatedCheapestPrice' => $calculated]);
    }

    private function buildDefinition(
        Entity $product,
        PriceCollection $prices,
        SalesChannelContext $context,
        UnitCollection $units,
        ReferencePriceDto $reference,
        int $quantity = 1
    ): QuantityPriceDefinition {
        $price = $this->getPriceValue($prices, $context);

        $taxId = $product->get('taxId');
        $definition = new QuantityPriceDefinition($price, $context->buildTaxRules($taxId), $quantity);
        $definition->setReferencePriceDefinition(
            $this->buildReferencePriceDefinition($reference, $units)
        );
        $definition->setListPrice(
            $this->getListPrice($prices, $context)
        );
        $definition->setRegulationPrice(
            $this->getRegulationPrice($prices, $context)
        );

        return $definition;
    }

    private function getPriceValue(PriceCollection $price, SalesChannelContext $context): float
    {
        /** @var Price $currency */
        $currency = $price->getCurrencyPrice($context->getCurrencyId());

        $value = $this->getPriceForTaxState($currency, $context);

        if ($currency->getCurrencyId() !== $context->getCurrency()->getId()) {
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

    private function getListPrice(PriceCollection $prices, SalesChannelContext $context): ?float
    {
        $price = $prices->getCurrencyPrice($context->getCurrency()->getId());
        if ($price === null || $price->getListPrice() === null) {
            return null;
        }

        $value = $this->getPriceForTaxState($price->getListPrice(), $context);

        if ($price->getCurrencyId() !== $context->getCurrency()->getId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    private function getRegulationPrice(PriceCollection $prices, SalesChannelContext $context): ?float
    {
        $price = $prices->getCurrencyPrice($context->getCurrency()->getId());
        if ($price === null || $price->getRegulationPrice() === null) {
            return null;
        }

        $taxPrice = $this->getPriceForTaxState($price, $context);
        $value = $this->getPriceForTaxState($price->getRegulationPrice(), $context);
        if ($taxPrice === 0.0 || $taxPrice === $value) {
            return null;
        }

        if ($price->getCurrencyId() !== $context->getCurrency()->getId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    private function buildReferencePriceDefinition(ReferencePriceDto $definition, UnitCollection $units): ?ReferencePriceDefinition
    {
        if (
            $definition->getPurchase() === null
            || $definition->getPurchase() <= 0
            || $definition->getUnitId() === null
            || $definition->getReference() === null
            || $definition->getReference() <= 0
            || $definition->getPurchase() === $definition->getReference()
        ) {
            return null;
        }

        $unit = $units->get($definition->getUnitId());
        if ($unit === null) {
            return null;
        }

        return new ReferencePriceDefinition(
            $definition->getPurchase(),
            $definition->getReference(),
            $unit->getTranslation('name')
        );
    }

    private function filterRulePrices(ProductPriceCollection $rules, SalesChannelContext $context): ?ProductPriceCollection
    {
        foreach ($context->getRuleIds() as $ruleId) {
            $filtered = $rules->filterByRuleId($ruleId);

            if (\count($filtered) > 0) {
                return $filtered;
            }
        }

        return null;
    }

    private function getUnits(SalesChannelContext $context): UnitCollection
    {
        if ($this->units !== null) {
            return $this->units;
        }

        $criteria = new Criteria();
        $criteria->setTitle('product-price-calculator::units');

        /** @var UnitCollection $units */
        $units = $this->unitRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $this->units = $units;
    }
}
