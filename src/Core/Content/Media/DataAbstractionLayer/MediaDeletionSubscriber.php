<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use League\Flysystem\AdapterInterface;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Event\MediaThumbnailDeletedEvent;
use Shopware\Core\Content\Media\Message\DeleteFileHandler;
use Shopware\Core\Content\Media\Message\DeleteFileMessage;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MediaDeletionSubscriber implements EventSubscriberInterface
{
    public const SYNCHRONE_FILE_DELETE = 'synchrone-file-delete';

    private UrlGeneratorInterface $urlGenerator;

    private EventDispatcherInterface $dispatcher;

    private EntityRepositoryInterface $repository;

    private MessageBusInterface $messageBus;

    private DeleteFileHandler $deleteFileHandler;

    public function __construct(UrlGeneratorInterface $urlGenerator, EventDispatcherInterface $dispatcher, EntityRepositoryInterface $repository, MessageBusInterface $messageBus, DeleteFileHandler $deleteFileHandler)
    {
        $this->urlGenerator = $urlGenerator;
        $this->dispatcher = $dispatcher;
        $this->repository = $repository;
        $this->messageBus = $messageBus;
        $this->deleteFileHandler = $deleteFileHandler;
    }

    public static function getSubscribedEvents()
    {
        return [BeforeDeleteEvent::class => 'beforeDelete'];
    }

    public function beforeDelete(BeforeDeleteEvent $event): void
    {
        $affected = $event->getIds(MediaThumbnailDefinition::ENTITY_NAME);

        if (!empty($affected)) {
            $this->handleDeletedThumbnails($event, $affected, $event->getContext());
        }
    }

    private function handleDeletedThumbnails(BeforeDeleteEvent $event, array $affected, Context $context): void
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

        $thumbnailsSearch = $this->repository->search($criteria, $context);

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
