<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopware\Core\Content\Category\Subscriber\CategorySubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(CategorySubscriber::class)]
class CategorySubscriberTest extends TestCase
{
    public function testHasEvents(): void
    {
        $expectedEvents = [
            'sales_channel.category.loaded' => 'salesChannelCategoryLoaded',
            'category.written' => 'onCategoryWritten',
        ];

        static::assertSame($expectedEvents, CategorySubscriber::getSubscribedEvents());
    }

    #[DataProvider('salesChannelCategoryLoadedEventDataProvider')]
    public function testSalesChannelCategoryLoadedEvent(
        SystemConfigService $systemConfigService,
        SalesChannelCategoryEntity $categoryEntity,
        ?string $cmsPageIdBeforeEvent,
        ?string $cmsPageIdAfterEvent,
        string $salesChannelId
    ): void {
        $categorySubscriber = new CategorySubscriber($systemConfigService, $this->createMock(CategoryUrlGenerator::class), $this->createMock(Connection::class));

        static::assertSame($cmsPageIdBeforeEvent, $categoryEntity->getCmsPageId());

        $event = new SalesChannelEntityLoadedEvent(
            new SalesChannelCategoryDefinition(),
            [$categoryEntity],
            $this->getSalesChannelContext($salesChannelId)
        );

        $categorySubscriber->salesChannelCategoryLoaded($event);

        static::assertSame($cmsPageIdAfterEvent, $categoryEntity->getCmsPageId());
    }

