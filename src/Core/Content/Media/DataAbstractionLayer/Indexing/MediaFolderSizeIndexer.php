<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfigurationMediaThumbnailSize\MediaFolderConfigurationMediaThumbnailSizeDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class MediaFolderSizeIndexer implements IndexerInterface
{
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
     * @var EntityRepositoryInterface
     */
    private $folderConfigRepository;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    public function __construct(
        Connection $connection,
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $folderConfigRepository,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        CacheClearer $cache
    ) {
        $this->connection = $connection;
        $this->folderConfigRepository = $folderConfigRepository;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
        $this->iteratorFactory = $iteratorFactory;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $iterator = $this->iteratorFactory->createIterator($this->folderConfigRepository->getDefinition());

        $context = Context::createDefaultContext();

        while ($ids = $iterator->fetch()) {
            $this->update($ids, $context);
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $writtenEvent = $sizesEvent = $event->getEventByEntityName(MediaFolderConfigurationMediaThumbnailSizeDefinition::class);

        if ($writtenEvent) {
            $configIds = array_column($sizesEvent->getIds(), 'media_folder_configuration_id');
            $this->update($configIds, $event->getContext());
        }
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $iterator = $this->iteratorFactory->createIterator($this->folderConfigRepository->getDefinition(), $lastId);

        $context = Context::createDefaultContext();

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $this->update($ids, $context);

        return $iterator->getOffset();
    }

    public static function getName(): string
    {
        return 'Swag.MediaFolderSizeIndexer';
    }

    private function update(array $ids, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('mediaThumbnailSizes');

        $criteria->setIds($ids);

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
