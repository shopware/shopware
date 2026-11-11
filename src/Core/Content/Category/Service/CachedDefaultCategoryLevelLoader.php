<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
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
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CategoryEvents::CATEGORY_WRITTEN_EVENT => 'onCategoryWritten',
            CategoryEvents::CATEGORY_TRANSLATION_WRITTEN_EVENT => 'onCategoryTranslationWritten',
            CategoryEvents::CATEGORY_TRANSLATION_DELETED_EVENT => 'onCategoryTranslationDeleted',
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

    public function onCategoryWritten(EntityWrittenEvent $event): void
    {
        $categoryIds = [];

        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();

            // results without payload only mark the category as parent of another write, e.g. a product assignment
            if ($payload === []) {
                continue;
            }

            if ($result->getOperation() !== EntityWriteResult::OPERATION_INSERT && \array_key_exists('parentId', $payload)) {
                // the category may have left another navigation tree, whose root is not known anymore
                $this->invalidateCache();

                return;
            }

            $categoryIds[] = $result->getPrimaryKey();

            if (\is_string($payload['parentId'] ?? null)) {
                $categoryIds[] = $payload['parentId'];
            }
        }

        $this->invalidateTrees($categoryIds);
    }

    public function onCategoryTranslationWritten(EntityWrittenEvent $event): void
    {
        $categoryIds = [];

        foreach ($event->getWriteResults() as $result) {
            $primaryKey = $result->getPrimaryKey();

            if (\is_array($primaryKey) && isset($primaryKey['categoryId'])) {
                $categoryIds[] = $primaryKey['categoryId'];
            }
        }

        $this->invalidateTrees($categoryIds);
    }

    public function onCategoryTranslationDeleted(EntityDeletedEvent $event): void
    {
        $this->onCategoryTranslationWritten($event);
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

        $fresh = null;

        $compressed = $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($rootId, $rootLevel, $context, $criteria, $depth, &$fresh): string {
                $item->tag([self::CACHE_TAG, self::buildTreeTag($rootId)]);

                $fresh = $this->inner->loadLevels($rootId, $rootLevel, $context, $criteria, $depth);

                return CacheValueCompressor::compress($fresh);
            }
        );

        // the levels were built in this call, return them directly instead of
        // uncompressing the cache payload that was just compressed from them
        if ($fresh instanceof CategoryCollection) {
            return $fresh;
        }

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

    private static function buildTreeTag(string $rootId): string
    {
        return self::CACHE_TAG . '-' . $rootId;
    }

    /**
     * Invalidates every cached navigation tree that contains one of the categories:
     * the trees whose root is the category itself or one of its ancestors.
     *
     * @param array<mixed> $categoryIds
     */
    private function invalidateTrees(array $categoryIds): void
    {
        $categoryIds = array_values(array_unique(array_filter($categoryIds, static fn (mixed $id): bool => \is_string($id) && Uuid::isValid($id))));

        if ($categoryIds === []) {
            return;
        }

        $paths = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), path FROM category WHERE id IN (:ids) AND version_id = :versionId',
            ['ids' => Uuid::fromHexToBytesList($categoryIds), 'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $treeIds = $categoryIds;
        foreach ($paths as $path) {
            if (\is_string($path)) {
                $treeIds = [...$treeIds, ...array_filter(explode('|', $path))];
            }
        }

        $this->cache->invalidateTags(array_map(self::buildTreeTag(...), array_values(array_unique($treeIds))));
    }
}
