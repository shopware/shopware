<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\AbstractPropertyGroupSorter;
use Shopware\Core\Content\Product\IsNewDetector;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\ProductVariationBuilder;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\Subscriber\ProductSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Entity\PartialSalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tax\TaxCollection;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\Subscriber\ProductSubscriber
 */
class ProductSubscriberTest extends TestCase
{
    /**
     * @dataProvider productLoadedDataProvider
     */
    public function testProductLoadedEvent(ProductEntity $productEntity, SystemConfigService $systemConfigService, ?string $cmsPageIdBeforeEvent, ?string $cmsPageIdAfterEvent): void
    {
        $productSubscriber = $this->getSubscriber($systemConfigService);

        $event = new EntityLoadedEvent(new ProductDefinition(), [$productEntity], Context::createDefaultContext());

        static::assertEquals($cmsPageIdBeforeEvent, $productEntity->getCmsPageId());
        $productSubscriber->loaded($event);
        static::assertEquals($cmsPageIdAfterEvent, $productEntity->getCmsPageId());
    }

    /**
     * @dataProvider partialEntityLoadedDataProvider
     */
    public function testPartialEntityLoadedEvent(PartialEntity $productEntity, SystemConfigService $systemConfigService, ?string $cmsPageIdBeforeEvent, ?string $cmsPageIdAfterEvent, bool $hasCmsPageIdProperty): void
    {
        $productSubscriber = $this->getSubscriber($systemConfigService);

        $event = new PartialEntityLoadedEvent(new ProductDefinition(), [$productEntity], Context::createDefaultContext());

        if ($hasCmsPageIdProperty) {
            static::assertEquals($cmsPageIdBeforeEvent, $productEntity->get('cmsPageId'));
        } else {
            static::assertFalse($productEntity->has('cmsPageId'));
        }

        $productSubscriber->partialEntityLoaded($event);

        if ($hasCmsPageIdProperty) {
            static::assertEquals($cmsPageIdAfterEvent, $productEntity->get('cmsPageId'));
        } else {
            static::assertFalse($productEntity->has('cmsPageId'));
        }
    }

    /**
     * @dataProvider salesChannelProductLoadedDataProvider
     */
    public function testSalesChannelProductLoadedEvent(ProductEntity $productEntity, SystemConfigService $systemConfigService, SalesChannelContext $salesChannelContext, ?string $cmsPageIdBeforeEvent, ?string $cmsPageIdAfterEvent): void
    {
        $productSubscriber = $this->getSubscriber($systemConfigService);

        $event = new SalesChannelEntityLoadedEvent(new ProductDefinition(), [$productEntity], $salesChannelContext);

        static::assertEquals($cmsPageIdBeforeEvent, $productEntity->getCmsPageId());
        $productSubscriber->salesChannelLoaded($event);
        static::assertEquals($cmsPageIdAfterEvent, $productEntity->getCmsPageId());
    }

    /**
     * @dataProvider partialSalesChannelProductLoadedDataProvider
     */
    public function testPartialSalesChannelProductLoadedEvent(PartialEntity $productEntity, SystemConfigService $systemConfigService, SalesChannelContext $salesChannelContext, ?string $cmsPageIdBeforeEvent, ?string $cmsPageIdAfterEvent, bool $hasCmsPageIdProperty): void
    {
        $productSubscriber = $this->getSubscriber($systemConfigService);

        $event = new PartialSalesChannelEntityLoadedEvent(new ProductDefinition(), [$productEntity], $salesChannelContext);

        if ($hasCmsPageIdProperty) {
            static::assertEquals($cmsPageIdBeforeEvent, $productEntity->get('cmsPageId'));
        } else {
            static::assertFalse($productEntity->has('cmsPageId'));
        }

        $productSubscriber->partialSalesChannelLoaded($event);

        if ($hasCmsPageIdProperty) {
            static::assertEquals($cmsPageIdAfterEvent, $productEntity->get('cmsPageId'));
        } else {
            static::assertFalse($productEntity->has('cmsPageId'));
        }
    }

