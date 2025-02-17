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
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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
            ->expects(static::once())
            ->method('getShippingMethod')
            ->willReturn($shippingMethod);

        $result = $this->createMock(EntitySearchResult::class);
        $result
            ->expects(static::once())
            ->method('has')->with($shippingMethod->getId())->willReturn(true);
        $result
            ->expects(static::once())
            ->method('get')->with($shippingMethod->getId())->willReturn($shippingMethod);

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects(static::once())
            ->method('search')->willReturn($result);

        $processor = new DeliveryProcessor(
            $this->createMock(DeliveryBuilder::class),
            $this->createMock(DeliveryCalculator::class),
            $repository
        );

        $data = new CartDataCollection();
        $processor->collect($data, new Cart('test'), $context, new CartBehavior());

        static::assertInstanceOf(ShippingMethodEntity::class, $data->get($processor::buildKey($shippingMethod->getId())));
    }

    public function testProcessDeliveryCost(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $calculator = $this->createMock(DeliveryCalculator::class);
        $calculator
            ->expects(static::once())
            ->method('calculate');

        $delivery = $this->getMockBuilder(Delivery::class)
            ->disableOriginalConstructor()
            ->getMock();

        $newCosts = null;
        $delivery
            ->expects(static::atLeastOnce())
            ->method('setShippingCosts')
            ->willReturnCallback(function ($costsParameter) use (&$newCosts): void {
                $newCosts = $costsParameter;
            });

        $builder = $this->createMock(DeliveryBuilder::class);
        $builder
            ->expects(static::once())
            ->method('build')
            ->willReturn(new DeliveryCollection([$delivery]));

        $processor = new DeliveryProcessor($builder, $calculator, $this->createMock(EntityRepository::class));

        $original = new Cart('test');
        $original->addExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS, new CalculatedPrice(10.00, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $toCalculate = new Cart('calculate');
        $processor->process(new CartDataCollection(), $original, $toCalculate, $context, new CartBehavior());

        static::assertInstanceOf(CalculatedPrice::class, $newCosts);
        static::assertNotEmpty($toCalculate->getDeliveries());
    }
}
