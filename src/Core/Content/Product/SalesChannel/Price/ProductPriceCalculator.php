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
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Unit\UnitCollection;

class ProductPriceCalculator extends AbstractProductPriceCalculator
{
    private EntityRepositoryInterface $unitRepository;

    private QuantityPriceCalculator $calculator;

    private ?UnitCollection $units = null;

    public function __construct(EntityRepositoryInterface $unitRepository, QuantityPriceCalculator $calculator)
    {
        $this->unitRepository = $unitRepository;
        $this->calculator = $calculator;
    }

    public function getDecorated(): AbstractProductPriceCalculator
    {
        throw new DecorationPatternException(self::class);
    }

    public function calculate(iterable $products, SalesChannelContext $context): void
    {
        $units = $this->getUnits($context);

        /** @var SalesChannelProductEntity $product */
        foreach ($products as $product) {
            $this->calculatePrice($product, $context, $units);
            $this->calculateAdvancePrices($product, $context, $units);
            $this->calculateCheapestPrice($product, $context, $units);
        }
    }

    private function calculatePrice(SalesChannelProductEntity $product, SalesChannelContext $context, UnitCollection $units): void
    {
        $reference = ReferencePriceDto::createFromProduct($product);

        \assert($product->getPrice() !== null);
        $definition = $this->buildDefinition($product, $product->getPrice(), $context, $units, $reference);

        $price = $this->calculator->calculate($definition, $context);

        $product->setCalculatedPrice($price);
    }

    private function calculateAdvancePrices(SalesChannelProductEntity $product, SalesChannelContext $context, UnitCollection $units): void
    {
        if ($product->getPrices() === null) {
            return;
        }
        $prices = $this->filterRulePrices($product->getPrices(), $context);

        if ($prices === null) {
            $product->setCalculatedPrices(new CalculatedPriceCollection());

            return;
        }
        $prices->sortByQuantity();

        $reference = ReferencePriceDto::createFromProduct($product);

        $calculated = new CalculatedPriceCollection();
        foreach ($prices as $price) {
            $quantity = $price->getQuantityEnd() ?? $price->getQuantityStart();

            $definition = $this->buildDefinition($product, $price->getPrice(), $context, $units, $reference, $quantity);

            $calculated->add($this->calculator->calculate($definition, $context));
        }

        $product->setCalculatedPrices($calculated);
    }

    private function calculateCheapestPrice(SalesChannelProductEntity $product, SalesChannelContext $context, UnitCollection $units): void
    {
        $price = $product->getCheapestPrice();

        if (!$price instanceof CheapestPrice) {
            $reference = ReferencePriceDto::createFromProduct($product);

            \assert($product->getPrice() !== null);
            $definition = $this->buildDefinition($product, $product->getPrice(), $context, $units, $reference);

            $cheapest = CalculatedCheapestPrice::createFrom(
                $this->calculator->calculate($definition, $context)
            );

            $cheapest->setHasRange($product->getCalculatedPrices()->count() > 1);

            $product->setCalculatedCheapestPrice($cheapest);

            return;
        }

        $reference = ReferencePriceDto::createFromCheapestPrice($price);

        $definition = $this->buildDefinition($product, $price->getPrice(), $context, $units, $reference);

        $cheapest = CalculatedCheapestPrice::createFrom(
            $this->calculator->calculate($definition, $context)
        );

        $cheapest->setHasRange($price->hasRange());

        $product->setCalculatedCheapestPrice($cheapest);
    }

    private function buildDefinition(
        SalesChannelProductEntity $product,
        PriceCollection $prices,
        SalesChannelContext $context,
        UnitCollection $units,
        ReferencePriceDto $reference,
        int $quantity = 1
    ): QuantityPriceDefinition {
        $price = $this->getPriceValue($prices, $context);

        \assert($product->getTaxId() !== null);
        $definition = new QuantityPriceDefinition($price, $context->buildTaxRules($product->getTaxId()), $quantity);
        $definition->setReferencePriceDefinition(
            $this->buildReferencePriceDefinition($reference, $units)
        );
        $definition->setListPrice(
            $this->getListPrice($prices, $context)
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

    private function getListPrice(?PriceCollection $prices, SalesChannelContext $context): ?float
    {
        if (!$prices) {
            return null;
        }

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

    private function buildReferencePriceDefinition(ReferencePriceDto $definition, UnitCollection $units): ?ReferencePriceDefinition
    {
        if ($definition->getPurchase() === null || $definition->getPurchase() <= 0) {
            return null;
        }
        if ($definition->getUnitId() === null) {
            return null;
        }
        if ($definition->getReference() === null || $definition->getReference() <= 0) {
            return null;
        }
        if ($definition->getPurchase() === $definition->getReference()) {
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

        /** @var UnitCollection $units */
        $units = $this->unitRepository
            ->search(new Criteria(), $context->getContext())
            ->getEntities();

        return $this->units = $units;
    }
}
