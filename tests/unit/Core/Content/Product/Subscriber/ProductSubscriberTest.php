<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementEnum;
use Shopware\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Shopware\Core\Content\MeasurementSystem\Unit\AbstractMeasurementUnitConverter;
use Shopware\Core\Content\MeasurementSystem\Unit\ConvertedUnit;
use Shopware\Core\Content\MeasurementSystem\Unit\ConvertedUnitSet;
use Shopware\Core\Content\Product\AbstractPropertyGroupSorter;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\IsNewDetector;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\ProductVariationBuilder;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\Subscriber\ProductSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(ProductSubscriber::class)]
class ProductSubscriberTest extends TestCase
{
    private const CONFIG = ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT;

    #[DataProvider('resolveCmsPageIdProviderWithLoadedEventProvider')]
    public function testResolveCmsPageIdProviderWithLoadedEvent(Entity $entity, SystemConfigService $config, ?string $expected): void
    {
        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            $config,
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $this->createMock(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            $this->createMock(Connection::class)
        );

        /** @var EntityLoadedEvent<ProductEntity|PartialEntity> $event */
        $event = new EntityLoadedEvent(
            $this->createMock(ProductDefinition::class),
            [$entity],
            Context::createDefaultContext()
        );

        $subscriber->loaded($event);

        static::assertSame($expected, $entity->get('cmsPageId'));
    }

    #[DataProvider('resolveCmsPageIdProviderWithSalesChannelLoadedEventProvider')]
    public function testResolveCmsPageIdProviderWithSalesChannelLoadedEvent(Entity $entity, SystemConfigService $config, ?string $expected): void
    {
        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            $config,
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $this->createMock(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            $this->createMock(Connection::class)
        );

        /** @var SalesChannelEntityLoadedEvent<ProductEntity|PartialEntity> $event */
        $event = new SalesChannelEntityLoadedEvent(
            $this->createMock(SalesChannelProductDefinition::class),
            [$entity],
            $this->createMock(SalesChannelContext::class)
        );

        $subscriber->salesChannelLoaded($event);

        static::assertSame($expected, $entity->get('cmsPageId'));
    }

    public static function resolveCmsPageIdProviderWithLoadedEventProvider(): \Generator
    {
        yield 'It does not set cms page id if no product entity given' => [
            (new CategoryEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => 'own-id']),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'own-id',
        ];

