<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Category\Subscriber\CategorySubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tax\TaxCollection;

/**
 * @internal
 *
 * @package content
 * @covers \Shopware\Core\Content\Category\Subscriber\CategorySubscriber
 */
class CategorySubscriberTest extends TestCase
{
    public function testHasEvents(): void
    {
        $expectedEvents = [
            CategoryEvents::CATEGORY_LOADED_EVENT => 'entityLoaded',
            'sales_channel.' . CategoryEvents::CATEGORY_LOADED_EVENT => 'entityLoaded',
        ];

        static::assertEquals($expectedEvents, CategorySubscriber::getSubscribedEvents());
    }

    /**
     * @dataProvider entityLoadedEventDataProvider
     */
    public function testEntityLoadedEvent(SystemConfigService $systemConfigService, CategoryEntity $categoryEntity, ?string $cmsPageIdBeforeEvent, ?string $cmsPageIdAfterEvent, ?string $salesChannelId): void
    {
        $categorySubscriber = new CategorySubscriber($systemConfigService);

        if ($salesChannelId) {
            $event = new SalesChannelEntityLoadedEvent(new CategoryDefinition(), [$categoryEntity], $this->getSalesChannelContext($salesChannelId));
        } else {
            $event = new EntityLoadedEvent(new CategoryDefinition(), [$categoryEntity], Context::createDefaultContext());
        }

        static::assertEquals($cmsPageIdBeforeEvent, $categoryEntity->getCmsPageId());
        $categorySubscriber->entityLoaded($event);
        static::assertEquals($cmsPageIdAfterEvent, $categoryEntity->getCmsPageId());
    }

    /**
     * @return array<string, array{systemConfigService: SystemConfigService, categoryEntity: CategoryEntity, cmsPageIdBeforeEvent: string|null, cmsPageIdAfterEvent:string|null, salesChannelId: string|null}>
     */
    public function entityLoadedEventDataProvider(): iterable
    {
        yield 'It does not set cms page id if already set by the user' => [
            'systemConfigService' => $this->getSystemConfigServiceMock(),
            'categoryEntity' => $this->getCategory('foobar', false),
            'cmsPageIdBeforeEvent' => 'foobar',
            'cmsPageIdAfterEvent' => 'foobar',
            'salesChannelId' => null,
        ];

        yield 'It does not set cms page id if already set by the subscriber' => [
            'systemConfigService' => $this->getSystemConfigServiceMock(null, 'cmsPageId'),
            'categoryEntity' => $this->getCategory('differentCmsPageId', true),
            'cmsPageIdBeforeEvent' => 'differentCmsPageId',
            'cmsPageIdAfterEvent' => 'differentCmsPageId',
            'salesChannelId' => 'salesChannelId',
        ];

        yield 'It does not set if no default is given' => [
            'systemConfigService' => $this->getSystemConfigServiceMock(),
            'categoryEntity' => $this->getCategory(null, false),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => null,
            'salesChannelId' => null,
        ];

        yield 'It uses overall default if no salesChannel is given' => [
            'systemConfigService' => $this->getSystemConfigServiceMock(null, 'cmsPageId'),
            'categoryEntity' => $this->getCategory(null, false),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => 'cmsPageId',
            'salesChannelId' => null,
        ];

        yield 'It uses salesChannel specific default' => [
            'systemConfigService' => $this->getSystemConfigServiceMock('salesChannelId', 'salesChannelSpecificDefault'),
            'categoryEntity' => $this->getCategory(null, false),
            'cmsPageIdBeforeEvent' => null,
            'cmsPageIdAfterEvent' => 'salesChannelSpecificDefault',
            'salesChannelId' => 'salesChannelId',
        ];
    }

    private function getSystemConfigServiceMock(?string $salesChannelId = null, ?string $cmsPageId = null): SystemConfigService
    {
        $systemContextService = $this->createMock(SystemConfigService::class);

        $systemContextService
            ->method('get')
            ->with(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY, $salesChannelId)
            ->willReturn($cmsPageId);

        return $systemContextService;
    }

    private function getCategory(?string $cmsPageId, bool $cmsPageIdSwitched): CategoryEntity
    {
        $category = new CategoryEntity();

        if ($cmsPageId) {
            $category->setCmsPageId($cmsPageId);
        }

        $category->setCmsPageIdSwitched($cmsPageIdSwitched);

        return $category;
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
