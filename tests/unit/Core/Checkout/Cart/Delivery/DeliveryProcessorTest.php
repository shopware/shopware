<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\SelectedDeliveryShippingMethods;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryProcessor::class)]
class DeliveryProcessorTest extends TestCase
{
    public function testCollectShippingMethods(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->expects($this->once())
            ->method('getShippingMethod')
            ->willReturn($shippingMethod);

        $result = $this->createMock(EntitySearchResult::class);
        $result
            ->expects($this->once())
            ->method('getEntities')->willReturn(new EntityCollection([$shippingMethod]));

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')->willReturn($result);

        $processor = new DeliveryProcessor(
            static::createStub(DeliveryBuilder::class),
            static::createStub(DeliveryCalculator::class),
            $repository
        );

        $data = new CartDataCollection();
        $processor->collect($data, new Cart('test'), $context, new CartBehavior());

        static::assertInstanceOf(ShippingMethodEntity::class, $data->get($processor::buildKey($shippingMethod->getId())));
    }

    public function testProcessDeliveryCost(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $calculator = $this->createMock(DeliveryCalculator::class);
        $calculator
            ->expects($this->once())
            ->method('calculate');

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTime(), new \DateTime()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        );

        $builder = $this->createMock(DeliveryBuilder::class);
        $builder
            ->expects($this->once())
            ->method('build')
            ->willReturn(new DeliveryCollection([$delivery]));

        $processor = new DeliveryProcessor($builder, $calculator, static::createStub(EntityRepository::class));

        $manualShippingCosts = new CalculatedPrice(10.00, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $original = new Cart('test');
        $original->addExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS, $manualShippingCosts);

        $toCalculate = new Cart('calculate');
        $processor->process(new CartDataCollection(), $original, $toCalculate, $context, new CartBehavior());

        // the processor applied the manual shipping costs to the built delivery
        static::assertSame($manualShippingCosts, $delivery->getShippingCosts());
        static::assertNotCount(0, $toCalculate->getDeliveries());
    }

    public function testCollectLoadsTheSelectedShippingMethods(): void
    {
        $context = Generator::generateSalesChannelContext();
        $express = $this->createShippingMethod();

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($context->getShippingMethod()->getId()), $context->getShippingMethod());

        $original = new Cart('test');
        $original->addExtension(SelectedDeliveryShippingMethods::KEY, new SelectedDeliveryShippingMethods(['freight' => $express->getId()]));

        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($express): ShippingMethodCollection {
                static::assertSame([$express->getId()], $criteria->getIds());

                return new ShippingMethodCollection([$express]);
            },
        ]);

        $processor = new DeliveryProcessor(new DeliveryBuilder(), static::createStub(DeliveryCalculator::class), $repository);
        $processor->collect($data, $original, $context, new CartBehavior());

        static::assertSame($express, $data->get(DeliveryProcessor::buildKey($express->getId())));
    }

    public function testSelectedShippingMethodReplacesTheShippingMethodOfItsDelivery(): void
    {
        $standard = $this->createShippingMethod();
        $express = $this->createShippingMethod();
        $parcel = $this->createDelivery($standard, 'parcel');
        $freight = $this->createDelivery($standard, 'freight');

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($express->getId()), $express);

        $toCalculate = new Cart('test');
        $toCalculate->addExtension(SelectedDeliveryShippingMethods::KEY, new SelectedDeliveryShippingMethods(['freight' => $express->getId()]));

        $processor = new DeliveryProcessor(
            new StaticDeliveryBuilder(new DeliveryCollection([$parcel, $freight])),
            static::createStub(DeliveryCalculator::class),
            static::createStub(EntityRepository::class),
        );
        $processor->process($data, new Cart('test'), $toCalculate, Generator::generateSalesChannelContext(), new CartBehavior());

        static::assertSame($standard, $parcel->getShippingMethod());
        static::assertSame($express, $freight->getShippingMethod());
        static::assertSame(
            ['freight' => $express->getId()],
            $toCalculate->getExtensionOfType(SelectedDeliveryShippingMethods::KEY, SelectedDeliveryShippingMethods::class)?->getShippingMethodIds(),
        );
    }

    public function testSelectionIsDroppedWhenItsDeliveryOrShippingMethodIsGone(): void
    {
        $standard = $this->createShippingMethod();
        $express = $this->createShippingMethod();
        $parcel = $this->createDelivery($standard, 'parcel');
        $unkeyed = $this->createDelivery($standard, null);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($express->getId()), $express);

        $toCalculate = new Cart('test');
        $toCalculate->addExtension(SelectedDeliveryShippingMethods::KEY, new SelectedDeliveryShippingMethods([
            'freight' => $express->getId(),
            'parcel' => Uuid::randomHex(),
        ]));

        $processor = new DeliveryProcessor(
            new StaticDeliveryBuilder(new DeliveryCollection([$parcel, $unkeyed])),
            static::createStub(DeliveryCalculator::class),
            static::createStub(EntityRepository::class),
        );
        $processor->process($data, new Cart('test'), $toCalculate, Generator::generateSalesChannelContext(), new CartBehavior());

        static::assertSame($standard, $parcel->getShippingMethod());
        static::assertSame($standard, $unkeyed->getShippingMethod());
        static::assertFalse($toCalculate->hasExtension(SelectedDeliveryShippingMethods::KEY));
    }

    private function createShippingMethod(): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());

        return $shippingMethod;
    }

    private function createDelivery(ShippingMethodEntity $shippingMethod, ?string $deliveryKey): Delivery
    {
        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        );

        if ($deliveryKey !== null) {
            $delivery->setPayloadValue(Delivery::DELIVERY_KEY, $deliveryKey);
        }

        return $delivery;
    }
}

/**
 * @internal
 */
class StaticDeliveryBuilder extends DeliveryBuilder
{
    public function __construct(private readonly DeliveryCollection $deliveries)
    {
    }

    public function build(Cart $cart, CartDataCollection $data, SalesChannelContext $context, CartBehavior $cartBehavior): DeliveryCollection
    {
        return $this->deliveries;
    }
}
