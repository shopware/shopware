<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Content\Product\Extension\ProductPriceCalculationExtension;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Unit\UnitCollection;

#[Package('inventory')]
class ProductPriceCalculator extends AbstractProductPriceCalculator
{
    private ?UnitCollection $units = null;

    /**
     * @internal
     *
     * @param EntityRepository<UnitCollection> $unitRepository
     */
    public function __construct(
        private readonly EntityRepository $unitRepository,
        private readonly QuantityPriceCalculator $calculator,
        private readonly ExtensionDispatcher $extensions,
        private readonly Connection $connection,
    ) {
    }

    public function getDecorated(): AbstractProductPriceCalculator
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param iterable<Entity> $products
     */
    public function calculate(iterable $products, SalesChannelContext $context): void
    {
        // allows full service decoration
        $this->extensions->publish(
            name: ProductPriceCalculationExtension::NAME,
            extension: new ProductPriceCalculationExtension($products, $context),
            function: $this->_calculate(...)
        );
    }

    public function reset(): void
    {
        $this->units = null;
    }

    /**
     * @param iterable<Entity> $products
     */
    private function _calculate(iterable $products, SalesChannelContext $context): void
    {
        $products = \is_array($products) ? $products : iterator_to_array($products, false);
        $units = $this->getUnits($context);
        $inheritedAdvancedPriceBases = $this->loadInheritedAdvancedPriceBases($products, $context);

        foreach ($products as $product) {
            $this->calculatePrice($product, $context, $units);
            $this->calculateAdvancePrices($product, $context, $units, $inheritedAdvancedPriceBases);
            $this->calculateCheapestPrice($product, $context, $units);
        }
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

    /**
     * @param array<string, PriceCollection> $inheritedAdvancedPriceBases
     */
    private function calculateAdvancePrices(Entity $product, SalesChannelContext $context, UnitCollection $units, array $inheritedAdvancedPriceBases): void
    {
        $prices = $product->get('prices');

        $product->assign(['calculatedPrices' => new CalculatedPriceCollection()]);
        if ($prices === null) {
            return;
        }

        if (!$prices instanceof EntityCollection || $prices->count() === 0) {
            return;
        }

        $prices = $this->filterRulePrices($prices, $context);
        if ($prices === null) {
            return;
        }
        $prices->sort(fn (Entity $a, Entity $b) => $a->get('quantityStart') <=> $b->get('quantityStart'));

        $reference = ReferencePriceDto::createFromEntity($product);

        $calculated = new CalculatedPriceCollection();
        foreach ($prices as $price) {
            $quantityStart = $price->get('quantityStart');
            $quantityEnd = $price->get('quantityEnd');
            $priceObj = $price->get('price');

            if (!$priceObj instanceof PriceCollection || (!\is_int($quantityStart) && !\is_int($quantityEnd))) {
                continue;
            }

            $quantity = \is_int($quantityEnd) ? $quantityEnd : $quantityStart;

            $definition = $this->buildDefinition(
                $product,
                $this->resolveInheritedAdvancedPrice($product, $price, $priceObj, $inheritedAdvancedPriceBases, $context),
                $context,
                $units,
                $reference,
                $quantity
            );

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
        $currency = $price->getCurrencyPrice($context->getCurrencyId());
        if ($currency === null) {
            throw ProductException::noPriceForCurrency($context->getCurrency());
        }

        $value = $this->getPriceForTaxState($currency, $context);

        if ($currency->getCurrencyId() !== $context->getCurrencyId()) {
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
        $price = $prices->getCurrencyPrice($context->getCurrencyId());
        if ($price === null || $price->getListPrice() === null) {
            return null;
        }

        $value = $this->getPriceForTaxState($price->getListPrice(), $context);

        if ($price->getCurrencyId() !== $context->getCurrencyId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    private function getRegulationPrice(PriceCollection $prices, SalesChannelContext $context): ?float
    {
        $price = $prices->getCurrencyPrice($context->getCurrencyId());
        if ($price === null || $price->getRegulationPrice() === null) {
            return null;
        }

        $taxPrice = $this->getPriceForTaxState($price, $context);
        $value = $this->getPriceForTaxState($price->getRegulationPrice(), $context);

        if ($taxPrice === 0.0) {
            return null;
        }

        if ($price->getCurrencyId() !== $context->getCurrencyId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    /**
     * @param array<Entity> $products
     *
     * @return array<string, PriceCollection>
     */
    private function loadInheritedAdvancedPriceBases(array $products, SalesChannelContext $context): array
    {
        $ownerIds = [];

        foreach ($products as $product) {
            $prices = $product->get('prices');

            if (!$prices instanceof EntityCollection) {
                continue;
            }

            $productId = $product->getUniqueIdentifier();

            foreach ($prices as $price) {
                $ownerId = $price->getVars()['productId'] ?? null;

                if (!\is_string($ownerId) || $ownerId === $productId) {
                    continue;
                }

                $ownerIds[$ownerId] = true;
            }
        }

        if ($ownerIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)) as id, price
             FROM product
             WHERE id IN (:ids)
             AND version_id = :version',
            [
                'ids' => Uuid::fromHexToBytesList(array_keys($ownerIds)),
                'version' => Uuid::fromHexToBytes($context->getContext()->getVersionId()),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $basePrices = [];
        foreach ($rows as $ownerId => $price) {
            if (!\is_string($ownerId) || !\is_string($price)) {
                continue;
            }

            $basePrices[$ownerId] = $this->decodePriceCollection($price);
        }

        return $basePrices;
    }

    /**
     * @param array<string, PriceCollection> $inheritedAdvancedPriceBases
     */
    private function resolveInheritedAdvancedPrice(Entity $product, Entity $advancedPrice, PriceCollection $price, array $inheritedAdvancedPriceBases, SalesChannelContext $context): PriceCollection
    {
        $ownerId = $advancedPrice->getVars()['productId'] ?? null;
        if (!\is_string($ownerId) || $ownerId === $product->getUniqueIdentifier()) {
            return $price;
        }

        $productPrice = $product->get('price');
        $ownerBasePrice = $inheritedAdvancedPriceBases[$ownerId] ?? null;

        if (!$productPrice instanceof PriceCollection || !$ownerBasePrice instanceof PriceCollection) {
            return $price;
        }

        return $this->applyPriceDelta($price, $productPrice, $ownerBasePrice, $context);
    }

    private function applyPriceDelta(PriceCollection $advancedPrice, PriceCollection $productPrice, PriceCollection $ownerBasePrice, SalesChannelContext $context): PriceCollection
    {
        $adjusted = [];
        $hasDelta = false;

        foreach ($advancedPrice as $price) {
            $delta = $this->resolvePriceDelta($price->getCurrencyId(), $productPrice, $ownerBasePrice, $context);

            if ($delta === null) {
                $adjusted[] = $this->clonePrice($price);

                continue;
            }

            if ($delta->getNet() !== 0.0 || $delta->getGross() !== 0.0) {
                $hasDelta = true;
            }

            $adjusted[] = $this->clonePrice($price, $delta);
        }

        if (!$hasDelta) {
            return $advancedPrice;
        }

        return new PriceCollection($adjusted);
    }

    private function resolvePriceDelta(string $currencyId, PriceCollection $productPrice, PriceCollection $ownerBasePrice, SalesChannelContext $context): ?Price
    {
        $productCurrencyPrice = $productPrice->getCurrencyPrice($currencyId);
        $ownerCurrencyPrice = $ownerBasePrice->getCurrencyPrice($currencyId);

        if (!$productCurrencyPrice instanceof Price || !$ownerCurrencyPrice instanceof Price) {
            return null;
        }

        $productValues = $this->convertPriceValues($productCurrencyPrice, $currencyId, $context);
        $ownerValues = $this->convertPriceValues($ownerCurrencyPrice, $currencyId, $context);

        if ($productValues === null || $ownerValues === null) {
            return null;
        }

        return new Price(
            $currencyId,
            $productValues['net'] - $ownerValues['net'],
            $productValues['gross'] - $ownerValues['gross'],
            $productCurrencyPrice->getLinked()
        );
    }

    /**
     * @return array{net: float, gross: float}|null
     */
    private function convertPriceValues(Price $price, string $currencyId, SalesChannelContext $context): ?array
    {
        if ($price->getCurrencyId() === $currencyId) {
            return [
                'net' => $price->getNet(),
                'gross' => $price->getGross(),
            ];
        }

        if ($currencyId !== $context->getCurrencyId()) {
            return null;
        }

        $factor = $context->getContext()->getCurrencyFactor();

        return [
            'net' => $price->getNet() * $factor,
            'gross' => $price->getGross() * $factor,
        ];
    }

    private function decodePriceCollection(string $value): PriceCollection
    {
        $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);

        $prices = [];
        foreach ($decoded as $price) {
            if (!\is_array($price)) {
                continue;
            }

            $prices[] = $this->decodePrice($price);
        }

        return new PriceCollection($prices);
    }

    /**
     * @param array<string, mixed> $price
     */
    private function decodePrice(array $price, ?string $currencyId = null, ?bool $linked = null): Price
    {
        $currencyId ??= (string) $price['currencyId'];
        $linked ??= (bool) ($price['linked'] ?? false);

        return new Price(
            $currencyId,
            (float) $price['net'],
            (float) $price['gross'],
            $linked,
            isset($price['listPrice']) && \is_array($price['listPrice']) ? $this->decodePrice($price['listPrice'], $currencyId, $linked) : null,
            isset($price['percentage']) && \is_array($price['percentage']) ? $price['percentage'] : null,
            isset($price['regulationPrice']) && \is_array($price['regulationPrice']) ? $this->decodePrice($price['regulationPrice'], $currencyId, $linked) : null
        );
    }

    private function clonePrice(Price $price, ?Price $delta = null): Price
    {
        return new Price(
            $price->getCurrencyId(),
            $price->getNet() + ($delta?->getNet() ?? 0.0),
            $price->getGross() + ($delta?->getGross() ?? 0.0),
            $price->getLinked(),
            $price->getListPrice() ? $this->clonePrice($price->getListPrice(), $delta) : null,
            $price->getPercentage(),
            $price->getRegulationPrice() ? $this->clonePrice($price->getRegulationPrice(), $delta) : null
        );
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

    /**
     * @param EntityCollection<ProductPriceEntity|PartialEntity> $rules
     *
     * @return EntityCollection<ProductPriceEntity|PartialEntity>|null
     */
    private function filterRulePrices(EntityCollection $rules, SalesChannelContext $context): ?EntityCollection
    {
        foreach ($context->getRuleIds() as $ruleId) {
            $filtered = $rules->filter(fn (Entity $price) => $ruleId === $price->get('ruleId'));

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

        $units = $this->unitRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $this->units = $units;
    }
}
