<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Event\MediaThumbnailDeletedEvent;
use Shopware\Core\Content\Media\Message\DeleteFileMessage;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MediaThumbnailRepositoryDecorator implements EntityRepositoryInterface
{
    private UrlGeneratorInterface $urlGenerator;

    private EventDispatcherInterface $eventDispatcher;

    private EntityRepositoryInterface $innerRepo;

    private MessageBusInterface $messageBus;

    public function __construct(
        EntityRepositoryInterface $innerRepo,
        EventDispatcherInterface $eventDispatcher,
        UrlGeneratorInterface $urlGenerator,
        MessageBusInterface $messageBus
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->urlGenerator = $urlGenerator;
        $this->innerRepo = $innerRepo;
        $this->messageBus = $messageBus;
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        $thumbnails = $this->getThumbnailsByIds($ids, $context);

        return $this->deleteFromCollection($thumbnails, $context);
    }

    // Unchanged methods

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        return $this->innerRepo->aggregate($criteria, $context);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->innerRepo->searchIds($criteria, $context);
    }

    public function clone(string $id, Context $context, ?string $newId = null, ?CloneBehavior $behavior = null): EntityWrittenContainerEvent
    {
        return $this->innerRepo->clone($id, $context, $newId, $behavior);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->innerRepo->search($criteria, $context);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->innerRepo->update($data, $context);
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->innerRepo->upsert($data, $context);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->innerRepo->create($data, $context);
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->innerRepo->createVersion($id, $context, $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->innerRepo->merge($versionId, $context);
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->innerRepo->getDefinition();
    }

    private function getThumbnailsByIds(array $ids, Context $context): MediaThumbnailCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addFilter(new EqualsAnyFilter('media_thumbnail.id', $ids));

        $thumbnailsSearch = $this->search($criteria, $context);

        /** @var MediaThumbnailCollection $thumbnails */
        $thumbnails = $thumbnailsSearch->getEntities();

        return $thumbnails;
    }

    private function deleteFromCollection(MediaThumbnailCollection $thumbnails, Context $context): EntityWrittenContainerEvent
    {
        if ($thumbnails->count() === 0) {
            $event = EntityWrittenContainerEvent::createWithDeletedEvents([], $context, []);
            $this->eventDispatcher->dispatch($event);

            return $event;
        }

        $thumbnailIds = [];
        $thumbnailPaths = [];

        foreach ($thumbnails as $thumbnail) {
            $thumbnailIds[] = [
                'id' => $thumbnail->getId(),
                'mediaId' => $thumbnail->getMediaId(),
            ];

            if ($thumbnail->getMedia() !== null) {
                $thumbnailPaths[] = $this->urlGenerator->getRelativeThumbnailUrl($thumbnail->getMedia(), $thumbnail);
            }
        }

        $deleteMsg = new DeleteFileMessage();
        $deleteMsg->setFiles($thumbnailPaths);
        $this->messageBus->dispatch($deleteMsg);

        $delete = $this->innerRepo->delete($thumbnailIds, $context);

        $event = new MediaThumbnailDeletedEvent($thumbnails, $context);
        $this->eventDispatcher->dispatch($event, $event::EVENT_NAME);

        return $delete;
    }
}
