<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;

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
     * @var CacheClearer
     */
    private $cache;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    public function __construct(
        Connection $connection,
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $folderRepository,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        CacheClearer $cache
    ) {
        $this->connection = $connection;
        $this->folderRepository = $folderRepository;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
        $this->iteratorFactory = $iteratorFactory;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($this->folderRepository->getDefinition());
        $iterator->getQuery()->andWhere('use_parent_configuration = :useParent')
            ->setParameter('useParent', false);

        while ($ids = $iterator->fetch()) {
            $folders = $this->folderRepository->search(new Criteria($ids), $context);

            foreach ($folders as $folder) {
                $this->updateChildren($folder->getId(), $folder->getConfigurationId(), $context);
            }
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $entityWrittenEvent = $event->getEventByEntityName(MediaFolderDefinition::ENTITY_NAME);
        if ($entityWrittenEvent) {
            $this->updateConfigOnRefresh($entityWrittenEvent);
        }
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($this->folderRepository->getDefinition(), $lastId);
        $iterator->getQuery()
            ->andWhere('use_parent_configuration = :useParent')
            ->setParameter('useParent', false);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $folders = $this->folderRepository->search(new Criteria($ids), $context);

        foreach ($folders as $folder) {
            $this->updateChildren($folder->getId(), $folder->getConfigurationId(), $context);
        }

        return $iterator->getOffset();
    }

    public static function getName(): string
    {
        return 'Swag.MediaFolderConfigIndexer';
    }

    private function updateConfigOnRefresh(EntityWrittenEvent $event): void
    {
        foreach ($event->getPayloads() as $update) {
            if (!(array_key_exists('parentId', $update) && $update['parentId'] !== null) && !array_key_exists('configurationId', $update)) {
                continue;
            }

            if (array_key_exists('parentId', $update) && !array_key_exists('configurationId', $update)) {
                $folders = $this->folderRepository->search(new Criteria([$update['id'], $update['parentId']]), $event->getContext());
                $child = $folders->get($update['id']);
                $parent = $folders->get($update['parentId']);

                if (!$child->getUseParentConfiguration()) {
                    continue;
                }

                $this->updateSelfAndChildren($update['id'], $parent->getConfigurationId(), $event->getContext());

                continue;
            }

            $this->updateChildren($update['id'], $update['configurationId'], $event->getContext());
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
}
