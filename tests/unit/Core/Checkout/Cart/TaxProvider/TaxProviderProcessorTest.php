<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Exception\TaxProviderExceptions;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxAdjustment;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxAdjustmentCalculator;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderRegistry;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\TaxProvider\Payload\TaxProviderPayload;
use Shopware\Core\Framework\App\TaxProvider\Payload\TaxProviderPayloadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\TaxProvider\TaxProviderCollection;
use Shopware\Core\System\TaxProvider\TaxProviderDefinition;
use Shopware\Core\System\TaxProvider\TaxProviderEntity;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestConstantTaxRateProvider;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestEmptyTaxProvider;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestGenericExceptionTaxProvider;

/**
 * @package checkout
 *
 * @internal
 */
#[CoversClass(TaxProviderProcessor::class)]
class TaxProviderProcessorTest extends TestCase
{
    private IdsCollection $ids;

    private TaxAdjustment $adjustment;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->adjustment = new TaxAdjustment(
            new AmountCalculator(
                new CashRounding(),
                new PercentageTaxRuleBuilder(),
                new TaxAdjustmentCalculator()
            ),
            new CashRounding()
        );
    }

    public function testProcess(): void
    {
        $cart = $this->createCart();
        $salesChannelContext = static::createMock(SalesChannelContext::class);
        $salesChannelContext
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $taxProvider = new TaxProviderEntity();
        $taxProvider->setId(Uuid::randomHex());
        $taxProvider->setActive(true);
        $taxProvider->setPriority(1);
        $taxProvider->setIdentifier(TestConstantTaxRateProvider::class);

        $collection = new TaxProviderCollection([$taxProvider]);

        $result = new EntitySearchResult(
            TaxProviderDefinition::ENTITY_NAME,
            1,
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $taxProviderRegistry = new TaxProviderRegistry([
            new TestConstantTaxRateProvider(),
        ]);

        $repo = static::createMock(EntityRepository::class);
        $repo->method('search')->willReturn($result);

        $processor = new TaxProviderProcessor(
            $repo,
            static::createMock(LoggerInterface::class),
            $this->adjustment,
            $taxProviderRegistry,
            static::createMock(TaxProviderPayloadService::class)
        );

        $processor->process($cart, $salesChannelContext);

        $lineItem = $cart->getLineItems()->get($this->ids->get('line-item-1'));
        $delivery = $cart->getDeliveries()->first();

        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertInstanceOf(Delivery::class, $delivery);

        $lineItemPrice = $lineItem->getPrice();

        static::assertNotNull($lineItemPrice);

        $lineItemTaxes = $lineItemPrice->getCalculatedTaxes()->getElements();
        $deliveryTaxes = $delivery->getShippingCosts()->getCalculatedTaxes()->getElements();

        static::assertArrayHasKey('7', $lineItemTaxes);
        static::assertArrayHasKey('7', $deliveryTaxes);

        static::assertInstanceOf(CalculatedTax::class, $lineItemTaxes['7']);
        static::assertInstanceOf(CalculatedTax::class, $deliveryTaxes['7']);

        $lineItemTax = $lineItemTaxes['7'];
        $deliveryTax = $deliveryTaxes['7'];

        static::assertEquals(7, $lineItemTax->getTaxRate());
        static::assertEquals(7, $deliveryTax->getTaxRate());
    }

    public function testNoTaxResultsGivenThrowsException(): void
    {
        // empty data set should result in exception to prevent invalid taxes
        $taxProviderStruct = new TaxProviderResult();

        $cart = new Cart('foo');
        $salesChannelContext = static::createMock(SalesChannelContext::class);
        $salesChannelContext
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $testProvider = static::createMock(TestEmptyTaxProvider::class);
        $testProvider
            ->expects(static::once())
            ->method('provide')
            ->with($cart, $salesChannelContext)
            ->willReturn($taxProviderStruct);

        $taxProviderRegistry = static::createMock(TaxProviderRegistry::class);
        $taxProviderRegistry
            ->method('has')
            ->willReturnCallback(fn (string $identifier) => $identifier === TestEmptyTaxProvider::class);

        $taxProviderRegistry
            ->method('get')
            ->withAnyParameters()
            ->willReturnCallback(function (string $identifier) use ($testProvider) {
                if ($identifier === TestEmptyTaxProvider::class) {
                    return $testProvider;
                }

                return null;
            });

        $taxProvider = new TaxProviderEntity();
        $taxProvider->setId(Uuid::randomHex());
        $taxProvider->setActive(true);
        $taxProvider->setPriority(1);
        $taxProvider->setIdentifier(TestEmptyTaxProvider::class);

        $collection = new TaxProviderCollection([$taxProvider]);

        $result = new EntitySearchResult(
            TaxProviderDefinition::ENTITY_NAME,
            1,
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = static::createMock(EntityRepository::class);
        $repo->method('search')->willReturn($result);

        $processor = new TaxProviderProcessor(
            $repo,
            static::createMock(LoggerInterface::class),
            $this->adjustment,
            $taxProviderRegistry,
            static::createMock(TaxProviderPayloadService::class)
        );

        static::expectException(TaxProviderExceptions::class);
        static::expectExceptionMessage('There was an error while calculating taxes');

        $processor->process($cart, $salesChannelContext);
    }

    public function testGenericExceptionDoesNotInterruptTaxProcessor(): void
    {
        $cart = $this->createCart();

        $salesChannelContext = static::createMock(SalesChannelContext::class);
        $salesChannelContext
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $registry = new TaxProviderRegistry(
            [
                new TestGenericExceptionTaxProvider(),
                new TestConstantTaxRateProvider(),
            ]
        );

        $taxProvider1 = new TaxProviderEntity();
        $taxProvider1->setId(Uuid::randomHex());
        $taxProvider1->setActive(true);
        $taxProvider1->setPriority(1);
        $taxProvider1->setIdentifier(TestGenericExceptionTaxProvider::class);

        $taxProvider2 = new TaxProviderEntity();
        $taxProvider2->setId(Uuid::randomHex());
        $taxProvider2->setActive(true);
        $taxProvider2->setPriority(2);
        $taxProvider2->setIdentifier(TestConstantTaxRateProvider::class);

        $collection = new TaxProviderCollection([$taxProvider1, $taxProvider2]);

        $result = new EntitySearchResult(
            TaxProviderDefinition::ENTITY_NAME,
            2,
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = static::createMock(EntityRepository::class);
        $repo->method('search')->willReturn($result);

        $processor = new TaxProviderProcessor(
            $repo,
            static::createMock(LoggerInterface::class),
            $this->adjustment,
            $registry,
            static::createMock(TaxProviderPayloadService::class)
        );

        $processor->process($cart, $salesChannelContext);

        static::assertInstanceOf(LineItem::class, $cart->get($this->ids->get('line-item-1')));
    }

    public function testProcessorThrowsExceptionOnUnknownProvider(): void
    {
        $taxProviderRegistry = static::createMock(TaxProviderRegistry::class);
        $taxProviderRegistry
            ->method('has')
            ->willReturnCallback(fn (string $identifier) => $identifier === TestEmptyTaxProvider::class);

        $taxProviderRegistry
            ->method('get')
            ->withAnyParameters()
            ->willReturnCallback(function (string $identifier) {
                if ($identifier === TestEmptyTaxProvider::class) {
                    return new TestEmptyTaxProvider();
                }

                return null;
            });

        $taxProvider = new TaxProviderEntity();
        $taxProvider->setId(Uuid::randomHex());
        $taxProvider->setActive(true);
        $taxProvider->setPriority(1);
        $taxProvider->setIdentifier('foo_bar');

        $collection = new TaxProviderCollection([$taxProvider]);

        $result = new EntitySearchResult(
            TaxProviderDefinition::ENTITY_NAME,
            1,
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = static::createMock(EntityRepository::class);
        $repo->method('search')->willReturn($result);

        $processor = new TaxProviderProcessor(
            $repo,
            static::createMock(LoggerInterface::class),
            $this->adjustment,
            $taxProviderRegistry,
            static::createMock(TaxProviderPayloadService::class)
        );

        static::expectException(TaxProviderExceptions::class);
        static::expectExceptionMessage('There were 1 errors while fetching taxes from providers: ' . \PHP_EOL . 'Tax provider \'foo_bar\' threw an exception: No tax provider found for identifier foo_bar');

        $processor->process(new Cart('foo'), static::createMock(SalesChannelContext::class));
    }

    public function testNoProvidersAvailableWillDoNothing(): void
    {
        $cart = new Cart('foo');

        $salesChannelContext = static::createMock(SalesChannelContext::class);

        $registry = new TaxProviderRegistry([]);
        $collection = new TaxProviderCollection([]);

        $result = new EntitySearchResult(
            TaxProviderDefinition::ENTITY_NAME,
            0,
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = static::createMock(EntityRepository::class);
        $repo->method('search')->willReturn($result);

        $taxAdjuster = static::createMock(TaxAdjustment::class);
        $taxAdjuster
            ->expects(static::never())
            ->method('adjust');

        $processor = new TaxProviderProcessor(
            $repo,
            static::createMock(LoggerInterface::class),
            $taxAdjuster,
            $registry,
            static::createMock(TaxProviderPayloadService::class)
        );

        $processor->process($cart, $salesChannelContext);
    }

    public function testLoggerIsCalledOnException(): void
    {
        $cart = new Cart('foo');

        $salesChannelContext = static::createMock(SalesChannelContext::class);

        $registry = static::createMock(TaxProviderRegistry::class);
        $registry
            ->method('get')
            ->withAnyParameters()
            ->willReturnCallback(function (string $identifier) {
                if ($identifier === TestGenericExceptionTaxProvider::class) {
                    return new TestGenericExceptionTaxProvider();
                }

                return null;
            });

        $taxProvider = new TaxProviderEntity();
        $taxProvider->setId(Uuid::randomHex());
        $taxProvider->setActive(true);
        $taxProvider->setPriority(1);
        $taxProvider->setIdentifier(TestGenericExceptionTaxProvider::class);

        $collection = new TaxProviderCollection([$taxProvider]);

        $result = new EntitySearchResult(
            TaxProviderDefinition::ENTITY_NAME,
            1,
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = static::createMock(EntityRepository::class);
        $repo->method('search')->willReturn($result);

        $e = new TaxProviderExceptions();
        $e->add(TestGenericExceptionTaxProvider::class, new \Exception('Test exception'));

        $logger = static::createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('error')
            ->with('There were 1 errors while fetching taxes from providers: ' . \PHP_EOL . 'Tax provider \'Shopware\\Tests\\Unit\\Core\\Checkout\\Cart\\TaxProvider\\_fixtures\\TestGenericExceptionTaxProvider\' threw an exception: Test exception' . \PHP_EOL);

        $processor = new TaxProviderProcessor(
            $repo,
            $logger,
            static::createMock(TaxAdjustment::class),
            $registry,
            static::createMock(TaxProviderPayloadService::class)
        );

        static::expectException(TaxProviderExceptions::class);

        $processor->process($cart, $salesChannelContext);
    }

    public function testAppProviderIsCalled(): void
    {
        $cart = $this->createCart();
        $salesChannelContext = static::createMock(SalesChannelContext::class);
        $salesChannelContext
            ->method('getTotalRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $taxProvider = new TaxProviderEntity();
        $taxProvider->setId(Uuid::randomHex());
        $taxProvider->setActive(true);
        $taxProvider->setPriority(1);
        $taxProvider->setIdentifier(TestConstantTaxRateProvider::class);
        $taxProvider->setApp(new AppEntity());
        $taxProvider->setProcessUrl('https://example.com');

        $collection = new TaxProviderCollection([$taxProvider]);

        $result = new EntitySearchResult(
            TaxProviderDefinition::ENTITY_NAME,
            1,
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $taxProviderRegistry = new TaxProviderRegistry([
            new TestConstantTaxRateProvider(),
        ]);

        $repo = static::createMock(EntityRepository::class);
        $repo->method('search')->willReturn($result);

        $taxes = new CalculatedTaxCollection([
            new CalculatedTax(19, 19, 100),
        ]);

        $taxProviderPayloadService = static::createMock(TaxProviderPayloadService::class);
        $taxProviderPayloadService
            ->expects(static::once())
            ->method('request')
            ->with(
                'https://example.com',
                static::isInstanceOf(TaxProviderPayload::class),
                static::isInstanceOf(AppEntity::class),
                $salesChannelContext->getContext()
            )
            ->willReturn(new TaxProviderResult([$this->ids->get('line-item-1') => $taxes]));

        $processor = new TaxProviderProcessor(
            $repo,
            static::createMock(LoggerInterface::class),
            $this->adjustment,
            $taxProviderRegistry,
            $taxProviderPayloadService
        );

        $processor->process($cart, $salesChannelContext);
    }

    public function testTaxProcessorNotProcessingOnStateTaxFree(): void
    {
        $repo = static::createMock(EntityRepository::class);
        $repo
            ->expects(static::never())
            ->method('search');

        $logger = static::createMock(LoggerInterface::class);
        $logger
            ->expects(static::never())
            ->method('error');

        $taxAdjuster = static::createMock(TaxAdjustment::class);
        $taxAdjuster
            ->expects(static::never())
            ->method('adjust');

        $registry = static::createMock(TaxProviderRegistry::class);
        $registry
            ->expects(static::never())
            ->method('get');

        $payloadService = static::createMock(TaxProviderPayloadService::class);
        $payloadService
            ->expects(static::never())
            ->method('request');

        $processor = new TaxProviderProcessor(
            $repo,
            $logger,
            $taxAdjuster,
            $registry,
            $payloadService
        );

        $cart = new Cart('foo');
        $context = static::createMock(SalesChannelContext::class);
        $context
            ->method('getTaxState')
            ->willReturn(CartPrice::TAX_STATE_FREE);

        $processor->process($cart, $context);
    }

    private function createCart(): Cart
    {
        $cart = new Cart('test');

        $lineItem = new LineItem(
            $this->ids->get('line-item-1'),
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $this->ids->get('line-item-1'),
            1,
        );

        $taxes = new CalculatedTaxCollection([
            new CalculatedTax(
                19,
                19,
                100
            ),
        ]);

        $price = new CalculatedPrice(
            100,
            100,
            $taxes,
            new TaxRuleCollection(),
            1
        );

        $price->assign(['calculatedTaxes' => $taxes]);
        $lineItem->setPrice($price);

        $cart->add($lineItem);

        $deliveries = new DeliveryCollection([
            new Delivery(
                new DeliveryPositionCollection([
                    new DeliveryPosition(
                        $this->ids->get('delivery-position-1'),
                        $lineItem,
                        1,
                        $price,
                        static::createMock(DeliveryDate::class)
                    ),
                ]),
                static::createMock(DeliveryDate::class),
                static::createMock(ShippingMethodEntity::class),
                static::createMock(ShippingLocation::class),
                $price
            ),
        ]);

        $cart->addDeliveries($deliveries);

        return $cart;
    }
}
