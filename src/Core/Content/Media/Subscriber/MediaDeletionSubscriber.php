<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use League\Flysystem\Visibility;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Event\MediaThumbnailDeletedEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\Message\DeleteFileHandler;
use Shopware\Core\Content\Media\Message\DeleteFileMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
class MediaDeletionSubscriber implements EventSubscriberInterface
{
    final public const SYNCHRONE_FILE_DELETE = 'synchrone-file-delete';

    /**
     * @internal
     *
     * @param EntityRepository<MediaThumbnailCollection> $thumbnailRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly EntityRepository $thumbnailRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly DeleteFileHandler $deleteFileHandler,
        private readonly Connection $connection,
        private readonly EntityRepository $mediaRepository,
        private readonly bool $remoteThumbnailsEnable = false
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityDeleteEvent::class => 'beforeDelete',
        ];
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        /** @var array<string> $affected */
        $affected = array_values($event->getIds(MediaThumbnailDefinition::ENTITY_NAME));
        if (!empty($affected)) {
            $this->handleThumbnailDeletion($event, $affected, $event->getContext());
        }

        /** @var array<string> $affected */
        $affected = array_values($event->getIds(MediaFolderDefinition::ENTITY_NAME));
        if (!empty($affected)) {
            $this->handleFolderDeletion($affected, $event->getContext());
        }

        /** @var array<string> $affected */
        $affected = array_values($event->getIds(MediaDefinition::ENTITY_NAME));
        if (!empty($affected)) {
            $this->handleMediaDeletion($affected, $event->getContext());
        }
    }

    /**
     * @param array<string> $affected
     */
    private function handleMediaDeletion(array $affected, Context $context): void
    {
        $media = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): MediaCollection => $this->mediaRepository->search(new Criteria($affected), $context)->getEntities());

        $privatePaths = [];
        $publicPaths = [];
        $thumbnails = [];

        foreach ($media as $mediaEntity) {
            if (!$mediaEntity->hasFile()) {
                continue;
            }

            if ($mediaEntity->isPrivate()) {
                $privatePaths[] = $mediaEntity->getPath();
            } else {
                $publicPaths[] = $mediaEntity->getPath();
            }

            if ($this->remoteThumbnailsEnable || !$mediaEntity->getThumbnails()) {
                continue;
            }

            foreach ($mediaEntity->getThumbnails()->getIds() as $id) {
                $thumbnails[] = ['id' => $id];
            }
        }

        $this->performFileDelete($context, $publicPaths, Visibility::PUBLIC);
        $this->performFileDelete($context, $privatePaths, Visibility::PRIVATE);

        if ($this->remoteThumbnailsEnable) {
            return;
        }

        $this->thumbnailRepository->delete($thumbnails, $context);
    }

    /**
     * @param array<string> $affected
     */
    private function handleFolderDeletion(array $affected, Context $context): void
    {
        $ids = $this->fetchChildrenIds($affected);

        if (empty($ids)) {
            return;
        }

        $media = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id FROM media WHERE media_folder_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        if (empty($media)) {
            return;
        }

        $this->mediaRepository->delete($media, $context);
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function fetchChildrenIds(array $ids): array
    {
        $children = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM media_folder WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        if (empty($children)) {
            return \array_merge($ids, $children);
        }

        $nested = $this->fetchChildrenIds($children);

        $children = [...$children, ...$nested];

        return [...$ids, ...$children, ...$nested];
    }

    /**
     * @param array<string> $affected
     */
    private function handleThumbnailDeletion(EntityDeleteEvent $event, array $affected, Context $context): void
    {
        $privatePaths = [];
        $publicPaths = [];

        $thumbnails = $this->getThumbnails($affected, $context);

        foreach ($thumbnails as $thumbnail) {
            $media = $thumbnail->getMedia();
            if ($media === null) {
                continue;
            }

            if ($media->isPrivate()) {
                $privatePaths[] = $thumbnail->getPath();
            } else {
                $publicPaths[] = $thumbnail->getPath();
            }
        }

        $this->performFileDelete($context, $privatePaths, Visibility::PRIVATE);
        $this->performFileDelete($context, $publicPaths, Visibility::PUBLIC);

        $event->addSuccess(function () use ($thumbnails, $context): void {
            $this->dispatcher->dispatch(new MediaThumbnailDeletedEvent($thumbnails, $context), MediaThumbnailDeletedEvent::EVENT_NAME);
        });
    }

    /**
     * @param array<string> $ids
     */
    private function getThumbnails(array $ids, Context $context): MediaThumbnailCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addFilter(new EqualsAnyFilter('media_thumbnail.id', $ids));

        return $this->thumbnailRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param list<string> $paths
     */
    private function performFileDelete(Context $context, array $paths, string $visibility): void
    {
        if (\count($paths) <= 0) {
            return;
        }

        if ($context->hasState(self::SYNCHRONE_FILE_DELETE)) {
            $this->deleteFileHandler->__invoke(new DeleteFileMessage($paths, $visibility));

            return;
        }

        $this->messageBus->dispatch(new DeleteFileMessage($paths, $visibility));
    }
}
