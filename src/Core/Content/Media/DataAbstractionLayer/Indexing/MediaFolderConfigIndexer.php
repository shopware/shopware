<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfigurationMediaThumbnailSize\MediaFolderConfigurationMediaThumbnailSizeDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class MediaFolderConfigIndexer implements IndexerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $folderRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var TagAwareAdapter
     */
    private $cache;

    /**
     * @var EntityRepositoryInterface
     */
    private $folderConfigRepository;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $folderRepository,
        EntityRepositoryInterface $folderConfigRepository,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        TagAwareAdapter $cache
    ) {
        $this->connection = $connection;
        $this->folderRepository = $folderRepository;
        $this->folderConfigRepository = $folderConfigRepository;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        /** @var MediaFolderEntity $folder */
        foreach ($this->fetchFoldersWithOwnConfig($context) as $folder) {
            $this->updateChildren($folder->getId(), $folder->getConfigurationId(), $context);
        }

        $this->updateSizesRoField(null, $context);
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $entityWrittenEvent = $event->getEventByDefinition(MediaFolderDefinition::class);
        if ($entityWrittenEvent) {
            $this->updateConfigOnRefresh($entityWrittenEvent);
        }

        if ($sizesEvent = $event->getEventByDefinition(MediaFolderConfigurationMediaThumbnailSizeDefinition::class)) {
            $configIds = array_column($sizesEvent->getIds(), 'media_folder_configuration_id');
            $this->updateSizesRoField($configIds, $event->getContext());
        }
    }

    private function updateConfigOnRefresh(EntityWrittenEvent $event): void
    {
        foreach ($event->getPayloads() as $update) {
            if (!(array_key_exists('parentId', $update) && $update['parentId'] !== null) && !array_key_exists('configurationId', $update)) {
                continue;
            } elseif (array_key_exists('parentId', $update) && !array_key_exists('configurationId', $update)) {
                $folders = $this->folderRepository->search(new Criteria([$update['id'], $update['parentId']]), $event->getContext());
                $child = $folders->get($update['id']);
                $parent = $folders->get($update['parentId']);

                if (!$child->getUseParentConfiguration()) {
                    continue;
                }

                $this->updateSelfAndChildren($update['id'], $parent->getConfigurationId(), $event->getContext());
            } else {
                $this->updateChildren($update['id'], $update['configurationId'], $event->getContext());
            }
        }
    }

    private function updateSelfAndChildren(string $folderId, string $configId, Context $context): void
    {
        $ids = $this->getChildIds($folderId, $context);
        $ids[] = Uuid::fromHexToBytes($folderId);

        $this->updateConfigForFolders($ids, $configId);
    }

    private function updateChildren(string $folderId, string $configId, Context $context): void
    {
        $ids = $this->getChildIds($folderId, $context);

        $this->updateConfigForFolders($ids, $configId);
    }

    private function getChildIds(string $folderId, Context $context): array
    {
        return $this->fetchChildren([$folderId], $context)
            ->map(function (MediaFolderEntity $folder) {
                return Uuid::fromHexToBytes($folder->getId());
            });
    }

    private function fetchChildren(array $parentIds, Context $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('media_folder.useParentConfiguration', true),
                    new EqualsAnyFilter('media_folder.parentId', $parentIds),
                ]
            )
        );

        $children = $this->folderRepository->search($criteria, $context);

        if ($children->getTotal() > 0) {
            $children->merge($this->fetchChildren($children->getIds(), $context));
        }

        return $children;
    }

    private function updateConfigForFolders(array $ids, string $configId): void
    {
        $this->connection->createQueryBuilder()
            ->update('media_folder')
            ->set('media_folder_configuration_id', ':configId')
            ->andWhere('id in (:ids)')
            ->setParameter('configId', Uuid::fromHexToBytes($configId))
            ->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY)
            ->execute();

        $tags = array_map(function ($id) {
            return $this->cacheKeyGenerator
                ->getEntityTag(Uuid::fromBytesToHex($id), $this->folderRepository->getDefinition());
        }, $ids);

        $this->cache->invalidateTags($tags);
    }

    private function fetchFoldersWithOwnConfig(Context $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.useParentConfiguration', false));

        return $this->folderRepository->search($criteria, $context)->getEntities();
    }

    private function updateSizesRoField(?array $configIds, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('mediaThumbnailSizes');

        if ($configIds !== null) {
            $criteria->setIds($configIds);
        }

        $cacheIds = [];

        /** @var MediaFolderConfigurationCollection $configs */
        $configs = $this->folderConfigRepository->search($criteria, $context);
        foreach ($configs as $config) {
            $cacheIds[] = $this->cacheKeyGenerator
                ->getEntityTag($config->getId(), $this->folderConfigRepository->getDefinition());

            $this->connection->update(
                $this->folderConfigRepository->getDefinition()->getEntityName(),
                ['media_thumbnail_sizes_ro' => serialize($config->getMediaThumbnailSizes())],
                ['id' => Uuid::fromHexToBytes($config->getId())]
            );
        }

        if (count($cacheIds)) {
            $this->cache->invalidateTags($cacheIds);
        }
    }
}