    /**
     * @return array<string, array{productEntity: ProductEntity, systemConfigService: SystemConfigService, cmsPageIdBeforeEvent: string|null, cmsPageIdAfterEvent: string|null}>
     */
    public function productLoadedDataProvider(): iterable
    {
        yield 'It does not set cms page id if already given' => [
            'productEntity' => $this->getProductEntity('cmsPageId'),
            'systemConfigService' => $this->getSystemConfigServiceMock(null, 'defaultCmsPageId'),
            'cmsPageIdBeforeEvent' => 'cmsPageId',
            'cmsPageIdAfterEvent' => 'cmsPageId',
        ];

        yield 'It does not set if no default is given' => [
            'productEntity' => $this->getProductEntity(),
            'systemConfigService' => $this->getSystemConfigServiceMock(),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => null,
        ];

        yield 'It sets cms page id if none is given and default is provided' => [
            'productEntity' => $this->getProductEntity(),
            'systemConfigService' => $this->getSystemConfigServiceMock(null, 'cmsPageId'),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => 'cmsPageId',
        ];
    }

    /**
     * @return array<string, array{partialEntity: PartialEntity, systemConfigService: SystemConfigService, cmsPageIdBeforeEvent: string|null, cmsPageIdAfterEvent: string|null, hasCmsPageIdProperty: bool}>
     */
    public function partialEntityLoadedDataProvider(): iterable
    {
        yield 'It does not set if no cms page id property is given' => [
            'partialEntity' => $this->getPartialEntity(false),
            'systemConfigService' => $this->getSystemConfigServiceMock(),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => null,
            'hasCmsPageIdProperty' => false,
        ];

        yield 'It does not set cms page id if already given' => [
            'partialEntity' => $this->getPartialEntity(true, 'cmsPageId'),
            'systemConfigService' => $this->getSystemConfigServiceMock(),
            'cmsPageIdBeforeEvent' => 'cmsPageId',
            'cmsPageIdAfterEvent' => 'cmsPageId',
            'hasCmsPageIdProperty' => true,
        ];

        yield 'It does not set if no default is given' => [
            'partialEntity' => $this->getPartialEntity(true),
            'systemConfigService' => $this->getSystemConfigServiceMock(),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => null,
            'hasCmsPageIdProperty' => true,
        ];

        yield 'It sets cms page id if none is given and default is provided' => [
            'partialEntity' => $this->getPartialEntity(true),
            'systemConfigService' => $this->getSystemConfigServiceMock(null, 'cmsPageId'),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => 'cmsPageId',
            'hasCmsPageIdProperty' => true,
        ];
    }

    /**
     * @return array<string, array{productEntity: ProductEntity, systemConfigService: SystemConfigService, salesChannelContext: SalesChannelContext, cmsPageIdBeforeEvent: string|null, cmsPageIdAfterEvent: string|null}>
     */
    public function salesChannelProductLoadedDataProvider(): iterable
    {
        yield 'It does not set cms page id if already given' => [
            'productEntity' => $this->getSalesChannelProductEntity('cmsPageId'),
            'systemConfigService' => $this->getSystemConfigServiceMock(null, 'defaultCmsPageId'),
            'salesChannelContext' => $this->getSalesChannelContext('salesChannelId'),
            'cmsPageIdBeforeEvent' => 'cmsPageId',
            'cmsPageIdAfterEvent' => 'cmsPageId',
        ];

        yield 'It does not set if no default is given' => [
            'productEntity' => $this->getSalesChannelProductEntity(),
            'systemConfigService' => $this->getSystemConfigServiceMock('salesChannelId'),
            'salesChannelContext' => $this->getSalesChannelContext('salesChannelId'),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => null,
        ];

        yield 'It sets cms page id if none is given and default is provided' => [
            'productEntity' => $this->getSalesChannelProductEntity(),
            'systemConfigService' => $this->getSystemConfigServiceMock('salesChannelId', 'cmsPageId'),
            'salesChannelContext' => $this->getSalesChannelContext('salesChannelId'),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => 'cmsPageId',
        ];

        yield 'It sets cms page id if none is given and sales channel specific default is provided' => [
            'productEntity' => $this->getSalesChannelProductEntity(),
            'systemConfigService' => $this->getSystemConfigServiceMock('salesChannelId', 'cmsPageId'),
            'salesChannelContext' => $this->getSalesChannelContext('salesChannelId'),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => 'cmsPageId',
        ];
    }

