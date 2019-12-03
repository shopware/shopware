<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Event\MediaThumbnailDeletedEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MediaThumbnailIndexer implements IndexerInterface, EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

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
        EntityRepositoryInterface $mediaRepository,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        CacheClearer $cache
    ) {
        $this->connection = $connection;
        $this->mediaRepository = $mediaRepository;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
        $this->iteratorFactory = $iteratorFactory;
    }

    public static function getSubscribedEvents()
    {
        return [
            MediaThumbnailDeletedEvent::EVENT_NAME => 'onDelete',
        ];
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($this->mediaRepository->getDefinition(), null);

        while ($ids = $iterator->fetch()) {
            $this->updateThumbnailsRoField($ids, $context);
        }
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($this->mediaRepository->getDefinition(), $lastId);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        $this->updateThumbnailsRoField($ids, $context);

        return $iterator->getOffset();
    }

    public function onDelete(MediaThumbnailDeletedEvent $event): void
    {
        $mediaIds = [];
        foreach ($event->getThumbnails() as $thumbnail) {
            $mediaIds[] = $thumbnail->getMediaId();
        }

        if (count($mediaIds) === 0) {
            return;
        }

        $this->updateThumbnailsRoField($mediaIds, $event->getContext());
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        if ($thumbnailEvent = $event->getEventByEntityName(MediaDefinition::ENTITY_NAME)) {
            $this->updateThumbnailsRoField($thumbnailEvent->getIds(), $event->getContext());
        }
    }

    public static function getName(): string
    {
        return 'Swag.MediaThumbnailIndexer';
    }

    private function updateThumbnailsRoField(array $mediaIds, Context $context): void
    {
        $criteria = new Criteria($mediaIds);
        $criteria->addAssociation('thumbnails');

        $cacheIds = [];

        /** @var MediaCollection $medias */
        $medias = $this->mediaRepository->search($criteria, $context);
        foreach ($medias as $media) {
            $cacheIds[] = $this->cacheKeyGenerator
                ->getEntityTag($media->getId(), $this->mediaRepository->getDefinition());

            $this->connection->update(
                $this->mediaRepository->getDefinition()->getEntityName(),
                ['thumbnails_ro' => serialize($media->getThumbnails())],
                ['id' => Uuid::fromHexToBytes($media->getId())]
            );
        }

        if (count($cacheIds)) {
            $this->cache->invalidateTags($cacheIds);
        }
    }
}
