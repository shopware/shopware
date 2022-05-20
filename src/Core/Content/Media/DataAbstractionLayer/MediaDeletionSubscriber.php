<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use League\Flysystem\AdapterInterface;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Event\MediaThumbnailDeletedEvent;
use Shopware\Core\Content\Media\Message\DeleteFileHandler;
use Shopware\Core\Content\Media\Message\DeleteFileMessage;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MediaDeletionSubscriber implements EventSubscriberInterface
{
    public const SYNCHRONE_FILE_DELETE = 'synchrone-file-delete';

    private Connection $connection;

    private UrlGeneratorInterface $urlGenerator;

    private EventDispatcherInterface $dispatcher;

    private EntityRepositoryInterface $thumbnailRepository;

    private MessageBusInterface $messageBus;

    private DeleteFileHandler $deleteFileHandler;

    private EntityRepositoryInterface $mediaRepository;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        EventDispatcherInterface $dispatcher,
        EntityRepositoryInterface $thumbnailRepository,
        MessageBusInterface $messageBus,
        DeleteFileHandler $deleteFileHandler,
        Connection $connection,
        EntityRepositoryInterface $mediaRepository
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->dispatcher = $dispatcher;
        $this->thumbnailRepository = $thumbnailRepository;
        $this->messageBus = $messageBus;
        $this->deleteFileHandler = $deleteFileHandler;
        $this->connection = $connection;
        $this->mediaRepository = $mediaRepository;
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
        if ($event->getDefinition()->getEntityName() !== MediaFolderDefinition::ENTITY_NAME) {
            return;
        }

        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        $event->getCriteria()->addFilter(
            new MultiFilter('OR', [
                new EqualsFilter('media_folder.configuration.private', false),
                new EqualsFilter('media_folder.configuration.private', null),
            ])
        );
    }

    public function beforeDelete(BeforeDeleteEvent $event): void
    {
        $affected = $event->getIds(MediaThumbnailDefinition::ENTITY_NAME);

        if (!empty($affected)) {
            $this->handleThumbnailDeletion($event, $affected, $event->getContext());
        }

        $affected = $event->getIds(MediaFolderDefinition::ENTITY_NAME);
        if (!empty($affected)) {
            $this->handleFolderDeletion($affected, $event->getContext());
        }
    }

    private function handleFolderDeletion(array $affected, Context $context): void
    {
        $ids = $this->fetchChildrenIds($affected);

        if (empty($ids)) {
            return;
        }

        $media = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id FROM media WHERE media_folder_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        if (empty($media)) {
            return;
        }

        $this->mediaRepository->delete($media, $context);
    }

    private function fetchChildrenIds(array $ids): array
    {
        $children = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM media_folder WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        if (empty($children)) {
            return \array_merge($ids, $children);
        }

        $nested = $this->fetchChildrenIds($children);

        $children = \array_merge($children, $nested);

        return \array_merge($ids, $children, $nested);
    }

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

        $this->performFileDelete($context, $privatePaths, AdapterInterface::VISIBILITY_PRIVATE);
        $this->performFileDelete($context, $publicPaths, AdapterInterface::VISIBILITY_PUBLIC);

        $event->addSuccess(function () use ($thumbnails, $context): void {
            $this->dispatcher->dispatch(new MediaThumbnailDeletedEvent($thumbnails, $context), MediaThumbnailDeletedEvent::EVENT_NAME);
        });
    }

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

    private function performFileDelete(Context $context, array $paths, string $visibility): void
    {
        if (\count($paths) <= 0) {
            return;
        }

        if ($context->hasState(self::SYNCHRONE_FILE_DELETE)) {
            $this->deleteFileHandler->handle(new DeleteFileMessage($paths, $visibility));

            return;
        }

        $this->messageBus->dispatch(new DeleteFileMessage($paths, $visibility));
    }
}
