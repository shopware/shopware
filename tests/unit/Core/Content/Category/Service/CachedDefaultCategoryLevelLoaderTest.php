<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent;
use Shopware\Core\Content\Category\Service\CachedDefaultCategoryLevelLoader;
use Shopware\Core\Content\Category\Service\DefaultCategoryLevelLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CachedDefaultCategoryLevelLoader::class)]
class CachedDefaultCategoryLevelLoaderTest extends TestCase
{
    private const ROOT_A = '019a5b0e5a7c71a2a5c1e6a1b2c3d4e1';
    private const ROOT_B = '019a5b0e5a7c71a2a5c1e6a1b2c3d4e2';
    private const CLOTHING = '019a5b0e5a7c71a2a5c1e6a1b2c3d4e3';
    private const SHIRTS = '019a5b0e5a7c71a2a5c1e6a1b2c3d4e4';
    private const LANGUAGE = '019a5b0e5a7c71a2a5c1e6a1b2c3d4e5';

    private TagAwareCacheInterface&Stub $cache;

    private EventDispatcherInterface $eventDispatcher;

    private DefaultCategoryLevelLoader&Stub $innerLoader;

    private Stub&SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->cache = static::createStub(TagAwareCacheInterface::class);
        $this->salesChannelContext = static::createStub(SalesChannelContext::class);

        $this->eventDispatcher = new EventDispatcher();
        $this->innerLoader = static::createStub(DefaultCategoryLevelLoader::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = CachedDefaultCategoryLevelLoader::getSubscribedEvents();

        static::assertIsArray($events);
        static::assertArrayHasKey(CategoryEvents::CATEGORY_WRITTEN_EVENT, $events);
        static::assertSame('onCategoryWritten', $events[CategoryEvents::CATEGORY_WRITTEN_EVENT]);

        static::assertArrayHasKey(CategoryEvents::CATEGORY_TRANSLATION_WRITTEN_EVENT, $events);
        static::assertSame('onCategoryTranslationWritten', $events[CategoryEvents::CATEGORY_TRANSLATION_WRITTEN_EVENT]);

        static::assertArrayHasKey(CategoryEvents::CATEGORY_TRANSLATION_DELETED_EVENT, $events);
        static::assertSame('onCategoryTranslationDeleted', $events[CategoryEvents::CATEGORY_TRANSLATION_DELETED_EVENT]);

        static::assertArrayHasKey(CategoryEvents::CATEGORY_DELETED_EVENT, $events);
        static::assertSame('invalidateCache', $events[CategoryEvents::CATEGORY_DELETED_EVENT]);
    }

    public function testLoadLevelsOutsideMainCategoryIsUncached(): void
    {
        $rootId = 'non-navigation-category-id';
        $rootLevel = 1;
        $depth = 3;
        $criteria = new Criteria();

        $salesChannel = (new SalesChannelEntity())->assign([
            'navigationCategoryId' => 'different-id',
        ]);

        $this->salesChannelContext->method('getSalesChannel')
            ->willReturn($salesChannel);

        $expectedCollection = new CategoryCollection();

        $innerLoader = $this->createMock(DefaultCategoryLevelLoader::class);
        $innerLoader->expects($this->once())
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->salesChannelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $result = $this->createLoader(innerLoader: $innerLoader)->loadLevels(
            $rootId,
            $rootLevel,
            $this->salesChannelContext,
            $criteria,
            $depth
        );

        static::assertSame($expectedCollection, $result);
    }

    public function testInvalidateCache(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())
            ->method('invalidateTags')
            ->with(['category_level_loader']);