    public function testOnCategoryWrittenPersistsDefaultCmsPageIdOnInsert(): void
    {
        $categoryId = Uuid::randomHex();
        $defaultCmsPageId = Uuid::randomHex();

        $systemConfigService = new StaticSystemConfigService([
            CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY => $defaultCmsPageId,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE `category` SET `cms_page_id` = :cmsPageId WHERE `id` IN (:ids) AND `cms_page_id` IS NULL',
                static::callback(function (array $params) use ($defaultCmsPageId, $categoryId): bool {
                    return $params['cmsPageId'] === Uuid::fromHexToBytes($defaultCmsPageId)
                        && $params['ids'] === Uuid::fromHexToBytesList([$categoryId]);
                }),
                ['ids' => ArrayParameterType::BINARY]
            );

        $subscriber = new CategorySubscriber($systemConfigService, $this->createMock(CategoryUrlGenerator::class), $connection);

        $writeResult = new EntityWriteResult(
            $categoryId,
            ['id' => $categoryId],
            'category',
            EntityWriteResult::OPERATION_INSERT
        );

        $event = new EntityWrittenEvent('category', [$writeResult], Context::createDefaultContext());
        $subscriber->onCategoryWritten($event);
    }

    public function testOnCategoryWrittenPersistsDefaultOnUpdateWithNullCmsPageId(): void
    {
        $categoryId = Uuid::randomHex();
        $defaultCmsPageId = Uuid::randomHex();

        $systemConfigService = new StaticSystemConfigService([
            CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY => $defaultCmsPageId,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement');

        $subscriber = new CategorySubscriber($systemConfigService, $this->createMock(CategoryUrlGenerator::class), $connection);

        $writeResult = new EntityWriteResult(
            $categoryId,
            ['id' => $categoryId, 'cmsPageId' => null],
            'category',
            EntityWriteResult::OPERATION_UPDATE
        );

        $event = new EntityWrittenEvent('category', [$writeResult], Context::createDefaultContext());
        $subscriber->onCategoryWritten($event);
    }

    public function testOnCategoryWrittenSkipsWhenCmsPageIdIsExplicitlySet(): void
    {
        $categoryId = Uuid::randomHex();
        $cmsPageId = Uuid::randomHex();
        $defaultCmsPageId = Uuid::randomHex();

        $systemConfigService = new StaticSystemConfigService([
            CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY => $defaultCmsPageId,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new CategorySubscriber($systemConfigService, $this->createMock(CategoryUrlGenerator::class), $connection);

        $writeResult = new EntityWriteResult(
            $categoryId,
            ['id' => $categoryId, 'cmsPageId' => $cmsPageId],
            'category',
            EntityWriteResult::OPERATION_INSERT
        );

        $event = new EntityWrittenEvent('category', [$writeResult], Context::createDefaultContext());
        $subscriber->onCategoryWritten($event);
    }

    public function testOnCategoryWrittenSkipsUpdateWithoutCmsPageIdInPayload(): void
    {
        $categoryId = Uuid::randomHex();
        $defaultCmsPageId = Uuid::randomHex();

        $systemConfigService = new StaticSystemConfigService([
            CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY => $defaultCmsPageId,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new CategorySubscriber($systemConfigService, $this->createMock(CategoryUrlGenerator::class), $connection);

        $writeResult = new EntityWriteResult(
            $categoryId,
            ['id' => $categoryId, 'name' => 'Updated Name'],
            'category',
            EntityWriteResult::OPERATION_UPDATE
        );

        $event = new EntityWrittenEvent('category', [$writeResult], Context::createDefaultContext());
        $subscriber->onCategoryWritten($event);
    }

    public function testOnCategoryWrittenSkipsWhenNoDefaultConfigured(): void
    {
        $systemConfigService = new StaticSystemConfigService([]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new CategorySubscriber($systemConfigService, $this->createMock(CategoryUrlGenerator::class), $connection);

        $writeResult = new EntityWriteResult(
            Uuid::randomHex(),
            ['id' => Uuid::randomHex()],
            'category',
            EntityWriteResult::OPERATION_INSERT
        );

        $event = new EntityWrittenEvent('category', [$writeResult], Context::createDefaultContext());
        $subscriber->onCategoryWritten($event);
    }

    /**
     * @return iterable<string, array{SystemConfigService, SalesChannelCategoryEntity, string|null, string|null, string}>
     */
    public static function salesChannelCategoryLoadedEventDataProvider(): iterable
    {
        yield 'It does not set cms page id if already set by the user' => [
            self::getSystemConfigServiceMock(),
            self::getCategory('foobar'),
            'foobar',
            'foobar',
            'salesChannelId',
        ];

        yield 'It uses salesChannel specific default' => [
            self::getSystemConfigServiceMock('salesChannelId', 'salesChannelSpecificDefault'),
            self::getCategory(null),
            null,
            'salesChannelSpecificDefault',
            'salesChannelId',
        ];

        $systemConfigWithOnlyGlobalDefault = self::getSystemConfigServiceMock('nonExistentSalesChannel', 'foobar');
        $systemConfigWithOnlyGlobalDefault->set(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY, 'globalDefaultCmsPage');

        yield 'It uses global default when no sales channel specific default is set' => [
            $systemConfigWithOnlyGlobalDefault,
            self::getCategory(null),
            null,
            'globalDefaultCmsPage',
            'testSalesChannelId',
        ];
    }

    private static function getSystemConfigServiceMock(?string $salesChannelId = null, ?string $cmsPageId = null): SystemConfigService
    {
        if ($salesChannelId === null && $cmsPageId === null) {
            return new StaticSystemConfigService([]);
        }

        if ($salesChannelId === null) {
            return new StaticSystemConfigService([
                CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY => $cmsPageId,
            ]);
        }

        return new StaticSystemConfigService([
            $salesChannelId => [
                CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY => $cmsPageId,
            ],
        ]);
    }

    private static function getCategory(?string $cmsPageId): SalesChannelCategoryEntity
    {
        $category = new SalesChannelCategoryEntity();

        if ($cmsPageId) {
            $category->setCmsPageId($cmsPageId);
        }

        return $category;
    }

    private function getSalesChannelContext(string $salesChanelId): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId($salesChanelId);

        return Generator::generateSalesChannelContext(salesChannel: $salesChannelEntity);
    }
}