    /**
     * @return array<string, array{partialEntity: PartialEntity, systemConfigService: SystemConfigService, salesChannelContext: SalesChannelContext, cmsPageIdBeforeEvent: string|null, cmsPageIdAfterEvent: string|null, hasCmsPageIdProperty: bool}>
     */
    public function partialSalesChannelProductLoadedDataProvider(): iterable
    {
        yield 'It does not set if no cms page id property is given' => [
            'partialEntity' => $this->getPartialEntity(false),
            'systemConfigService' => $this->getSystemConfigServiceMock(),
            'salesChannelContext' => $this->getSalesChannelContext('salesChannelId'),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => null,
            'hasCmsPageIdProperty' => false,
        ];

        yield 'It does not set cms page id if already given' => [
            'partialEntity' => $this->getPartialEntity(true, 'cmsPageId'),
            'systemConfigService' => $this->getSystemConfigServiceMock(),
            'salesChannelContext' => $this->getSalesChannelContext('salesChannelId'),
            'cmsPageIdBeforeEvent' => 'cmsPageId',
            'cmsPageIdAfterEvent' => 'cmsPageId',
            'hasCmsPageIdProperty' => true,
        ];

        yield 'It does not set if no default is given' => [
            'partialEntity' => $this->getPartialEntity(true),
            'systemConfigService' => $this->getSystemConfigServiceMock('salesChannelId'),
            'salesChannelContext' => $this->getSalesChannelContext('salesChannelId'),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => null,
            'hasCmsPageIdProperty' => true,
        ];

        yield 'It sets cms page id if none is given and default is provided' => [
            'partialEntity' => $this->getPartialEntity(true),
            'systemConfigService' => $this->getSystemConfigServiceMock('salesChannelId', 'cmsPageId'),
            'salesChannelContext' => $this->getSalesChannelContext('salesChannelId'),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => 'cmsPageId',
            'hasCmsPageIdProperty' => true,
        ];

        yield 'It sets cms page id if none is given and sales channel specific default is provided' => [
            'partialEntity' => $this->getPartialEntity(true),
            'systemConfigService' => $this->getSystemConfigServiceMock('salesChannelId', 'cmsPageId'),
            'salesChannelContext' => $this->getSalesChannelContext('salesChannelId'),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => 'cmsPageId',
            'hasCmsPageIdProperty' => true,
        ];
    }

    private function getProductEntity(?string $cmsPageId = null): ProductEntity
    {
        $productEntity = new ProductEntity();

        if ($cmsPageId) {
            $productEntity->setCmsPageId($cmsPageId);
        }

        return $productEntity;
    }

    private function getSalesChannelProductEntity(?string $cmsPageId = null): ProductEntity
    {
        $productEntity = new SalesChannelProductEntity();

        if ($cmsPageId) {
            $productEntity->setCmsPageId($cmsPageId);
        }

        return $productEntity;
    }

    private function getSubscriber(SystemConfigService $systemConfigService): ProductSubscriber
    {
        return new ProductSubscriber(
            $this->createMock(ProductVariationBuilder::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractPropertyGroupSorter::class),
            $this->createMock(ProductMaxPurchaseCalculator::class),
            $this->createMock(IsNewDetector::class),
            $systemConfigService
        );
    }

    private function getPartialEntity(bool $hasCmsPageIdProperty, ?string $cmsPageId = null): PartialEntity
    {
        $partialEntity = new PartialEntity();

        if ($hasCmsPageIdProperty) {
            $partialEntity->assign(['cmsPageId' => $cmsPageId]);
        }

        return $partialEntity;
    }

    private function getSystemConfigServiceMock(?string $salesChannelId = null, ?string $cmsPageId = null): SystemConfigService
    {
        $systemContextService = $this->createMock(SystemConfigService::class);

        $systemContextService
            ->method('get')
            ->with(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $salesChannelId)
            ->willReturn($cmsPageId);

        return $systemContextService;
    }

    private function getSalesChannelContext(string $salesChanelId): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId($salesChanelId);

        return new SalesChannelContext(
            Context::createDefaultContext(),
            'foo',
            'bar',
            $salesChannelEntity,
            new CurrencyEntity(),
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CustomerEntity(),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            []
        );
    }
}
