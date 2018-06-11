<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\AggregatorResult;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Core\Framework\ORM\Search\IdSearchResult;
use Shopware\Core\Framework\ORM\Version\Service\VersionManager;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\ORM\Write\WriteContext;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Collection\ListingFacetTranslationBasicCollection;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Collection\ListingFacetTranslationDetailCollection;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Event\ListingFacetTranslationAggregationResultLoadedEvent;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Event\ListingFacetTranslationBasicLoadedEvent;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Event\ListingFacetTranslationDetailLoadedEvent;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Event\ListingFacetTranslationIdSearchResultLoadedEvent;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Event\ListingFacetTranslationSearchResultLoadedEvent;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Struct\ListingFacetTranslationSearchResult;
use Shopware\Core\System\Listing\Definition\ListingFacetTranslationDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingFacetTranslationRepository implements RepositoryInterface
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
     * @var \Shopware\Core\Framework\ORM\Version\Service\VersionManager
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

    public function search(Criteria $criteria, Context $context): ListingFacetTranslationSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ListingFacetTranslationSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new ListingFacetTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, Context $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(ListingFacetTranslationDefinition::class, $criteria, $context);

        $event = new ListingFacetTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $result = $this->searcher->search(ListingFacetTranslationDefinition::class, $criteria, $context);

        $event = new ListingFacetTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, Context $context): ListingFacetTranslationBasicCollection
    {
        /** @var ListingFacetTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(ListingFacetTranslationDefinition::class, $ids, $context);

        $event = new ListingFacetTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, Context $context): ListingFacetTranslationDetailCollection
    {
        /** @var \Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Collection\ListingFacetTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(ListingFacetTranslationDefinition::class, $ids, $context);

        $event = new ListingFacetTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->update(ListingFacetTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->upsert(ListingFacetTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->insert(ListingFacetTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->delete(ListingFacetTranslationDefinition::class, $ids, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(ListingFacetTranslationDefinition::class, $id, WriteContext::createFromContext($context), $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromContext($context));
    }
}
