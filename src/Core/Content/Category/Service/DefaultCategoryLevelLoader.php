<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
#[Package('discovery')]
class DefaultCategoryLevelLoader implements EventSubscriberInterface
{
    private const CACHE_TAG = 'category_level_loader';

    /**
     * @param SalesChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SalesChannelRepository $categoryRepository,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CategoryEvents::CATEGORY_WRITTEN_EVENT => 'invalidateCache',
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

        return $this->load(
            $rootId,
            $rootLevel,
            $context,
            $criteria,
            $depth,
        );
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
            return $this->load($rootId, $rootLevel, $context, $criteria, $depth);
        }

        $cacheMetadata = [
            ItemInterface::METADATA_TAGS => self::CACHE_TAG,
        ];

        $compressed = $this->cache->get(
            $cacheKey,
            fn () => CacheValueCompressor::compress($this->load($rootId, $rootLevel, $context, $criteria, $depth)),
            null,
            $cacheMetadata
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

    private function load(
        string $rootId,
        int $rootLevel,
        SalesChannelContext $context,
        Criteria $criteria,
        int $depth,
    ): CategoryCollection {
        $criteria->addFilter(new OrFilter(
            [
                new EqualsFilter('id', $rootId),
                new AndFilter([
                    new ContainsFilter('path', '|' . $rootId . '|'),
                    new RangeFilter('level', [
                        RangeFilter::GT => $rootLevel,
                        RangeFilter::LTE => $rootLevel + $depth + 1,
                    ]),
                ]),
            ]
        ));

        $criteria->addAssociation('media');

        $criteria->setLimit(null);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        /** @var CategoryCollection $levels */
        $levels = $this->categoryRepository->search($criteria, $context)->getEntities();

        $this->addVisibilityCounts($rootId, $rootLevel, $depth, $levels, $context);

        return $levels;
    }

    private function addVisibilityCounts(string $rootId, int $rootLevel, int $depth, CategoryCollection $levels, SalesChannelContext $context): void
    {
        $counts = [];
        foreach ($levels as $category) {
            if (!$category->getActive() || !$category->getVisible()) {
                continue;
            }

            $parentId = $category->getParentId();
            $counts[$parentId] ??= 0;
            ++$counts[$parentId];
        }
        foreach ($levels as $category) {
            $category->setVisibleChildCount($counts[$category->getId()] ?? 0);
        }

        // Fetch additional level of categories for counting visible children that are NOT included in the original query
        $criteria = new Criteria();
        $criteria->addFilter(
            new ContainsFilter('path', '|' . $rootId . '|'),
            new EqualsFilter('level', $rootLevel + $depth + 1),
            new EqualsFilter('active', true),
            new EqualsFilter('visible', true)
        );

        $criteria->addAggregation(
            new TermsAggregation('category-ids', 'parentId', null, null, new CountAggregation('visible-children-count', 'id'))
        );

        $termsResult = $this->categoryRepository
            ->aggregate($criteria, $context)
            ->get('category-ids');

        if (!($termsResult instanceof TermsResult)) {
            return;
        }

        foreach ($termsResult->getBuckets() as $bucket) {
            $key = $bucket->getKey();

            if ($key === null) {
                continue;
            }

            $parent = $levels->get($key);

            if ($parent instanceof CategoryEntity) {
                $parent->setVisibleChildCount($bucket->getCount());
            }
        }
    }
}
