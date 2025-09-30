<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent;
use Shopware\Core\Content\Category\Service\CachedDefaultCategoryLevelLoader;
use Shopware\Core\Content\Category\Service\DefaultCategoryLevelLoader;
use Shopware\Core\Framework\Context;
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
#[CoversClass(CachedDefaultCategoryLevelLoader::class)]
#[Package('discovery')]
class CachedDefaultCategoryLevelLoaderTest extends TestCase
{
    private CachedDefaultCategoryLevelLoader $categoryLevelLoader;

    private MockObject&TagAwareCacheInterface $cache;

    private EventDispatcherInterface $eventDispatcher;

    private MockObject&DefaultCategoryLevelLoader $innerLoader;

    private MockObject&SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(TagAwareCacheInterface::class);
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);

        $this->eventDispatcher = new EventDispatcher();
        $this->innerLoader = $this->createMock(DefaultCategoryLevelLoader::class);

        $this->categoryLevelLoader = new CachedDefaultCategoryLevelLoader(
            $this->cache,
            $this->eventDispatcher,
            $this->innerLoader,
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = CachedDefaultCategoryLevelLoader::getSubscribedEvents();

        static::assertIsArray($events);
        static::assertArrayHasKey(CategoryEvents::CATEGORY_WRITTEN_EVENT, $events);
        static::assertSame('invalidateCache', $events[CategoryEvents::CATEGORY_WRITTEN_EVENT]);

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

        $this->innerLoader->expects($this->once())
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->salesChannelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $result = $this->categoryLevelLoader->loadLevels(
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
        $this->cache->expects($this->once())
            ->method('invalidateTags')
            ->with(['category_level_loader']);

        $this->categoryLevelLoader->invalidateCache();
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
        $this->innerLoader->expects($this->exactly(1))
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->salesChannelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $cache = new TagAwareAdapter(new ArrayAdapter());

        $loader = new CachedDefaultCategoryLevelLoader(
            $cache,
            $this->eventDispatcher,
            $this->innerLoader,
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
            function (CategoryLevelLoaderCacheKeyEvent $event) use ($cacheKeyParts, &$eventsThrown): void {
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

        static::assertEquals($expectedCollection, $result);
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
        $this->innerLoader->expects($this->exactly(1))
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->salesChannelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $cache = new TagAwareAdapter(new ArrayAdapter());

        $loader = new CachedDefaultCategoryLevelLoader(
            $cache,
            $this->eventDispatcher,
            $this->innerLoader,
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
            function (CategoryLevelLoaderCacheKeyEvent $event) use ($cacheKeyParts, &$eventsThrown): void {
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
        $this->innerLoader->expects($this->exactly(1))
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->salesChannelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $cache = new TagAwareAdapter(new ArrayAdapter());

        $loader = new CachedDefaultCategoryLevelLoader(
            $cache,
            $this->eventDispatcher,
            $this->innerLoader,
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
            function (CategoryLevelLoaderCacheKeyEvent $event) use ($cacheKeyParts, &$eventsThrown): void {
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
}
