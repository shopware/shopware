<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Event\MediaThumbnailDeletedEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
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
     * @var TagAwareAdapter
     */
    private $cache;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $mediaRepository,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        TagAwareAdapter $cache
    ) {
        $this->connection = $connection;
        $this->mediaRepository = $mediaRepository;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
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

        $this->updateThumbnailsRoField(null, $context);
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
        if ($thumbnailEvent = $event->getEventByDefinition(MediaDefinition::class)) {
            $this->updateThumbnailsRoField($thumbnailEvent->getIds(), $event->getContext());
        }
    }

    private function updateThumbnailsRoField(?array $mediaIds, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('thumbnails');

        if ($mediaIds !== null) {
            $criteria->setIds($mediaIds);
        }

        $cacheIds = [];

        /** @var MediaCollection $medias */
        $medias = $this->mediaRepository->search($criteria, $context);
        foreach ($medias as $media) {
            $cacheIds[] = $this->cacheKeyGenerator->getEntityTag($media->getId(), MediaDefinition::class);

            $this->connection->update(
                MediaDefinition::getEntityName(),
                ['thumbnails_ro' => serialize($media->getThumbnails())],
                ['id' => Uuid::fromHexToBytes($media->getId())]
            );
        }

        if (count($cacheIds)) {
            $this->cache->invalidateTags($cacheIds);
        }
    }
}
