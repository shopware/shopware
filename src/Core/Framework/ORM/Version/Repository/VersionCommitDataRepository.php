<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Version\Repository;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Framework\ORM\RepositoryInterface;
use Shopware\Framework\ORM\Search\AggregatorResult;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Framework\ORM\Version\Collection\VersionCommitDataBasicCollection;
use Shopware\Framework\ORM\Version\Collection\VersionCommitDataDetailCollection;
use Shopware\Framework\ORM\Version\Definition\VersionCommitDataDefinition;
use Shopware\Framework\ORM\Version\Event\VersionCommitData\VersionCommitDataAggregationResultLoadedEvent;
use Shopware\Framework\ORM\Version\Event\VersionCommitData\VersionCommitDataBasicLoadedEvent;
use Shopware\Framework\ORM\Version\Event\VersionCommitData\VersionCommitDataDetailLoadedEvent;
use Shopware\Framework\ORM\Version\Event\VersionCommitData\VersionCommitDataIdSearchResultLoadedEvent;
use Shopware\Framework\ORM\Version\Event\VersionCommitData\VersionCommitDataSearchResultLoadedEvent;
use Shopware\Framework\ORM\Version\Service\VersionManager;
use Shopware\Framework\ORM\Version\Struct\VersionCommitDataSearchResult;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Framework\ORM\Write\WriteContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class VersionCommitDataRepository implements RepositoryInterface
{
    /**
     * @var EntityReaderInterface
     */
    private $reader;

    /**
     * @var EntitySearcherInterface
     */
    private $searcher;

    /**
     * @var EntityAggregatorInterface
     */
    private $aggregator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var \Shopware\Framework\ORM\Version\Service\VersionManager
     */
    private $versionManager;

    public function __construct(
       EntityReaderInterface $reader,
       VersionManager $versionManager,
       EntitySearcherInterface $searcher,
       EntityAggregatorInterface $aggregator,
       EventDispatcherInterface $eventDispatcher
   ) {
        $this->reader = $reader;
        $this->searcher = $searcher;
        $this->aggregator = $aggregator;
        $this->eventDispatcher = $eventDispatcher;
        $this->versionManager = $versionManager;
    }

    public function search(Criteria $criteria, ApplicationContext $context): VersionCommitDataSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = VersionCommitDataSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new VersionCommitDataSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, ApplicationContext $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(VersionCommitDataDefinition::class, $criteria, $context);

        $event = new VersionCommitDataAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ApplicationContext $context): IdSearchResult
    {
        $result = $this->searcher->search(VersionCommitDataDefinition::class, $criteria, $context);

        $event = new VersionCommitDataIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ApplicationContext $context): VersionCommitDataBasicCollection
    {
        /** @var VersionCommitDataBasicCollection $entities */
        $entities = $this->reader->readBasic(VersionCommitDataDefinition::class, $ids, $context);

        $event = new VersionCommitDataBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ApplicationContext $context): VersionCommitDataDetailCollection
    {
        /** @var VersionCommitDataDetailCollection $entities */
        $entities = $this->reader->readDetail(VersionCommitDataDefinition::class, $ids, $context);

        $event = new VersionCommitDataDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(VersionCommitDataDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(VersionCommitDataDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(VersionCommitDataDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(VersionCommitDataDefinition::class, $ids, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, ApplicationContext $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(VersionCommitDataDefinition::class, $id, WriteContext::createFromApplicationContext($context), $name, $versionId);
    }

    public function merge(string $versionId, ApplicationContext $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromApplicationContext($context));
    }
}
