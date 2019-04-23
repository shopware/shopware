<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Shipping\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Checkout\Shipping\Cart\ShippingMethodPriceCollector;
use Shopware\Core\Checkout\Shipping\Cart\ShippingMethodPriceFetchDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ShippingMethodPriceCollectorTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var ShippingMethodPriceCollector
     */
    private $collector;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|SalesChannelContext
     */
    private $context;

    public function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepositoryInterface::class);
        $this->collector = new ShippingMethodPriceCollector($this->repository);

        $this->context = $this->createMock(SalesChannelContext::class);
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $this->context->method('getShippingMethod')->willReturn($shippingMethod);
    }

    public function testPrepareWithEmptyRules(): void
    {
        $definitions = new StructCollection();
        $cart = $this->buildTestCart([]);

        $this->collector->prepare($definitions, $cart, $this->context, $this->createMock(CartBehavior::class));

        static::assertSame(0, $definitions->count());
    }

    public function testPrepareWithEmptyDeliveries(): void
    {
        $definitions = new StructCollection();
        $cart = $this->buildTestCart([]);
        $ruleId = Uuid::randomHex();
        $this->context->expects(static::once())->method('getRuleIds')->willReturn([$ruleId]);
        $this->collector->prepare($definitions, $cart, $this->context, $this->createMock(CartBehavior::class));

        static::assertSame(1, $definitions->count());
        static::assertSame([$this->context->getShippingMethod()->getId()], $definitions->get(ShippingMethodPriceCollector::DATA_KEY)->getShippingMethodIds());
    }

    public function testRecalculationPrepareWithEmptyDeliveries(): void
    {
        $definitions = new StructCollection();
        $cart = $this->buildTestCart([]);
        $ruleId = Uuid::randomHex();
        $this->context->expects(static::once())->method('getRuleIds')->willReturn([$ruleId]);
        $behavior = $this->createMock(CartBehavior::class);
        $behavior->expects(static::once())->method('isRecalculation')->willReturn(true);
        $this->collector->prepare($definitions, $cart, $this->context, $behavior);

        static::assertSame(0, $definitions->count());
    }

    public function testPrepareWithSingleDeliveries(): void
    {
        $definitions = new StructCollection();
        $ruleId = Uuid::randomHex();
        $cart = $this->buildTestCart([Uuid::randomHex()]);
        $this->context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([$ruleId]);

        $this->collector->prepare($definitions, $cart, $this->context, $this->createMock(CartBehavior::class));

        static::assertSame(1, $definitions->count());
        static::assertInstanceOf(ShippingMethodPriceFetchDefinition::class, $definitions->first());
        static::assertSame([$ruleId], $definitions->first()->getRuleIds());
    }

    public function testPrepareWithLoadedPrices(): void
    {
        $definitions = new StructCollection();
        $shippingMethodIds = [Uuid::randomHex(), Uuid::randomHex()];
        $ruleIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $cart = $this->buildTestCart($shippingMethodIds, $ruleIds);
        $this->context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn($ruleIds);

        foreach ($cart->getDeliveries() as $delivery) {
            static::assertCount(3, $delivery->getShippingMethod()->getPrices());
        }

        $this->collector->prepare($definitions, $cart, $this->context, $this->createMock(CartBehavior::class));

        foreach ($cart->getDeliveries() as $delivery) {
            static::assertCount(3, $delivery->getShippingMethod()->getPrices());
            static::assertSame(
                $ruleIds, array_values(
                    $delivery->getShippingMethod()->getPrices()->map(
                        function ($price) {
                            return $price->getRuleId();
                        }
                    )
                )
            );
        }

        static::assertSame(1, $definitions->count());
        static::assertInstanceOf(ShippingMethodPriceFetchDefinition::class, $definitions->first());
        static::assertSame($ruleIds, $definitions->first()->getRuleIds());
    }

    public function testPrepareWithLoadedPricesRemovesInvalid(): void
    {
        $definitions = new StructCollection();
        $ruleIds = [Uuid::randomHex(), Uuid::randomHex()];

        $cart = $this->buildTestCart(
            [Uuid::randomHex(), Uuid::randomHex()], array_merge($ruleIds, [Uuid::randomHex(), Uuid::randomHex()])
        );
        $this->context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn($ruleIds);

        foreach ($cart->getDeliveries() as $delivery) {
            static::assertSame(4, $delivery->getShippingMethod()->getPrices()->count());
        }

        $this->collector->prepare($definitions, $cart, $this->context, $this->createMock(CartBehavior::class));

        foreach ($cart->getDeliveries() as $delivery) {
            static::assertSame(2, $delivery->getShippingMethod()->getPrices()->count());
            static::assertSame(
                $ruleIds, array_values(
                    $delivery->getShippingMethod()->getPrices()->map(
                        function ($price) {
                            return $price->getRuleId();
                        }
                    )
                )
            );
        }

        static::assertCount(1, $definitions->getElements());
        static::assertInstanceOf(ShippingMethodPriceFetchDefinition::class, $definitions->first());
        static::assertSame($ruleIds, $definitions->first()->getRuleIds());
    }

    public function testCollectWithoutPriceFetchDefinition(): void
    {
        $definitions = new StructCollection();
        $data = new StructCollection();

        $cart = $this->buildTestCart([Uuid::randomHex()]);

        $this->collector->collect($definitions, $data, $cart, $this->context, $this->createMock(CartBehavior::class));

        static::assertSame(0, $data->count());
    }

    public function testCollectWithPriceFetchDefinition(): void
    {
        $ruleIds = [Uuid::randomHex(), Uuid::randomHex()];
        $shippingMethodIds = [Uuid::randomHex()];
        $cart = $this->buildTestCart([Uuid::randomHex()]);

        $definitions = new StructCollection([new ShippingMethodPriceFetchDefinition($ruleIds, $shippingMethodIds)]);
        $data = new StructCollection();

        $collection = new ShippingMethodPriceCollection();

        $this->repository->expects(static::once())->method('search')->willReturnCallback(
            function ($criteria) use ($collection) {
                return new EntitySearchResult(
                    0,
                    $collection,
                    new AggregationResultCollection(),
                    $criteria,
                    $this->createMock(Context::class)
                );
            }
        );

        $this->collector->collect($definitions, $data, $cart, $this->context, $this->createMock(CartBehavior::class));

        $this->checkCollectResult($data, $ruleIds, $shippingMethodIds, $collection);
    }

    public function testCollectWithMultiplePriceFetchDefinition(): void
    {
        $ruleIds1 = [Uuid::randomHex(), Uuid::randomHex()];
        $ruleIds2 = [Uuid::randomHex(), $ruleIds1[0]];
        $ruleIds = [];
        array_push($ruleIds, ...$ruleIds1, ...$ruleIds2);
        $shippingMethodIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $cart = $this->buildTestCart($shippingMethodIds);
        $definitions = new StructCollection(
            [
                new ShippingMethodPriceFetchDefinition($ruleIds1, $shippingMethodIds),
                new ShippingMethodPriceFetchDefinition($ruleIds2, [$shippingMethodIds[0]]),
            ]
        );
        $data = new StructCollection();

        $collection = new ShippingMethodPriceCollection();

        $this->repository->expects(static::once())->method('search')->willReturnCallback(
            function ($criteria) use ($collection) {
                return new EntitySearchResult(
                    0,
                    $collection,
                    new AggregationResultCollection(),
                    $criteria,
                    $this->createMock(Context::class)
                );
            }
        );

        $this->collector->collect($definitions, $data, $cart, $this->context, $this->createMock(CartBehavior::class));

        $this->checkCollectResult($data, $ruleIds, $shippingMethodIds, $collection);
    }

    public function testEnrich(): void
    {
        $shippingMethodIds = [Uuid::randomHex(), Uuid::randomHex(), $this->context->getShippingMethod()->getId()];
        $ruleIds = [Uuid::randomHex(), Uuid::randomHex()];
        $cart = $this->buildTestCart($shippingMethodIds);
        $data = new StructCollection();

        $prices = new ShippingMethodPriceCollection();
        foreach ($shippingMethodIds as $shippingMethodId) {
            $shippingPrices = $this->buildTestPrices($shippingMethodId, $ruleIds);
            foreach ($shippingPrices as $shippingPrice) {
                $prices->add($shippingPrice);
            }
        }

        $result = new EntitySearchResult(
            2, $prices, new AggregationResultCollection(), new Criteria(),
            $this->createMock(Context::class)
        );

        $data->set(ShippingMethodPriceCollector::DATA_KEY, $result);
        static::assertSame(0, $this->context->getShippingMethod()->getPrices()->count());

        $this->collector->enrich($data, $cart, $this->context, $this->createMock(CartBehavior::class));

        foreach ($cart->getDeliveries() as $delivery) {
            static::assertGreaterThan(0, $delivery->getShippingMethod()->getPrices()->count());
        }

        static::assertGreaterThan(0, $this->context->getShippingMethod()->getPrices()->count());
    }

    public function testEnrichRecalculation(): void
    {
        $shippingMethodIds = [Uuid::randomHex(), Uuid::randomHex(), $this->context->getShippingMethod()->getId()];
        $ruleIds = [Uuid::randomHex(), Uuid::randomHex()];
        $cart = $this->buildTestCart($shippingMethodIds);
        $data = new StructCollection();

        $prices = new ShippingMethodPriceCollection();
        foreach ($shippingMethodIds as $shippingMethodId) {
            $shippingPrices = $this->buildTestPrices($shippingMethodId, $ruleIds);
            foreach ($shippingPrices as $shippingPrice) {
                $prices->add($shippingPrice);
            }
        }

        $result = new EntitySearchResult(
            2, $prices, new AggregationResultCollection(), new Criteria(),
            $this->createMock(Context::class)
        );

        $data->set(ShippingMethodPriceCollector::DATA_KEY, $result);
        static::assertSame(0, $this->context->getShippingMethod()->getPrices()->count());

        $behavior = $this->createMock(CartBehavior::class);
        $behavior->expects(static::atLeastOnce())->method('isRecalculation')->willReturn(true);

        $this->collector->enrich($data, $cart, $this->context, $behavior);

        foreach ($cart->getDeliveries() as $delivery) {
            static::assertGreaterThan(0, $delivery->getShippingMethod()->getPrices()->count());
        }

        static::assertSame(0, $this->context->getShippingMethod()->getPrices()->count());
    }

    public function testEnrichWithoutData(): void
    {
        $shippingMethodIds = [Uuid::randomHex(), Uuid::randomHex()];
        $cart = $this->buildTestCart($shippingMethodIds);
        $data = new StructCollection();

        $this->collector->enrich($data, $cart, $this->context, $this->createMock(CartBehavior::class));

        foreach ($cart->getDeliveries() as $delivery) {
            static::assertSame(0, $delivery->getShippingMethod()->getPrices()->count());
        }
    }

    private function buildTestCart(array $shippingMethodIds, array $ruleIds = []): Cart
    {
        $cart = new Cart('test', 'test-token');
        $deliveries = new DeliveryCollection();
        $deliveryTime = new DeliveryTimeEntity();
        $deliveryTime->setId(Uuid::randomHex());
        $deliveryTime->setMin(1);
        $deliveryTime->setMax(1);
        $deliveryTime->setName('Test');
        foreach ($shippingMethodIds as $shippingMethodId) {
            $shippingMethod = new ShippingMethodEntity();
            $shippingMethod->setId($shippingMethodId);

            if ($ruleIds) {
                $prices = $this->buildTestPrices($shippingMethodId, $ruleIds);
                $shippingMethod->setPrices($prices);
            }

            $shippingMethod->setDeliveryTime($deliveryTime);
            $deliveries->add(
                new Delivery(
                    $this->createMock(DeliveryPositionCollection::class),
                    new DeliveryDate(new \DateTime(), new \DateTime()),
                    $shippingMethod,
                    $this->createMock(ShippingLocation::class),
                    $this->createMock(CalculatedPrice::class)
                )
            );
        }
        $cart->setDeliveries($deliveries);

        return $cart;
    }

    private function buildTestPrices(string $shippingMethodId, array $ruleIds): ShippingMethodPriceCollection
    {
        $prices = new ShippingMethodPriceCollection();
        foreach ($ruleIds as $ruleId) {
            $price = new ShippingMethodPriceEntity();
            $price->setUniqueIdentifier(Uuid::randomHex());
            $price->setRuleId($ruleId);
            $price->setShippingMethodId($shippingMethodId);
            $prices->add($price);
        }

        return $prices;
    }

    /**
     * @param string[] $ruleIds
     * @param string[] $shippingMethodIds
     */
    private function checkCollectResult(
        StructCollection $data,
        array $ruleIds,
        array $shippingMethodIds,
        ShippingMethodPriceCollection $collection
    ): void {
        static::assertSame(1, $data->count());
        static::assertTrue($data->has(ShippingMethodPriceCollector::DATA_KEY));
        /** @var EntitySearchResult $result */
        $result = $data->get(ShippingMethodPriceCollector::DATA_KEY);
        static::assertInstanceOf(EntitySearchResult::class, $data->first());

        static::assertCount(2, $result->getCriteria()->getFilters());
        /** @var MultiFilter $multiFilter */
        $multiFilter = $result->getCriteria()->getFilters()[0];
        static::assertInstanceOf(MultiFilter::class, $multiFilter);
        static::assertCount(2, $multiFilter->getQueries());
        static::assertInstanceOf(EqualsFilter::class, $multiFilter->getQueries()[0]);
        static::assertSame('ruleId', $multiFilter->getQueries()[0]->getField());
        static::assertNull($multiFilter->getQueries()[0]->getValue());
        static::assertInstanceOf(EqualsAnyFilter::class, $multiFilter->getQueries()[1]);
        static::assertSame('ruleId', $multiFilter->getQueries()[1]->getField());
        static::assertSame($ruleIds, $multiFilter->getQueries()[1]->getValue());
        /** @var EqualsAnyFilter $shippingMethodFilter */
        $shippingMethodFilter = $result->getCriteria()->getFilters()[1];
        static::assertInstanceOf(EqualsAnyFilter::class, $shippingMethodFilter);
        static::assertSame(array_merge([$this->context->getShippingMethod()->getId()], $shippingMethodIds), array_unique($shippingMethodFilter->getValue()));

        static::assertSame($collection, $result->getEntities());
    }
}