        $this->createLoader(cache: $cache)->invalidateCache();
    }

    public function testCachedLoading(): void
    {
        $rootId = 'navigation-category-id';
        $rootLevel = 1;
        $depth = 3;
        $criteria = new Criteria();

        $salesChannel = (new SalesChannelEntity())->assign([
            'navigationCategoryId' => $rootId,
        ]);

        $this->salesChannelContext->method('getSalesChannel')
            ->willReturn($salesChannel);
        $context = Context::createDefaultContext();
        $this->salesChannelContext->method('getContext')
            ->willReturn($context);
        $this->salesChannelContext->method('getSalesChannelId')
            ->willReturn('sales-channel-id');

        $expectedCollection = new CategoryCollection();
        $innerLoader = $this->createMock(DefaultCategoryLevelLoader::class);
        $innerLoader->expects($this->exactly(1))
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->salesChannelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $cache = new TagAwareAdapter(new ArrayAdapter());

        $loader = new CachedDefaultCategoryLevelLoader(
            $cache,
            $this->eventDispatcher,
            $innerLoader,
            static::createStub(Connection::class),
        );

        $cacheKeyParts = [
            'rootId' => $rootId,
            'depth' => $depth,
            'salesChannelId' => 'sales-channel-id',
            'languageId' => $context->getLanguageId(),
        ];
        $eventsThrown = 0;
        $this->eventDispatcher->addListener(
            CategoryLevelLoaderCacheKeyEvent::class,
            static function (CategoryLevelLoaderCacheKeyEvent $event) use ($cacheKeyParts, &$eventsThrown): void {
                static::assertSame($cacheKeyParts, $event->getParts());

                ++$eventsThrown;
            }
        );

        $result = $loader->loadLevels(
            $rootId,
            $rootLevel,
            $this->salesChannelContext,
            $criteria,
            $depth
        );
        $result2 = $loader->loadLevels(
            $rootId,
            $rootLevel,
            $this->salesChannelContext,
            $criteria,
            $depth
        );

        // the first call built the levels itself and returns them without a cache round trip
        static::assertSame($expectedCollection, $result);
        static::assertEquals($result2, $result);
        static::assertSame(2, $eventsThrown);

        static::assertTrue($cache->hasItem(Hasher::hash($cacheKeyParts)));

        $loader->invalidateCache();

        static::assertFalse($cache->hasItem(Hasher::hash($cacheKeyParts)));
    }

    public function testEventDisablesCaching(): void
    {
        $rootId = 'navigation-category-id';
        $rootLevel = 1;
        $depth = 3;
        $criteria = new Criteria();

        $salesChannel = (new SalesChannelEntity())->assign([
            'navigationCategoryId' => $rootId,
        ]);

        $this->salesChannelContext->method('getSalesChannel')
            ->willReturn($salesChannel);
        $context = Context::createDefaultContext();
        $this->salesChannelContext->method('getContext')
            ->willReturn($context);
        $this->salesChannelContext->method('getSalesChannelId')
            ->willReturn('sales-channel-id');

        $expectedCollection = new CategoryCollection();
        $innerLoader = $this->createMock(DefaultCategoryLevelLoader::class);
        $innerLoader->expects($this->exactly(1))
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->salesChannelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $cache = new TagAwareAdapter(new ArrayAdapter());

        $loader = new CachedDefaultCategoryLevelLoader(
            $cache,
            $this->eventDispatcher,
            $innerLoader,
            static::createStub(Connection::class),
        );

        $cacheKeyParts = [
            'rootId' => $rootId,
            'depth' => $depth,
            'salesChannelId' => 'sales-channel-id',
            'languageId' => $context->getLanguageId(),
        ];
        $eventsThrown = 0;
        $this->eventDispatcher->addListener(
            CategoryLevelLoaderCacheKeyEvent::class,
            static function (CategoryLevelLoaderCacheKeyEvent $event) use ($cacheKeyParts, &$eventsThrown): void {
                static::assertSame($cacheKeyParts, $event->getParts());

                $event->disableCaching();

                ++$eventsThrown;
            }
        );

        $result = $loader->loadLevels(
            $rootId,
            $rootLevel,
            $this->salesChannelContext,
            $criteria,
            $depth
        );

        static::assertEquals($expectedCollection, $result);
        static::assertSame(1, $eventsThrown);

        static::assertFalse($cache->hasItem(Hasher::hash($cacheKeyParts)));
    }

    public function testEventManipulatesCacheKey(): void
    {
        $rootId = 'navigation-category-id';
        $rootLevel = 1;
        $depth = 3;
        $criteria = new Criteria();

        $salesChannel = (new SalesChannelEntity())->assign([
            'navigationCategoryId' => $rootId,
        ]);

        $this->salesChannelContext->method('getSalesChannel')
            ->willReturn($salesChannel);
        $context = Context::createDefaultContext();
        $this->salesChannelContext->method('getContext')
            ->willReturn($context);
        $this->salesChannelContext->method('getSalesChannelId')
            ->willReturn('sales-channel-id');

        $expectedCollection = new CategoryCollection();
        $innerLoader = $this->createMock(DefaultCategoryLevelLoader::class);
        $innerLoader->expects($this->exactly(1))
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->salesChannelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $cache = new TagAwareAdapter(new ArrayAdapter());

        $loader = new CachedDefaultCategoryLevelLoader(
            $cache,
            $this->eventDispatcher,
            $innerLoader,
            static::createStub(Connection::class),
        );

        $cacheKeyParts = [
            'rootId' => $rootId,
            'depth' => $depth,
            'salesChannelId' => 'sales-channel-id',
            'languageId' => $context->getLanguageId(),
        ];
        $eventsThrown = 0;
        $this->eventDispatcher->addListener(
            CategoryLevelLoaderCacheKeyEvent::class,
            static function (CategoryLevelLoaderCacheKeyEvent $event) use ($cacheKeyParts, &$eventsThrown): void {
                static::assertSame($cacheKeyParts, $event->getParts());

                $event->addPart('test', 'test');

                ++$eventsThrown;
            }
        );

        $result = $loader->loadLevels(
            $rootId,
            $rootLevel,
            $this->salesChannelContext,
            $criteria,
            $depth
        );

        static::assertEquals($expectedCollection, $result);
        static::assertSame(1, $eventsThrown);

        $cacheKeyParts['test'] = 'test';
        static::assertTrue($cache->hasItem(Hasher::hash($cacheKeyParts)));

        $loader->invalidateCache();

        static::assertFalse($cache->hasItem(Hasher::hash($cacheKeyParts)));
    }

    public function testCategoryWriteInvalidatesOnlyTheNavigationTreeContainingIt(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $loader = $this->createLoader(cache: $cache, connection: $this->connectionWithPaths([
            self::SHIRTS => '|' . self::ROOT_A . '|' . self::CLOTHING . '|',
        ]));

        $this->loadLevels($loader, self::ROOT_A);
        $this->loadLevels($loader, self::ROOT_B);

        $loader->onCategoryWritten($this->categoryWritten(
            new EntityWriteResult(self::SHIRTS, ['active' => false], 'category', EntityWriteResult::OPERATION_UPDATE),
        ));

        static::assertFalse($cache->hasItem($this->cacheKey(self::ROOT_A)));
        static::assertTrue($cache->hasItem($this->cacheKey(self::ROOT_B)));
    }

    public function testCategoryAssignmentOfAProductKeepsTheCache(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $loader = $this->createLoader(cache: $cache, connection: $this->connectionWithPaths([
            self::SHIRTS => '|' . self::ROOT_A . '|' . self::CLOTHING . '|',
        ]));

        $this->loadLevels($loader, self::ROOT_A);

        // assigning a product to a category adds a category result without payload
        $loader->onCategoryWritten($this->categoryWritten(
            new EntityWriteResult(self::SHIRTS, [], 'category', EntityWriteResult::OPERATION_UPDATE),
        ));

        static::assertTrue($cache->hasItem($this->cacheKey(self::ROOT_A)));
    }

    public function testNewCategoryInvalidatesTheTreeOfItsParent(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $loader = $this->createLoader(cache: $cache, connection: $this->connectionWithPaths([
            self::CLOTHING => '|' . self::ROOT_A . '|',
        ]));

        $this->loadLevels($loader, self::ROOT_A);
        $this->loadLevels($loader, self::ROOT_B);

        $loader->onCategoryWritten($this->categoryWritten(
            new EntityWriteResult(self::SHIRTS, ['parentId' => self::CLOTHING], 'category', EntityWriteResult::OPERATION_INSERT),
        ));

        static::assertFalse($cache->hasItem($this->cacheKey(self::ROOT_A)));
        static::assertTrue($cache->hasItem($this->cacheKey(self::ROOT_B)));
    }

    public function testMovingACategoryInvalidatesAllTrees(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $loader = $this->createLoader(cache: $cache, connection: $this->connectionWithPaths([
            self::SHIRTS => '|' . self::ROOT_A . '|' . self::CLOTHING . '|',
        ]));

        $this->loadLevels($loader, self::ROOT_A);
        $this->loadLevels($loader, self::ROOT_B);

        $loader->onCategoryWritten($this->categoryWritten(
            new EntityWriteResult(self::SHIRTS, ['parentId' => self::ROOT_B], 'category', EntityWriteResult::OPERATION_UPDATE),
        ));

        static::assertFalse($cache->hasItem($this->cacheKey(self::ROOT_A)));
        static::assertFalse($cache->hasItem($this->cacheKey(self::ROOT_B)));
    }

    public function testCategoryTranslationWriteInvalidatesOnlyTheNavigationTreeContainingIt(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $loader = $this->createLoader(cache: $cache, connection: $this->connectionWithPaths([
            self::ROOT_A => null,
        ]));

        $this->loadLevels($loader, self::ROOT_A);
        $this->loadLevels($loader, self::ROOT_B);

        $loader->onCategoryTranslationWritten(new EntityWrittenEvent('category_translation', [
            new EntityWriteResult(
                ['categoryId' => self::ROOT_A, 'languageId' => self::LANGUAGE],
                ['name' => 'Home'],
                'category_translation',
                EntityWriteResult::OPERATION_UPDATE
            ),
        ], Context::createDefaultContext()));

        static::assertFalse($cache->hasItem($this->cacheKey(self::ROOT_A)));
        static::assertTrue($cache->hasItem($this->cacheKey(self::ROOT_B)));
    }

    public function testCategoryTranslationDeletionInvalidatesOnlyTheNavigationTreeContainingIt(): void
    {
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $loader = $this->createLoader(cache: $cache, connection: $this->connectionWithPaths([
            self::SHIRTS => '|' . self::ROOT_A . '|' . self::CLOTHING . '|',
        ]));

        $this->loadLevels($loader, self::ROOT_A);
        $this->loadLevels($loader, self::ROOT_B);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($loader);
        $deleted = new EntityDeletedEvent('category_translation', [
            new EntityWriteResult(
                ['categoryId' => self::SHIRTS, 'languageId' => self::LANGUAGE],
                [],
                'category_translation',
                EntityWriteResult::OPERATION_DELETE,
            ),
        ], Context::createDefaultContext());
        $dispatcher->dispatch($deleted, $deleted->getName());

        $parentWritten = $this->categoryWritten(
            new EntityWriteResult(self::SHIRTS, [], 'category', EntityWriteResult::OPERATION_UPDATE),
        );
        $dispatcher->dispatch($parentWritten, $parentWritten->getName());

        static::assertFalse($cache->hasItem($this->cacheKey(self::ROOT_A)));
        static::assertTrue($cache->hasItem($this->cacheKey(self::ROOT_B)));
    }

    private function createLoader(
        ?TagAwareCacheInterface $cache = null,
        ?DefaultCategoryLevelLoader $innerLoader = null,
        ?Connection $connection = null,
    ): CachedDefaultCategoryLevelLoader {
        return new CachedDefaultCategoryLevelLoader(
            $cache ?? $this->cache,
            $this->eventDispatcher,
            $innerLoader ?? $this->innerLoader,
            $connection ?? static::createStub(Connection::class),
        );
    }

    private function loadLevels(CachedDefaultCategoryLevelLoader $loader, string $rootId): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')
            ->willReturn((new SalesChannelEntity())->assign(['navigationCategoryId' => $rootId]));
        $salesChannelContext->method('getContext')
            ->willReturn(Context::createDefaultContext());
        $salesChannelContext->method('getSalesChannelId')
            ->willReturn('sales-channel-of-' . $rootId);

        $loader->loadLevels($rootId, 1, $salesChannelContext, new Criteria(), 3);
    }

    private function cacheKey(string $rootId): string
    {
        return Hasher::hash([
            'rootId' => $rootId,
            'depth' => 3,
            'salesChannelId' => 'sales-channel-of-' . $rootId,
            'languageId' => Context::createDefaultContext()->getLanguageId(),
        ]);
    }

    /**
     * @param array<string, string|null> $paths
     */
    private function connectionWithPaths(array $paths): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn($paths);

        return $connection;
    }

    private function categoryWritten(EntityWriteResult ...$results): EntityWrittenEvent
    {
        return new EntityWrittenEvent('category', array_values($results), Context::createDefaultContext());
    }
}
