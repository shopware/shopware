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
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Extension\CollectDeliveryShippingMethodsExtension;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryProcessor::class)]
class DeliveryProcessorTest extends TestCase
{
    public function testCollectWithoutSubscriberLoadsTheContextAndOriginalDeliveryMethodsOnce(): void
    {
        $contextMethod = $this->createShippingMethod();
        $deliveryMethod = $this->createShippingMethod();
        $context = Generator::generateSalesChannelContext(shippingMethod: $contextMethod);

        $original = new Cart('original');
        $original->setDeliveries(new DeliveryCollection([
            new Delivery(
                new DeliveryPositionCollection(),
                new DeliveryDate(new \DateTime(), new \DateTime()),
                $deliveryMethod,
                new ShippingLocation(new CountryEntity(), null, null),
                new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ),
        ]));

        $repository = new StaticEntityRepository([new ShippingMethodCollection([$contextMethod, $deliveryMethod])]);
        $processor = $this->createProcessor($repository, new EventDispatcher());

        $data = new CartDataCollection();
        $processor->collect($data, $original, $context, new CartBehavior());
        $processor->collect($data, $original, $context, new CartBehavior());

        static::assertCount(1, $repository->searchedCriteria);
        $criteria = $repository->searchedCriteria[0];
        static::assertSame([$contextMethod->getId(), $deliveryMethod->getId()], $criteria->getIds());
        static::assertSame(['prices', 'deliveryTime', 'tax'], array_keys($criteria->getAssociations()));
        static::assertSame('cart::shipping-methods', $criteria->getTitle());
        static::assertSame($contextMethod, $data->get(DeliveryProcessor::buildKey($contextMethod->getId())));
        static::assertSame($deliveryMethod, $data->get(DeliveryProcessor::buildKey($deliveryMethod->getId())));
    }

    public function testCollectLoadsAShippingMethodASubscriberAddsOnce(): void
    {
        $contextMethod = $this->createShippingMethod();
        $selectedMethod = $this->createShippingMethod();
        $context = Generator::generateSalesChannelContext(shippingMethod: $contextMethod);
        $original = new Cart('original');
        $behavior = new CartBehavior();

        $published = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            CollectDeliveryShippingMethodsExtension::onPre(),
            static function (CollectDeliveryShippingMethodsExtension $extension) use (&$published, $contextMethod, $selectedMethod): void {
                $published[] = [$extension, $extension->shippingMethodIds];
                $extension->shippingMethodIds[] = $selectedMethod->getId();
                $extension->shippingMethodIds[] = $contextMethod->getId();
            }
        );

        $repository = new StaticEntityRepository([new ShippingMethodCollection([$contextMethod, $selectedMethod])]);
        $processor = $this->createProcessor($repository, $dispatcher);

        $data = new CartDataCollection();
        $processor->collect($data, $original, $context, $behavior);
        $processor->collect($data, $original, $context, $behavior);

        static::assertCount(2, $published);
        [$extension, $idsBeforeSubscriber] = $published[0];
        static::assertSame([$contextMethod->getId()], $idsBeforeSubscriber);
        static::assertSame([], $published[1][1]);
        static::assertSame($original, $extension->cart);
        static::assertSame($context, $extension->context);
        static::assertSame($behavior, $extension->behavior);

        static::assertCount(1, $repository->searchedCriteria);
        static::assertSame([$contextMethod->getId(), $selectedMethod->getId()], $repository->searchedCriteria[0]->getIds());
        static::assertSame($selectedMethod, $data->get(DeliveryProcessor::buildKey($selectedMethod->getId())));
    }

    public function testCollectStoresTheShippingMethodsAPreListenerProvides(): void
    {
        $contextMethod = $this->createShippingMethod();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            CollectDeliveryShippingMethodsExtension::onPre(),
            static function (CollectDeliveryShippingMethodsExtension $extension) use ($contextMethod): void {
                $extension->result = new ShippingMethodCollection([$contextMethod]);
                $extension->stopPropagation();
            }
        );

        $repository = StaticEntityRepository::of(ShippingMethodCollection::class);
        $processor = $this->createProcessor($repository, $dispatcher);

        $data = new CartDataCollection();
        $processor->collect($data, new Cart('original'), Generator::generateSalesChannelContext(shippingMethod: $contextMethod), new CartBehavior());

        static::assertSame([], $repository->searchedCriteria);
        static::assertSame($contextMethod, $data->get(DeliveryProcessor::buildKey($contextMethod->getId())));
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

        $processor = new DeliveryProcessor($builder, $calculator, static::createStub(EntityRepository::class), new ExtensionDispatcher(new EventDispatcher()));

        $manualShippingCosts = new CalculatedPrice(10.00, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $original = new Cart('test');
        $original->addExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS, $manualShippingCosts);

        $toCalculate = new Cart('calculate');
        $processor->process(new CartDataCollection(), $original, $toCalculate, $context, new CartBehavior());

        // the processor applied the manual shipping costs to the built delivery
        static::assertSame($manualShippingCosts, $delivery->getShippingCosts());
        static::assertNotCount(0, $toCalculate->getDeliveries());
    }

    /**
     * @param StaticEntityRepository<ShippingMethodCollection> $repository
     */
    private function createProcessor(StaticEntityRepository $repository, EventDispatcher $dispatcher): DeliveryProcessor
    {
        return new DeliveryProcessor(
            static::createStub(DeliveryBuilder::class),
            static::createStub(DeliveryCalculator::class),
            $repository,
            new ExtensionDispatcher($dispatcher)
        );
    }

    private function createShippingMethod(): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());

        return $shippingMethod;
    }
}
