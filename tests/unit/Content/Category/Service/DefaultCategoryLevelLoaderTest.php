<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Content\Category\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent;
use Shopware\Core\Content\Category\Service\DefaultCategoryLevelLoader;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
#[CoversClass(DefaultCategoryLevelLoader::class)]
#[Package('discovery')]
class DefaultCategoryLevelLoaderTest extends TestCase
{
    private DefaultCategoryLevelLoader $categoryLevelLoader;

    private MockObject&TagAwareCacheInterface $cache;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var MockObject&SalesChannelRepository<CategoryCollection>
     */
    private MockObject&SalesChannelRepository $categoryRepository;

    private MockObject&SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(TagAwareCacheInterface::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->categoryRepository = $this->createMock(SalesChannelRepository::class);
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);

        $this->categoryLevelLoader = new DefaultCategoryLevelLoader(
            $this->cache,
            $this->eventDispatcher,
            $this->categoryRepository
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = DefaultCategoryLevelLoader::getSubscribedEvents();

        static::assertIsArray($events);
        static::assertArrayHasKey(CategoryEvents::CATEGORY_WRITTEN_EVENT, $events);
        static::assertSame('invalidateCache', $events[CategoryEvents::CATEGORY_WRITTEN_EVENT]);
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

        $this->categoryRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'category',
                0,
                $expectedCollection,
                null,
                $criteria,
                $this->salesChannelContext->getContext()
            ));

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
        $compressedData = CacheValueCompressor::compress($expectedCollection);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($compressedData);

        $eventThrown = false;
        $this->eventDispatcher->addListener(
            CategoryLevelLoaderCacheKeyEvent::class,
            function (CategoryLevelLoaderCacheKeyEvent $event) use ($rootId, $depth, $context, &$eventThrown): void {
                static::assertSame(
                    [
                        'rootId' => $rootId,
                        'depth' => $depth,
                        'salesChannelId' => 'sales-channel-id',
                        'languageId' => $context->getLanguageId(),
                    ],
                    $event->getParts()
                );

                $eventThrown = true;
            }
        );

        $result = $this->categoryLevelLoader->loadLevels(
            $rootId,
            $rootLevel,
            $this->salesChannelContext,
            $criteria,
            $depth
        );

        static::assertEquals($expectedCollection, $result);
        static::assertTrue($eventThrown);
    }
}
