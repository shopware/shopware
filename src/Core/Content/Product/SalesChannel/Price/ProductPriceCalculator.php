<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Content\Product\Params\StorePriceParams;
use Shopware\Core\Content\Product\Params\QuantityPriceId;
use Shopware\Core\Content\Product\Params\CheckoutPriceParams;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
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
     */
    public function __construct(
        private readonly EntityRepository $unitRepository,
        private readonly QuantityPriceCalculator $calculator,
        private readonly Connection $connection
    ) {
    }

    public function getDecorated(): AbstractProductPriceCalculator
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Called inside the checkout process to get the real live price for products with a specific quantity.
     *
     * @return array<string, CalculatedPrice>
     */
    public function checkout(CheckoutPriceParams $params): array
    {
        $ids = array_map(function ($id) {
            return $id->id;
        }, $params->ids);

        $prices = $this->fetch(productIds: $ids, context: $params->context);

        $mapped = [];
        foreach ($params->ids as $key => $id) {
            if (!isset($prices[$id->id])) {
                if (Feature::isActive('v6.7.0.0')) {
                    throw new \RuntimeException('No price found for product with id ' . $id->id);
                }

                continue;
            }

            $price = $prices[$id->id];

            $price = $this->findQuantity($price, $id->quantity);

            $calculated = $this->calculator->calculate(
                definition: $this->definition($price, $params->context),
                context: $params->context
            );

            $mapped[$key] = $calculated;
        }

        return $mapped;
    }

    /**
     * Called from the store apis to get a price for the store listings, boxes and detail pages.
     * @return array<string|int, CalculatedPrice[]>
     */
    public function store(StorePriceParams $params): array
    {
        $prices = $this->fetch($params->ids, $params->context);

        $mapped = [];

        foreach ($params->ids as $key => $id) {
            if (!isset($prices[$id])) {
                if (Feature::isActive('v6.7.0.0')) {
                    throw new \RuntimeException('No price found for product with id ' . $id);
                }

                continue;
            }

            $price = $prices[$id];

            $matrix = [];
            foreach ($price as $item) {
                $definition = $this->definition($item, $params->context);

                $definition->setQuantity((int) $item['quantity_start']);

                $calculated = $this->calculator->calculate(
                    definition: $definition,
                    context: $params->context
                );

                $matrix[] = $calculated;
            }

            $mapped[$key] = $matrix;
        }

        return $mapped;
    }

    /**
     * @param Entity[] $products
     */
    public function calculate(iterable $products, SalesChannelContext $context): void
    {
        foreach ($products as $product) {
            $product->assign([
                'calculatedPrices' => new CalculatedPriceCollection(),
                'calculatedCheapestPrice' => new CalculatedCheapestPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ]);
        }

        $ids = [];
        foreach ($products as $product) {
            $ids[$product->getUniqueIdentifier()] = $product->getUniqueIdentifier();
        }

        $prices = $this->store(new StorePriceParams(ids: $ids, context: $context));

        $fallback = [];
        foreach ($products as $product) {
            if (!isset($prices[$product->getUniqueIdentifier()])) {
                $fallback[] = $product;

                continue;
            }

            $price = $prices[$product->getUniqueIdentifier()];

            $cheapest = CheapestPrice::createFrom($price[0]);

            $product->assign([
                'calculated' => $price,
                'calculatedPrice' => $price[0],
                'calculatedCheapestPrice' => $cheapest
            ]);
        }

        if (empty($fallback)) {
            return;
        }

        Feature::throwException('v6.7.0.0', 'Fallback pricing will be removed in next major, migrate your prices');

        $units = $this->getUnits($context);

        /** @var Entity $product */
        foreach ($fallback as $product) {
            $this->calculatePrice($product, $context, $units);
            $this->calculateAdvancePrices($product, $context, $units);
            $this->calculateCheapestPrice($product, $context, $units);
        }
    }

    public function reset(): void
    {
        $this->units = null;
    }

    private function fetch(array $productIds, SalesChannelContext $context): array
    {
        if (empty($productIds)) {
            return [];
        }

        $query = $this->connection->createQueryBuilder();

        $where[] = '(
            product_pricing.customer_group_id = :customerGroupId AND
            product_pricing.sales_channel_id = :salesChannelId AND
            product_pricing.country_id = :countryId
        )';

        $where[] = '(
            product_pricing.customer_group_id = :customerGroupId AND
            product_pricing.sales_channel_id = :salesChannelId AND
            product_pricing.country_id IS NULL
        )';

        $where[] = '(
            product_pricing.customer_group_id = :customerGroupId AND
            product_pricing.sales_channel_id IS NULL AND
            product_pricing.country_id IS NULL
        )';

        $where[] = '(
            product_pricing.customer_group_id IS NULL AND
            product_pricing.sales_channel_id IS NULL AND
            product_pricing.country_id IS NULL
        )';

        $query->select([
            'CONCAT(LOWER(HEX(product.id)), "-", product_pricing.`precision`) as array_key',
            'LOWER(HEX(resolved.tax_id)) as tax_id',
            'LOWER(HEX(resolved.unit_id)) AS unit_id',
            'resolved.purchase_unit',
            'resolved.reference_unit',
            'LOWER(HEX(product_pricing.product_id)) as product_id',
            'LOWER(HEX(product_pricing.customer_group_id)) as customer_group_id',
            'LOWER(HEX(product_pricing.sales_channel_id)) as sales_channel_id',
            'LOWER(HEX(product_pricing.country_id)) as country_id',
            'product_pricing.quantity_start',
            'product_pricing.quantity_end',
            'product_pricing.price',
            'product_pricing.discount',
        ]);

        $query->from('product_pricing');
        $query->innerJoin('product_pricing', 'product', 'product', 'product.pricing = product_pricing.product_id AND product.version_id = product_pricing.product_version_id');
        $query->leftJoin('product', 'product', 'resolved', 'IFNULL(product.parent_id, product.id) = resolved.id');

        $query->where(implode(' OR ', $where));
        $query->andWhere('product.id IN (:ids)');

        $query->addOrderBy('product_pricing.product_id', 'DESC');
        $query->addOrderBy('product_pricing.`precision`', 'DESC');
        $query->addOrderBy('product_pricing.quantity_end', 'DESC');

        $query->setParameter('ids', Uuid::fromHexToBytesList($productIds), ArrayParameterType::BINARY);
        $query->setParameter('customerGroupId', Uuid::fromHexToBytes($context->getCurrentCustomerGroup()->getId()));
        $query->setParameter('salesChannelId', Uuid::fromHexToBytes($context->getSalesChannel()->getId()));
        $query->setParameter('countryId', Uuid::fromHexToBytes($context->getShippingLocation()->getCountry()->getId()));

        $data = $query->executeQuery()->fetchAllAssociative();

        $data = FetchModeHelper::group($data);

        $selected = $this->select($data, $productIds);

        return $this->discount($data, $selected);
    }

    private function calculatePrice(Entity $product, SalesChannelContext $context, UnitCollection $units): void
    {
        $price = $product->get('price');
        $taxId = $product->get('taxId');

        if ($price === null || $taxId === null) {
            return;
        }
        $reference = ReferencePriceDto::createFromEntity($product);

        $definition = $this->buildDefinition($product->get('taxId'), $price, $context, $units, $reference);

        $price = $this->calculator->calculate($definition, $context);

        $product->assign([
            'calculatedPrice' => $price,
        ]);
    }

    private function calculateAdvancePrices(Entity $product, SalesChannelContext $context, UnitCollection $units): void
    {
        if (Feature::isActive('cache_rework')) {
            $product->assign(['calculatedPrices' => new CalculatedPriceCollection()]);

            return;
        }
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

            $definition = $this->buildDefinition($product->get('taxId'), $price->getPrice(), $context, $units, $reference, $quantity);

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

        if (!$cheapest instanceof CheapestPrice || Feature::isActive('CACHE_REWORK')) {
            $price = $product->get('price');
            if ($price === null) {
                return;
            }

            $reference = ReferencePriceDto::createFromEntity($product);

            $definition = $this->buildDefinition($product->get('taxId'), $price, $context, $units, $reference);

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

        $definition = $this->buildDefinition($product->get('taxId'), $cheapest->getPrice(), $context, $units, $reference);

        $calculated = CalculatedCheapestPrice::createFrom(
            $this->calculator->calculate($definition, $context)
        );
        $calculated->setVariantId($cheapest->getVariantId());

        $calculated->setHasRange($cheapest->hasRange());

        $product->assign(['calculatedCheapestPrice' => $calculated]);
    }

    private function buildDefinition(
        string $taxId,
        PriceCollection $prices,
        SalesChannelContext $context,
        UnitCollection $units,
        ReferencePriceDto $reference,
        int $quantity = 1
    ): QuantityPriceDefinition {
        $price = $this->getPriceValue($prices, $context);

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
        if (Feature::isActive('cache_rework')) {
            return new ProductPriceCollection();
        }

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

    private function select(array $data, array $productIds): array
    {
        $selected = [];
        foreach ($productIds as $id) {
            if (!isset($data[$id . '-0'])) {
                // with next major, this is necessary
                if (Feature::isActive('v6.7.0.0')) {
                    throw new \RuntimeException('No price found for product with id ' . $id);
                }

                continue;
            }

            for ($i = 3; $i >= 0; --$i) {
                $key = $id . '-' . $i;

                if (isset($data[$key])) {
                    $selected[$id] = $data[$key];

                    break;
                }
            }
        }

        foreach ($selected as &$item) {
            foreach ($item as &$row) {
                if ($row['price'] !== null) {
                    $row['price'] = json_decode($row['price'], true);
                }
            }
        }

        return $selected;
    }

    private function discount(array $data, array $selected): array
    {
        $mapped = [];
        foreach ($selected as $id => $price) {
            // discounts can only have one row
            if (\count($price) !== 1) {
                $mapped[$id] = $price;

                continue;
            }

            $discount = $price[0]['discount'] ?? null;

            if ($discount === null) {
                $mapped[$id] = $price;

                continue;
            }

            $discount = (float) $discount;

            if (!isset($data[$id . '-0'])) {
                throw new \RuntimeException('No default price found for product with id ' . $id);
            }
            $default = $data[$id . '-0'];

            foreach ($default as &$row) {
                $row['price'] = json_decode($row['price'], true);
                foreach ($row['price'] as &$p) {
                    $p['gross'] = $p['gross'] - ($p['gross'] / 100 * $discount);
                    $p['net'] = $p['net'] - ($p['net'] / 100 * $discount);
                }
            }

            $mapped[$id] = $default;
        }

        return $mapped;
    }

    private function find(array $price, string $currencyId): ?array
    {
        foreach ($price as $value) {
            if ($value['currencyId'] === $currencyId) {
                return $value;
            }
        }

        return null;
    }

    private function taxed(array $price, SalesChannelContext $context)
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return $price['gross'];
        }

        return $price['net'];
    }

    private function findQuantity(array $price, int $quantity): array
    {
        foreach ($price as $value) {
            if ($value['quantity_start'] > $quantity) {
                continue;
            }
            if ($value['quantity_end'] === null) {
                return $value;
            }
            if ($value['quantity_end'] >= $quantity) {
                return $value;
            }
        }

        throw new \RuntimeException('No price found for quantity ' . $quantity);
    }

    private function definition(array $price, SalesChannelContext $context): QuantityPriceDefinition
    {
        $current = $this->find($price['price'], $context->getCurrencyId());

        $current = $current ?? $this->find($price['price'], Defaults::CURRENCY);

        $taxed = $this->taxed($current, $context);

        if ($current['currencyId'] !== $context->getCurrencyId()) {
            $taxed *= $context->getContext()->getCurrencyFactor();
        }

        $definition = new QuantityPriceDefinition(
            price: $taxed,
            taxRules: $context->buildTaxRules($price['tax_id'])
        );

        $definition->setReferencePriceDefinition(
            $this->reference($price, $context)
        );

        $definition->setListPrice(
            $this->listPrice($current, $context)
        );

        $definition->setRegulationPrice(
            $this->regulation($price, $context)
        );

        return $definition;
    }

    private function reference(array $price, SalesChannelContext $context): ?ReferencePriceDefinition
    {
        $purchase = $price['purchase_unit'];
        $unitId = $price['unit_id'];
        $reference = $price['reference_unit'];

        if ($purchase === null || $reference === null || $unitId === null) {
            return null;
        }

        if ($purchase <= 0 || $reference <= 0 || $purchase === $reference) {
            return null;
        }

        $unit = $this->getUnits($context)->get($unitId);

        if ($unit === null) {
            return null;
        }

        return new ReferencePriceDefinition(
            purchaseUnit: $purchase,
            referenceUnit: $reference,
            unitName: $unit->getTranslation('name')
        );
    }

    private function listPrice(array $price, SalesChannelContext $context): ?float
    {
        $listPrice = $price['listPrice'] ?? null;

        if ($listPrice === null) {
            return null;
        }

        $taxed = $this->taxed($listPrice, $context);

        if ($price['currencyId'] !== $context->getCurrencyId()) {
            $taxed *= $context->getContext()->getCurrencyFactor();
        }

        return $taxed;
    }

    private function regulation(array $price, SalesChannelContext $context): ?float
    {
        $regulationPrice = $price['regulationPrice'] ?? null;

        if ($regulationPrice === null) {
            return null;
        }

        $taxed = $this->taxed($regulationPrice, $context);

        if ($price['currencyId'] !== $context->getCurrencyId()) {
            $taxed *= $context->getContext()->getCurrencyFactor();
        }

        return $taxed;
    }
}
