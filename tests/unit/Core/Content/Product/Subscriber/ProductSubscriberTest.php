<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

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
        );

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
        );

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
        $isNewDetector->expects(static::once())->method('isNew');

        $maxPurchaseCalculator = $this->createMock(ProductMaxPurchaseCalculator::class);
        $maxPurchaseCalculator->expects(static::once())->method('calculate');

        $calculator = $this->createMock(AbstractProductPriceCalculator::class);
        $calculator->expects(static::once())->method('calculate');

        $productVariationBuilder = $this->createMock(ProductVariationBuilder::class);
        $productVariationBuilder->expects(static::once())->method('build');

        $propertyGroupSorter = $this->createMock(AbstractPropertyGroupSorter::class);
        $propertyGroupSorter->expects(static::once())->method('sort');

        $subscriber = new ProductSubscriber(
            $productVariationBuilder,
            $calculator,
            $propertyGroupSorter,
            $maxPurchaseCalculator,
            $isNewDetector,
            $this->createMock(SystemConfigService::class),
        );

        $cheapestPrice = new CheapestPriceContainer([]);

        $entity = (new PartialEntity())->assign([
            'id' => Uuid::randomHex(),
            'properties' => new EntityCollection(),
            'cheapestPrice' => $cheapestPrice,
        ]);

        $subscriber->salesChannelLoaded(
            new SalesChannelEntityLoadedEvent(
                $this->createMock(ProductDefinition::class),
                [$entity],
                $this->createMock(SalesChannelContext::class)
            )
        );
    }

    public function testEnsurePartialsEventsConsidered(): void
    {
        $events = ProductSubscriber::getSubscribedEvents();
        static::assertArrayHasKey('product.loaded', $events);
        static::assertArrayHasKey('product.partial_loaded', $events);
        static::assertArrayHasKey('sales_channel.product.loaded', $events);
        static::assertArrayHasKey('sales_channel.product.partial_loaded', $events);
    }
}
