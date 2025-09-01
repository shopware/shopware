<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
#[Package('discovery')]
class CachedDefaultCategoryLevelLoader implements DefaultCategoryLevelLoaderInterface, EventSubscriberInterface
{
    private const CACHE_TAG = 'category_level_loader';

    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DefaultCategoryLevelLoaderInterface $inner,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CategoryEvents::CATEGORY_WRITTEN_EVENT => 'invalidateCache',
            CategoryEvents::CATEGORY_DELETED_EVENT => 'invalidateCache',
        ];
    }

    public function loadLevels(
        string $rootId,
        int $rootLevel,
        SalesChannelContext $context,
        Criteria $criteria,
        int $depth,
    ): CategoryCollection {
        if ($context->getSalesChannel()->getNavigationCategoryId() === $rootId) {
            return $this->cached(
                $rootId,
                $rootLevel,
                $context,
                $criteria,
                $depth,
            );
        }

        return $this->inner->loadLevels($rootId, $rootLevel, $context, $criteria, $depth);
    }

    public function invalidateCache(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG]);
    }

    private function cached(
        string $rootId,
        int $rootLevel,
        SalesChannelContext $context,
        Criteria $criteria,
        int $depth,
    ): CategoryCollection {
        $cacheKey = $this->getCacheKey($rootId, $context, $criteria, $depth);

        if ($cacheKey === null) {
            return $this->inner->loadLevels($rootId, $rootLevel, $context, $criteria, $depth);
        }

        $compressed = $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($rootId, $rootLevel, $context, $criteria, $depth): string {
                $item->tag(self::CACHE_TAG);

                return CacheValueCompressor::compress(
                    $this->inner->loadLevels($rootId, $rootLevel, $context, $criteria, $depth),
                );
            }
        );

        $categories = CacheValueCompressor::uncompress($compressed);
        \assert($categories instanceof CategoryCollection);

        return $categories;
    }

    private function getCacheKey(string $rootId, SalesChannelContext $context, Criteria $criteria, int $depth): ?string
    {
        $event = new CategoryLevelLoaderCacheKeyEvent(
            [
                'rootId' => $rootId,
                'depth' => $depth,
                'salesChannelId' => $context->getSalesChannelId(),
                'languageId' => $context->getContext()->getLanguageId(),
            ],
            $rootId,
            $depth,
            $context,
            $criteria,
        );

        $this->eventDispatcher->dispatch($event);

        if (!$event->shouldCache()) {
            return null;
        }

        return Hasher::hash($event->getParts());
    }
}