        yield 'It does not set cms page id if already given' => [
            (new ProductEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => 'own-id']),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'own-id',
        ];

        yield 'It does not set if no default is given' => [
            (new ProductEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService(),
            null,
        ];

        yield 'It sets cms page id if none is given and default is provided' => [
            (new ProductEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'config-id',
        ];

        yield 'It does not set cms page id if already given with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => 'own-id']),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'own-id',
        ];

        yield 'It does not set if no default is given with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService(),
            null,
        ];

        yield 'It sets cms page id if none is given and default is provided with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'config-id',
        ];
    }

    public static function resolveCmsPageIdProviderWithSalesChannelLoadedEventProvider(): \Generator
    {
        yield 'It does not set cms page id if already given' => [
            (new SalesChannelProductEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => 'own-id']),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'own-id',
        ];

        yield 'It does not set if no default is given' => [
            (new SalesChannelProductEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService(),
            null,
        ];

        yield 'It sets cms page id if none is given and default is provided' => [
            (new SalesChannelProductEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'config-id',
        ];

        yield 'It does not set cms page id if already given with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => 'own-id']),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'own-id',
        ];

        yield 'It does not set if no default is given with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService(),
            null,
        ];

        yield 'It sets cms page id if none is given and default is provided with partial entity' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'cmsPageId' => null]),
            new StaticSystemConfigService([self::CONFIG => 'config-id']),
            'config-id',
        ];
    }

    public function testEnsureServicesAreCalled(): void
    {
        $isNewDetector = $this->createMock(IsNewDetector::class);
        $isNewDetector->expects($this->once())->method('isNew');

        $maxPurchaseCalculator = $this->createMock(ProductMaxPurchaseCalculator::class);
        $maxPurchaseCalculator->expects($this->once())->method('calculate');

        $calculator = $this->createMock(AbstractProductPriceCalculator::class);
        $calculator->expects($this->once())->method('calculate');

        $productVariationBuilder = $this->createMock(ProductVariationBuilder::class);
        $productVariationBuilder->expects($this->once())->method('build');

        $propertyGroupSorter = $this->createMock(AbstractPropertyGroupSorter::class);
        $propertyGroupSorter->expects($this->once())->method('sort');

        $subscriber = new ProductSubscriber(
            $productVariationBuilder,
            $calculator,
            $propertyGroupSorter,
            $maxPurchaseCalculator,
            $isNewDetector,
            $this->createMock(SystemConfigService::class),
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $this->createMock(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            $this->createMock(Connection::class)
        );

        $cheapestPrice = new CheapestPriceContainer([]);

        $entity = (new PartialEntity())->assign([
            'id' => Uuid::randomHex(),
            'properties' => new EntityCollection(),
            'cheapestPrice' => $cheapestPrice,
        ]);

        /** @var SalesChannelEntityLoadedEvent<ProductEntity|PartialEntity> $event */
        $event = new SalesChannelEntityLoadedEvent(
            $this->createMock(ProductDefinition::class),
            [$entity],
            $this->createMock(SalesChannelContext::class)
        );

        $subscriber->salesChannelLoaded($event);
    }

    public function testEnsurePartialsEventsConsidered(): void
    {
        $events = ProductSubscriber::getSubscribedEvents();
        static::assertArrayHasKey('product.loaded', $events);
        static::assertArrayHasKey('product.partial_loaded', $events);
        static::assertArrayHasKey('sales_channel.product.loaded', $events);
        static::assertArrayHasKey('sales_channel.product.partial_loaded', $events);
    }

    public function testLoadedWithAdminContextConvertsUnits(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);

        $measurementBuilder = $this->createMock(ProductMeasurementUnitBuilder::class);

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag([
            PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
            PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
        ]);
        $requestStack->push($request);

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $measurementBuilder,
            $measurementUnitConverter,
            $requestStack,
            $this->createMock(Connection::class)
        );

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'width' => 10.0,
            'height' => 20.0,
            'length' => 30.0,
            'weight' => 5.0,
        ]);

        $measurementBuilder->expects($this->exactly(1))
            ->method('build')
            ->with($product, 'ft', 'lb')
            ->willReturnCallback(function (ProductEntity $product, $from, $to) {
                // Simulate conversion logic
                // For the sake of this example, we will just double the value

                $converted = new ConvertedUnitSet();
                $converted->addUnit(ProductMeasurementEnum::WEIGHT->value, new ConvertedUnit((float) $product->getWeight() * 2.0, $to));
                $converted->addUnit(ProductMeasurementEnum::WIDTH->value, new ConvertedUnit((float) $product->getWidth() * 2.0, $to));
                $converted->addUnit(ProductMeasurementEnum::LENGTH->value, new ConvertedUnit((float) $product->getLength() * 2.0, $to));
                $converted->addUnit(ProductMeasurementEnum::HEIGHT->value, new ConvertedUnit((float) $product->getHeight() * 2.0, $to));

                return $converted;
            });

        $context = Context::createDefaultContext(new AdminApiSource('user-id', 'integration-id'));

        /** @var EntityLoadedEvent<ProductEntity|PartialEntity> $event */
        $event = new EntityLoadedEvent(
            $this->createMock(ProductDefinition::class),
            [$product],
            $context
        );

        $subscriber->loaded($event);

        static::assertSame(20.0, $product->get('width'));
        static::assertSame(40.0, $product->get('height'));
        static::assertSame(60.0, $product->get('length'));
        static::assertSame(10.0, $product->get('weight'));
    }

    public function testLoadedWithNonAdminContextDoesNotConvertUnits(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementUnitBuilder = $this->createMock(ProductMeasurementUnitBuilder::class);
        $measurementUnitBuilder->expects($this->never())->method('build');

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag([
            PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
            PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
        ]);
        $requestStack->push($request);

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $measurementUnitBuilder,
            $measurementUnitConverter,
            $requestStack,
            $this->createMock(Connection::class)
        );

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'width' => 10.0,
            'height' => 20.0,
            'length' => 30.0,
            'weight' => 5.0,
        ]);

        $context = Context::createDefaultContext(); // Non-admin context

        /** @var EntityLoadedEvent<ProductEntity|PartialEntity> $event */
        $event = new EntityLoadedEvent(
            $this->createMock(ProductDefinition::class),
            [$product],
            $context
        );

        $subscriber->loaded($event);

        // Values should remain unchanged
        static::assertSame(10.0, $product->get('width'));
        static::assertSame(20.0, $product->get('height'));
        static::assertSame(30.0, $product->get('length'));
        static::assertSame(5.0, $product->get('weight'));
    }

    /**
     * @param array<string, float> $productDimensions
     * @param array<string, string> $headers
     * @param array<string, ConvertedUnit> $expectedFinalValues
     */
    #[DataProvider('convertMeasurementUnitProvider')]
    public function testConvertMeasurementUnitWithVariousValues(
        array $productDimensions,
        array $headers,
        int $expectedConversions,
        array $expectedFinalValues
    ): void {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementBuilder = $this->createMock(ProductMeasurementUnitBuilder::class);

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag($headers);
        $requestStack->push($request);

        $product = (new ProductEntity())->assign(array_merge(['id' => Uuid::randomHex()], $productDimensions));

        if ($expectedConversions > 0) {
            $measurementBuilder->expects($this->exactly(1))
                ->method('build')
                ->with($product, $headers[PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT] ?? 'mm', $headers[PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT] ?? 'kg')
                ->willReturnCallback(function (ProductEntity $product, $from, $to) {
                    // Simulate conversion logic
                    // For the sake of this example, we will just double the value

                    $converted = new ConvertedUnitSet();
                    $converted->addUnit(ProductMeasurementEnum::WEIGHT->value, new ConvertedUnit((float) $product->getWeight() * 2.0, $to));
                    $converted->addUnit(ProductMeasurementEnum::WIDTH->value, new ConvertedUnit((float) $product->getWidth() * 2.0, $to));
                    $converted->addUnit(ProductMeasurementEnum::LENGTH->value, new ConvertedUnit((float) $product->getLength() * 2.0, $to));
                    $converted->addUnit(ProductMeasurementEnum::HEIGHT->value, new ConvertedUnit((float) $product->getHeight() * 2.0, $to));

                    return $converted;
                });
        } else {
            $measurementBuilder->expects($this->never())->method('build');
        }

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $measurementBuilder,
            $measurementUnitConverter,
            $requestStack,
            $this->createMock(Connection::class)
        );

        $context = Context::createDefaultContext(new AdminApiSource('user-id', 'integration-id'));

        /** @var EntityLoadedEvent<ProductEntity|PartialEntity> $event */
        $event = new EntityLoadedEvent(
            $this->createMock(ProductDefinition::class),
            [$product],
            $context
        );

        $subscriber->loaded($event);

        foreach ($expectedFinalValues as $field => $expectedValue) {
            static::assertSame($expectedValue->value, $product->get($field), "Field {$field} does not match expected value");
        }
    }

    public static function convertMeasurementUnitProvider(): \Generator
    {
        yield 'No headers provided' => [
            'productDimensions' => ['width' => 10.0, 'height' => 20.0, 'weight' => 5.0],
            'headers' => [],
            'expectedConversions' => 0,
            'expectedFinalValues' => [],
        ];

        yield 'Only length unit header provided' => [
            'productDimensions' => ['width' => 10.0, 'height' => 20.0, 'length' => 30.0, 'weight' => 5.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft'],
            'expectedConversions' => 3,
            'expectedFinalValues' => [
                'width' => new ConvertedUnit(20.0, 'mm'),
                'height' => new ConvertedUnit(40.0, 'mm'),
                'length' => new ConvertedUnit(60.0, 'mm'),
            ],
        ];

        yield 'Only weight unit header provided' => [
            'productDimensions' => ['width' => 10.0, 'height' => 20.0, 'weight' => 5.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb'],
            'expectedConversions' => 1,
            'expectedFinalValues' => [
                'weight' => new ConvertedUnit(10.0, 'g'),
            ],
        ];

        yield 'Both unit headers provided' => [
            'productDimensions' => ['width' => 10.0, 'height' => 20.0, 'length' => 30.0, 'weight' => 5.0],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'expectedConversions' => 4,
            'expectedFinalValues' => [
                'width' => new ConvertedUnit(20.0, 'mm'),
                'height' => new ConvertedUnit(40.0, 'mm'),
                'length' => new ConvertedUnit(60.0, 'mm'),
                'weight' => new ConvertedUnit(10.0, 'kg'),
            ],
        ];

        yield 'Zero values are not converted' => [
            'productDimensions' => ['width' => 0.0, 'height' => 10.0, 'weight' => 0.0],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'expectedConversions' => 1,
            'expectedFinalValues' => [
                'height' => new ConvertedUnit(20.0, 'mm'),
            ],
        ];

        yield 'Null values are not converted' => [
            'productDimensions' => ['width' => null, 'height' => 10.0, 'weight' => null],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'expectedConversions' => 1,
            'expectedFinalValues' => [
                'height' => new ConvertedUnit(20.0, 'mm'),
            ],
        ];

        yield 'Non-float values are not converted' => [
            'productDimensions' => ['width' => '10', 'height' => 10.0, 'weight' => '5'],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'expectedConversions' => 1,
            'expectedFinalValues' => [
                'height' => new ConvertedUnit(20.0, 'mm'),
            ],
        ];

        yield 'Weight headers given but product has no weight' => [
            'productDimensions' => ['height' => 10.0],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'expectedConversions' => 1,
            'expectedFinalValues' => [
                'height' => new ConvertedUnit(20.0, 'mm'),
            ],
        ];
    }

    public function testLoadedWithNoRequestInStack(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementUnitConverter->expects($this->never())->method('convert');

        $requestStack = new RequestStack(); // No request pushed

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            $this->createMock(Connection::class)
        );

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'width' => 10.0,
            'height' => 20.0,
            'weight' => 5.0,
        ]);

        $context = Context::createDefaultContext(new AdminApiSource('user-id', 'integration-id'));

        /** @var EntityLoadedEvent<ProductEntity|PartialEntity> $event */
        $event = new EntityLoadedEvent(
            $this->createMock(ProductDefinition::class),
            [$product],
            $context
        );

        $subscriber->loaded($event);

        // Values should remain unchanged when no request is in stack
        static::assertSame(10.0, $product->get('width'));
        static::assertSame(20.0, $product->get('height'));
        static::assertSame(5.0, $product->get('weight'));
    }

    public function testBeforeWriteProductWithMeasurementHeaders(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementUnitConverter->expects($this->exactly(4))
            ->method('convert')
            ->willReturnCallback(function ($value, $from, $to) {
                return new ConvertedUnit($value * 2.0, $to);
            });

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag([
            PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
            PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
        ]);
        $requestStack->push($request);

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            $this->createMock(Connection::class)
        );

        $command = $this->createMock(WriteCommand::class);

        $command->expects($this->once())
            ->method('getPayload')
            ->willReturn([
                'width' => 10.0,
                'height' => 20.0,
                'length' => 30.0,
                'weight' => 5.0,
            ]);

        $command->expects($this->exactly(4))
            ->method('hasField')
            ->willReturnCallback(function ($field) {
                return \in_array($field, ['width', 'height', 'length', 'weight'], true);
            });

        $addPayloadCallCount = 0;
        $command->expects($this->exactly(4))
            ->method('addPayload')
            ->willReturnCallback(function ($field, $value) use (&$addPayloadCallCount): void {
                $expectedValues = [
                    'width' => 20.0,
                    'height' => 40.0,
                    'length' => 60.0,
                    'weight' => 10.0,
                ];
                static::assertArrayHasKey($field, $expectedValues);
                static::assertSame($expectedValues[$field], $value);
                ++$addPayloadCallCount;
            });

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->once())
            ->method('getCommandsForEntity')
            ->with(ProductDefinition::ENTITY_NAME)
            ->willReturn([$command]);

        $subscriber->beforeWriteProduct($event);
    }

    public function testBeforeWriteProductWithNoHeaders(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementUnitConverter->expects($this->never())->method('convert');

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag([]);
        $requestStack->push($request);

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            $this->createMock(Connection::class)
        );

        $command = $this->createMock(WriteCommand::class);

        $command->expects($this->never())->method('getPayload');
        $command->expects($this->never())->method('hasField');
        $command->expects($this->never())->method('addPayload');

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->never())
            ->method('getCommandsForEntity');

        $subscriber->beforeWriteProduct($event);
    }

    public function testBeforeWriteProductWithNonProductCommands(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementUnitConverter->expects($this->never())->method('convert');

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag([
            PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
            PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
        ]);
        $requestStack->push($request);

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            $this->createMock(Connection::class)
        );

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->once())
            ->method('getCommandsForEntity')
            ->with(ProductDefinition::ENTITY_NAME)
            ->willReturn([]);

        $subscriber->beforeWriteProduct($event);
    }

    /**
     * @param array<string, float> $payload
     * @param array<string, string> $headers
     * @param array<string, bool> $hasFieldReturns
     * @param array<string, float> $expectedConversions
     */
    #[DataProvider('beforeWriteProductFieldProvider')]
    public function testBeforeWriteProductWithVariousFieldTypes(
        array $payload,
        array $headers,
        array $hasFieldReturns,
        array $expectedConversions
    ): void {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);

        if (!empty($expectedConversions)) {
            $measurementUnitConverter->expects($this->exactly(\count($expectedConversions)))
                ->method('convert')
                ->willReturn(new ConvertedUnit(2.0, 'm'));
        } else {
            $measurementUnitConverter->expects($this->never())->method('convert');
        }

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers = new HeaderBag($headers);
        $requestStack->push($request);

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            $this->createMock(Connection::class)
        );

        $command = $this->createMock(WriteCommand::class);

        $command->expects($this->once())
            ->method('getPayload')
            ->willReturn($payload);

        $command->expects($this->any())
            ->method('hasField')
            ->willReturnCallback(function ($field) use ($hasFieldReturns) {
                return $hasFieldReturns[$field] ?? false;
            });

        if (!empty($expectedConversions)) {
            $command->expects($this->exactly(\count($expectedConversions)))
                ->method('addPayload');
        } else {
            $command->expects($this->never())->method('addPayload');
        }

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->once())
            ->method('getCommandsForEntity')
            ->with(ProductDefinition::ENTITY_NAME)
            ->willReturn([$command]);

        $subscriber->beforeWriteProduct($event);
    }

    public static function beforeWriteProductFieldProvider(): \Generator
    {
        yield 'Only length header with width field' => [
            'payload' => ['width' => 10.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft'],
            'hasFieldReturns' => ['width' => true],
            'expectedConversions' => ['width' => 20.0],
        ];

        yield 'Only weight header with weight field' => [
            'payload' => ['weight' => 5.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb'],
            'hasFieldReturns' => ['weight' => true],
            'expectedConversions' => ['weight' => 10.0],
        ];

        yield 'Non-float values are skipped' => [
            'payload' => ['width' => '10', 'height' => 20.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft'],
            'hasFieldReturns' => ['width' => true, 'height' => true],
            'expectedConversions' => ['height' => 40.0],
        ];

        yield 'Missing fields are skipped' => [
            'payload' => ['width' => 10.0],
            'headers' => [PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft'],
            'hasFieldReturns' => ['width' => true, 'height' => false],
            'expectedConversions' => ['width' => 20.0],
        ];

        yield 'Both headers with mixed fields' => [
            'payload' => ['height' => 15.0, 'weight' => 3.0],
            'headers' => [
                PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT => 'ft',
                PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT => 'lb',
            ],
            'hasFieldReturns' => ['height' => true, 'weight' => true],
            'expectedConversions' => ['height' => 30.0, 'weight' => 6.0],
        ];
    }

    public function testBeforeWriteProductWithNoRequestInStack(): void
    {
        $measurementUnitConverter = $this->createMock(AbstractMeasurementUnitConverter::class);
        $measurementUnitConverter->expects($this->never())->method('convert');

        $requestStack = new RequestStack(); // No request pushed

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $measurementUnitConverter,
            $requestStack,
            $this->createMock(Connection::class)
        );

        $command = $this->createMock(WriteCommand::class);

        $command->expects($this->never())->method('getPayload');
        $command->expects($this->never())->method('hasField');
        $command->expects($this->never())->method('addPayload');

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->never())
            ->method('getCommandsForEntity');

        $subscriber->beforeWriteProduct($event);
    }

    public function testBeforeDeleteProductWithNoDeletedProducts(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $this->createMock(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            $connection
        );

        $event = $this->createMock(EntityDeleteEvent::class);
        $event->expects($this->once())
            ->method('getIds')
            ->with(ProductDefinition::ENTITY_NAME)
            ->willReturn([]);

        $event->expects($this->never())->method('addSuccess');

        $subscriber->beforeDeleteProduct($event);
    }

    public function testBeforeDeleteProductWithDeletedProductsButNoParentIds(): void
    {
        $deletedId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $this->createMock(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            $connection
        );

        $event = $this->createMock(EntityDeleteEvent::class);
        $event->expects($this->once())
            ->method('getIds')
            ->with(ProductDefinition::ENTITY_NAME)
            ->willReturn([$deletedId]);

        $event->expects($this->never())->method('addSuccess');

        $subscriber->beforeDeleteProduct($event);
    }

    public function testBeforeDeleteProductWithDeletedProductsAndParentIds(): void
    {
        $deletedId = Uuid::randomHex();
        $parentIdBytes = Uuid::randomBytes();
        $versionBytes = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$parentIdBytes]);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('DELETE FROM product_configurator_setting'),
                [
                    'parentIds' => [$parentIdBytes],
                    'versionId' => $versionBytes,
                ],
                [
                    'parentIds' => ArrayParameterType::BINARY,
                ]
            );

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $this->createMock(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            $connection
        );

        $context = Context::createDefaultContext();

        $event = $this->createMock(EntityDeleteEvent::class);
        $event->expects($this->once())
            ->method('getIds')
            ->with(ProductDefinition::ENTITY_NAME)
            ->willReturn([$deletedId]);

        $event->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $successCallback = null;
        $event->expects($this->once())
            ->method('addSuccess')
            ->willReturnCallback(function ($callback) use (&$successCallback): void {
                $successCallback = $callback;
            });

        $subscriber->beforeDeleteProduct($event);

        static::assertNotNull($successCallback, 'Success callback should be registered');
        $successCallback();
    }

    public function testBeforeDeleteProductWithMultipleDeletedProducts(): void
    {
        $deletedId1 = Uuid::randomHex();
        $deletedId2 = Uuid::randomHex();
        $parentIdBytes1 = Uuid::randomBytes();
        $parentIdBytes2 = Uuid::randomBytes();
        $versionBytes = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$parentIdBytes1, $parentIdBytes2]);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('DELETE FROM product_configurator_setting'),
                [
                    'parentIds' => [$parentIdBytes1, $parentIdBytes2],
                    'versionId' => $versionBytes,
                ],
                [
                    'parentIds' => ArrayParameterType::BINARY,
                ]
            );

        $subscriber = new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            new StaticSystemConfigService(),
            $this->createMock(ProductMeasurementUnitBuilder::class),
            $this->createMock(AbstractMeasurementUnitConverter::class),
            new RequestStack(),
            $connection
        );

        $context = Context::createDefaultContext();

        $event = $this->createMock(EntityDeleteEvent::class);
        $event->expects($this->once())
            ->method('getIds')
            ->with(ProductDefinition::ENTITY_NAME)
            ->willReturn([$deletedId1, $deletedId2]);

        $event->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $successCallback = null;
        $event->expects($this->once())
            ->method('addSuccess')
            ->willReturnCallback(function ($callback) use (&$successCallback): void {
                $successCallback = $callback;
            });

        $subscriber->beforeDeleteProduct($event);

        static::assertNotNull($successCallback);
        $successCallback();
    }
}
