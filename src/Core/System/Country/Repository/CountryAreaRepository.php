<?php declare(strict_types=1);

namespace Shopware\System\Country\Repository;

use Shopware\System\Country\Collection\CountryAreaBasicCollection;
use Shopware\System\Country\Collection\CountryAreaDetailCollection;
use Shopware\System\Country\Definition\CountryAreaDefinition;
use Shopware\System\Country\Event\CountryArea\CountryAreaAggregationResultLoadedEvent;
use Shopware\System\Country\Event\CountryArea\CountryAreaBasicLoadedEvent;
use Shopware\System\Country\Event\CountryArea\CountryAreaDetailLoadedEvent;
use Shopware\System\Country\Event\CountryArea\CountryAreaIdSearchResultLoadedEvent;
use Shopware\System\Country\Event\CountryArea\CountryAreaSearchResultLoadedEvent;
use Shopware\System\Country\Struct\CountryAreaSearchResult;
use Shopware\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Framework\ORM\RepositoryInterface;
use Shopware\Framework\ORM\Search\AggregatorResult;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Framework\ORM\Write\WriteContext;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\ORM\Version\Service\VersionManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CountryAreaRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, ApplicationContext $context): CountryAreaSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = CountryAreaSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new CountryAreaSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, ApplicationContext $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(CountryAreaDefinition::class, $criteria, $context);

        $event = new CountryAreaAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ApplicationContext $context): IdSearchResult
    {
        $result = $this->searcher->search(CountryAreaDefinition::class, $criteria, $context);

        $event = new CountryAreaIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ApplicationContext $context): CountryAreaBasicCollection
    {
        /** @var CountryAreaBasicCollection $entities */
        $entities = $this->reader->readBasic(CountryAreaDefinition::class, $ids, $context);

        $event = new CountryAreaBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ApplicationContext $context): CountryAreaDetailCollection
    {
        /** @var CountryAreaDetailCollection $entities */
        $entities = $this->reader->readDetail(CountryAreaDefinition::class, $ids, $context);

        $event = new CountryAreaDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(CountryAreaDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(CountryAreaDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(CountryAreaDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(CountryAreaDefinition::class, $ids, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, ApplicationContext $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(CountryAreaDefinition::class, $id, WriteContext::createFromApplicationContext($context), $name, $versionId);
    }

    public function merge(string $versionId, ApplicationContext $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromApplicationContext($context));
    }
}
