<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use League\Flysystem\Visibility;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Event\MediaThumbnailDeletedEvent;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Message\DeleteFileHandler;
use Shopware\Core\Content\Media\Message\DeleteFileMessage;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('content')]
class MediaDeletionSubscriber implements EventSubscriberInterface
{
    final public const SYNCHRONE_FILE_DELETE = 'synchrone-file-delete';

    /**
     * @internal
     */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly EntityRepository $thumbnailRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly DeleteFileHandler $deleteFileHandler,
        private readonly Connection $connection,
        private readonly EntityRepository $mediaRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeDeleteEvent::class => 'beforeDelete',
            EntitySearchedEvent::class => 'securePrivateFolders',
        ];
    }

    public function securePrivateFolders(EntitySearchedEvent $event): void
    {
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        if ($event->getDefinition()->getEntityName() === MediaFolderDefinition::ENTITY_NAME) {
            $event->getCriteria()->addFilter(
                new MultiFilter('OR', [
                    new EqualsFilter('media_folder.configuration.private', false),
                    new EqualsFilter('media_folder.configuration.private', null),
                ])
            );

            return;
        }

        if ($event->getDefinition()->getEntityName() === MediaDefinition::ENTITY_NAME) {
            $event->getCriteria()->addFilter(
                new MultiFilter('OR', [
                    new EqualsFilter('private', false),
                    new MultiFilter('AND', [
                        new EqualsFilter('private', true),
                        new EqualsFilter('mediaFolder.defaultFolder.entity', 'product_download'),
                    ]),
                ])
            );
        }
    }

    public function beforeDelete(BeforeDeleteEvent $event): void
    {
        $affected = array_values($event->getIds(MediaThumbnailDefinition::ENTITY_NAME));
        if (!empty($affected)) {
            $this->handleThumbnailDeletion($event, $affected, $event->getContext());
        }

        $affected = array_values($event->getIds(MediaFolderDefinition::ENTITY_NAME));
        if (!empty($affected)) {
            $this->handleFolderDeletion($affected, $event->getContext());
        }

        $affected = array_values($event->getIds(MediaDefinition::ENTITY_NAME));
        if (!empty($affected)) {
            $this->handleMediaDeletion($affected, $event->getContext());
        }
    }

    /**
     * @param list<string> $affected
     */
    private function handleMediaDeletion(array $affected, Context $context): void
    {
        $media = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->mediaRepository->search(new Criteria($affected), $context));

        $privatePaths = [];
        $publicPaths = [];
        $thumbnails = [];

        /** @var MediaEntity $mediaEntity */
        foreach ($media as $mediaEntity) {
            if (!$mediaEntity->hasFile()) {
                continue;
            }

            if ($mediaEntity->isPrivate()) {
                $privatePaths[] = $this->urlGenerator->getRelativeMediaUrl($mediaEntity);
            } else {
                $publicPaths[] = $this->urlGenerator->getRelativeMediaUrl($mediaEntity);
            }

            if (!$mediaEntity->getThumbnails()) {
                continue;
            }

            foreach ($mediaEntity->getThumbnails()->getIds() as $id) {
                $thumbnails[] = ['id' => $id];
            }
        }

        $this->performFileDelete($context, $publicPaths, Visibility::PUBLIC);
        $this->performFileDelete($context, $privatePaths, Visibility::PRIVATE);

        $this->thumbnailRepository->delete($thumbnails, $context);
    }

    /**
     * @param list<string> $affected
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
            ['ids' => ArrayParameterType::STRING]
        );

        if (empty($media)) {
            return;
        }

        $this->mediaRepository->delete($media, $context);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function fetchChildrenIds(array $ids): array
    {
        $children = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM media_folder WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        if (empty($children)) {
            return \array_merge($ids, $children);
        }

        $nested = $this->fetchChildrenIds($children);

        $children = [...$children, ...$nested];

        return [...$ids, ...$children, ...$nested];
    }

    /**
     * @param list<string> $affected
     */
    private function handleThumbnailDeletion(BeforeDeleteEvent $event, array $affected, Context $context): void
    {
        $privatePaths = [];
        $publicPaths = [];

        $thumbnails = $this->getThumbnails($affected, $context);

        foreach ($thumbnails as $thumbnail) {
            if ($thumbnail->getMedia() === null) {
                continue;
            }

            if ($thumbnail->getMedia()->isPrivate()) {
                $privatePaths[] = $this->urlGenerator->getRelativeThumbnailUrl($thumbnail->getMedia(), $thumbnail);
            } else {
                $publicPaths[] = $this->urlGenerator->getRelativeThumbnailUrl($thumbnail->getMedia(), $thumbnail);
            }
        }

        $this->performFileDelete($context, $privatePaths, Visibility::PRIVATE);
        $this->performFileDelete($context, $publicPaths, Visibility::PUBLIC);

        $event->addSuccess(function () use ($thumbnails, $context): void {
            $this->dispatcher->dispatch(new MediaThumbnailDeletedEvent($thumbnails, $context), MediaThumbnailDeletedEvent::EVENT_NAME);
        });
    }

    /**
     * @param list<string> $ids
     */
    private function getThumbnails(array $ids, Context $context): MediaThumbnailCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addFilter(new EqualsAnyFilter('media_thumbnail.id', $ids));

        $thumbnailsSearch = $this->thumbnailRepository->search($criteria, $context);

        /** @var MediaThumbnailCollection $thumbnails */
        $thumbnails = $thumbnailsSearch->getEntities();

        return $thumbnails;
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
