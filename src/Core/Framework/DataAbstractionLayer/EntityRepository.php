<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Adapter\Database\ReplicaConnection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\Profiler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @final tag:v6.5.0
 */
class EntityRepository implements EntityRepositoryInterface
{
    private EntityReaderInterface $reader;

    private EntitySearcherInterface $searcher;

    private EntityAggregatorInterface $aggregator;

    private EventDispatcherInterface $eventDispatcher;

    private VersionManager $versionManager;

    private EntityDefinition $definition;

    private ?EntityLoadedEventFactory $eventFactory = null;

    /**
     * @internal
     *
     * @deprecated tag:v6.5.0 - parameter $eventFactory will be required
     */
    public function __construct(
        EntityDefinition $definition,
        EntityReaderInterface $reader,
        VersionManager $versionManager,
        EntitySearcherInterface $searcher,
        EntityAggregatorInterface $aggregator,
        EventDispatcherInterface $eventDispatcher,
        ?EntityLoadedEventFactory $eventFactory = null
    ) {
        $this->reader = $reader;
        $this->searcher = $searcher;
        $this->aggregator = $aggregator;
        $this->eventDispatcher = $eventDispatcher;
        $this->versionManager = $versionManager;
        $this->definition = $definition;

        if ($eventFactory !== null) {
            $this->eventFactory = $eventFactory;
        } else {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                sprintf('EntityRepository constructor for definition %s requires the event factory as required 7th parameter in v6.5.0.0', $definition->getEntityName())
            );
        }
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed, inject entity loaded event factory in __construct
     */
    public function setEntityLoadedEventFactory(EntityLoadedEventFactory $eventFactory): void
    {
        if (isset($this->eventFactory)) {
            return;
        }

        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            sprintf('Repository for definition %s requires the event factory as __construct parameter', $this->definition->getEntityName())
        );

        $this->eventFactory = $eventFactory;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        if (!$criteria->getTitle()) {
            return $this->_search($criteria, $context);
        }

        return Profiler::trace($criteria->getTitle(), function () use ($criteria, $context) {
            return $this->_search($criteria, $context);
        }, 'repository');
    }

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        $criteria = clone $criteria;

        $result = $this->aggregator->aggregate($this->definition, $criteria, $context);

        $event = new EntityAggregationResultLoadedEvent($this->definition, $result, $context);
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $criteria = clone $criteria;

        $this->eventDispatcher->dispatch(new EntitySearchedEvent($criteria, $this->definition, $context));

        $result = $this->searcher->search($this->definition, $criteria, $context);

        $event = new EntityIdSearchResultLoadedEvent($this->definition, $result);
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }

    /**
     * @param array<array<string, mixed|null>> $data
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        ReplicaConnection::ensurePrimary();

        $affected = $this->versionManager->update($this->definition, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch($event);

        return $event;
    }

    /**
     * @param array<array<string, mixed|null>> $data
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        ReplicaConnection::ensurePrimary();

        $affected = $this->versionManager->upsert($this->definition, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch($event);

        return $event;
    }

    /**
     * @param array<array<string, mixed|null>> $data
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        ReplicaConnection::ensurePrimary();

        $affected = $this->versionManager->insert($this->definition, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch($event);

        return $event;
    }

    /**
     * @param array<array<string, mixed|null>> $ids
     */
    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        ReplicaConnection::ensurePrimary();

        $affected = $this->versionManager->delete($this->definition, $ids, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithDeletedEvents($affected->getDeleted(), $context, $affected->getNotFound());

        if ($affected->getWritten()) {
            $updates = EntityWrittenContainerEvent::createWithWrittenEvents($affected->getWritten(), $context, []);

            if ($updates->getEvents() !== null) {
                $event->addEvent(...$updates->getEvents());
            }
        }

        $this->eventDispatcher->dispatch($event);

        return $event;
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        ReplicaConnection::ensurePrimary();

        if (!$this->definition->isVersionAware()) {
            throw new \RuntimeException(sprintf('Entity %s is not version aware', $this->definition->getEntityName()));
        }

        return $this->versionManager->createVersion($this->definition, $id, WriteContext::createFromContext($context), $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        ReplicaConnection::ensurePrimary();

        if (!$this->definition->isVersionAware()) {
            throw new \RuntimeException(sprintf('Entity %s is not version aware', $this->definition->getEntityName()));
        }
        $this->versionManager->merge($versionId, WriteContext::createFromContext($context));
    }

    public function clone(string $id, Context $context, ?string $newId = null, ?CloneBehavior $behavior = null): EntityWrittenContainerEvent
    {
        ReplicaConnection::ensurePrimary();

        $newId = $newId ?? Uuid::randomHex();
        if (!Uuid::isValid($newId)) {
            throw new InvalidUuidException($newId);
        }

        $affected = $this->versionManager->clone(
            $this->definition,
            $id,
            $newId,
            $context->getVersionId(),
            WriteContext::createFromContext($context),
            $behavior ?? new CloneBehavior()
        );

        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, [], true);
        $this->eventDispatcher->dispatch($event);

        return $event;
    }

    private function read(Criteria $criteria, Context $context): EntityCollection
    {
        $criteria = clone $criteria;

        $entities = $this->reader->read($this->definition, $criteria, $context);

        if ($this->eventFactory === null) {
            throw new \RuntimeException('Event loaded factory was not injected');
        }

        if ($criteria->getFields() === []) {
            $event = $this->eventFactory->create($entities->getElements(), $context);
        } else {
            $event = $this->eventFactory->createPartial($entities->getElements(), $context);
        }

        $this->eventDispatcher->dispatch($event);

        return $entities;
    }

    private function _search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $criteria = clone $criteria;
        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        if (!RepositorySearchDetector::isSearchRequired($this->definition, $criteria)) {
            $this->eventDispatcher->dispatch(
                new EntitySearchedEvent($criteria, $this->definition, $context)
            );
            $entities = $this->read($criteria, $context);

            return new EntitySearchResult($this->definition->getEntityName(), $entities->count(), $entities, $aggregations, $criteria, $context);
        }

        $ids = $this->searchIds($criteria, $context);

        if (empty($ids->getIds())) {
            $collection = $this->definition->getCollectionClass();

            return new EntitySearchResult($this->definition->getEntityName(), $ids->getTotal(), new $collection(), $aggregations, $criteria, $context);
        }

        $readCriteria = $criteria->cloneForRead($ids->getIds());

        $entities = $this->read($readCriteria, $context);

        $search = $ids->getData();

        /** @var Entity $element */
        foreach ($entities as $element) {
            if (!\array_key_exists($element->getUniqueIdentifier(), $search)) {
                continue;
            }

            $data = $search[$element->getUniqueIdentifier()];
            unset($data['id']);

            if (empty($data)) {
                continue;
            }

            $element->addExtension('search', new ArrayEntity($data));
        }

        $result = new EntitySearchResult($this->definition->getEntityName(), $ids->getTotal(), $entities, $aggregations, $criteria, $context);
        $result->addState(...$ids->getStates());

        $event = new EntitySearchResultLoadedEvent($this->definition, $result);
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }
}
