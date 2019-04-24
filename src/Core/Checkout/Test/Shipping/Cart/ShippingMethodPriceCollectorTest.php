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
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
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

    public function testPrepare(): void
    {
        $definitions = $this->createMock(StructCollection::class);
        $cart = $this->createMock(Cart::class);
        $cartBehavior = $this->createMock(CartBehavior::class);

        $definitions->expects(static::never())->method(static::anything());
        $cart->expects(static::never())->method(static::anything());
        $cartBehavior->expects(static::never())->method(static::anything());
        $this->context->expects(static::never())->method(static::anything());

        $this->collector->prepare($definitions, $cart, $this->context, $cartBehavior);
    }

    public function testCollect(): void
    {
        $definitions = $this->createMock(StructCollection::class);
        $data = $this->createMock(StructCollection::class);
        $cart = $this->createMock(Cart::class);
        $cartBehavior = $this->createMock(CartBehavior::class);

        $definitions->expects(static::never())->method(static::anything());
        $data->expects(static::never())->method(static::anything());
        $cart->expects(static::never())->method(static::anything());
        $cartBehavior->expects(static::never())->method(static::anything());
        $this->context->expects(static::never())->method(static::anything());

        $this->collector->collect($definitions, $data, $cart, $this->context, $cartBehavior);
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

        $this->repository->expects(static::once())->method('search')->willReturn($result);
        static::assertSame(0, $this->context->getShippingMethod()->getPrices()->count());

        $this->collector->enrich($data, $cart, $this->context, $this->createMock(CartBehavior::class));

        foreach ($cart->getDeliveries() as $delivery) {
            static::assertGreaterThan(0, $delivery->getShippingMethod()->getPrices()->count());
        }

        static::assertGreaterThan(0, $this->context->getShippingMethod()->getPrices()->count());
    }

    public function testEnrichRecalculation(): void
    {
        $behavior = $this->createMock(CartBehavior::class);
        $behavior->expects(static::atLeastOnce())->method('isRecalculation')->willReturn(true);

        $data = $this->createMock(StructCollection::class);
        $cart = $this->createMock(Cart::class);

        $data->expects(static::never())->method(static::anything());
        $cart->expects(static::never())->method(static::anything());
        $this->context->expects(static::never())->method(static::anything());

        $this->collector->enrich($data, $cart, $this->context, $behavior);
    }

    public function testEnrichWithoutData(): void
    {
        $shippingMethodIds = [Uuid::randomHex(), Uuid::randomHex()];
        $cart = $this->buildTestCart($shippingMethodIds);
        $data = new StructCollection();

        $this->repository->expects(static::once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(0, new EntityCollection(), null, new Criteria(), $this->context->getContext())
            );

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
}
