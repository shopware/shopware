<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Message\DeleteFileMessage;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MediaRepositoryDecorator implements EntityRepositoryInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $innerRepo;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var EntityRepositoryInterface
     */
    private $thumbnailRepository;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    public function __construct(
        EntityRepositoryInterface $innerRepo,
        EventDispatcherInterface $eventDispatcher,
        UrlGeneratorInterface $urlGenerator,
        EntityRepositoryInterface $thumbnailRepository,
        MessageBusInterface $messageBus
    ) {
        $this->innerRepo = $innerRepo;
        $this->eventDispatcher = $eventDispatcher;
        $this->urlGenerator = $urlGenerator;
        $this->thumbnailRepository = $thumbnailRepository;
        $this->messageBus = $messageBus;
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        $affectedMedia = $this->search(new Criteria($this->getRawIds($ids)), $context);

        if ($affectedMedia->count() === 0) {
            $event = EntityWrittenContainerEvent::createWithDeletedEvents([], $context, []);
            $this->eventDispatcher->dispatch($event);

            return $event;
        }

        $filesToDelete = [];
        $thumbnailsToDelete = [];

        /** @var MediaEntity $mediaEntity */
        foreach ($affectedMedia as $mediaEntity) {
            if (!$mediaEntity->hasFile()) {
                continue;
            }
            $filesToDelete[] = $this->urlGenerator->getRelativeMediaUrl($mediaEntity);
            $thumbnailsToDelete = array_merge($thumbnailsToDelete, $mediaEntity->getThumbnails()->getIds());
        }

        $deleteMsg = new DeleteFileMessage();
        $deleteMsg->setFiles($filesToDelete);
        $this->messageBus->dispatch($deleteMsg);

        $this->thumbnailRepository->delete($thumbnailsToDelete, $context);

        return $this->innerRepo->delete($ids, $context);
    }

    // Unchanged methods

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        return $this->innerRepo->aggregate($criteria, $context);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        if ($context->getScope() !== Context::SYSTEM_SCOPE) {
            $criteria->addFilter(new EqualsFilter('private', false));
        }

        return $this->innerRepo->searchIds($criteria, $context);
    }

    public function clone(string $id, Context $context, ?string $newId = null): EntityWrittenContainerEvent
    {
        return $this->innerRepo->clone($id, $context, $newId);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        if ($context->getScope() !== Context::SYSTEM_SCOPE) {
            $criteria->addFilter(new EqualsFilter('private', false));
        }

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

    private function getRawIds(array $ids)
    {
        return array_column($ids, 'id');
    }
}
