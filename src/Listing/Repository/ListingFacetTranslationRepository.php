<?php declare(strict_types=1);

namespace Shopware\Listing\Repository;

use Shopware\Api\Read\EntityReaderInterface;
use Shopware\Api\RepositoryInterface;
use Shopware\Api\Search\AggregationResult;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\EntityAggregatorInterface;
use Shopware\Api\Search\EntitySearcherInterface;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Api\Write\EntityWriterInterface;
use Shopware\Api\Write\GenericWrittenEvent;
use Shopware\Api\Write\WriteContext;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Listing\Collection\ListingFacetTranslationBasicCollection;
use Shopware\Listing\Collection\ListingFacetTranslationDetailCollection;
use Shopware\Listing\Definition\ListingFacetTranslationDefinition;
use Shopware\Listing\Event\ListingFacetTranslation\ListingFacetTranslationAggregationResultLoadedEvent;
use Shopware\Listing\Event\ListingFacetTranslation\ListingFacetTranslationBasicLoadedEvent;
use Shopware\Listing\Event\ListingFacetTranslation\ListingFacetTranslationDetailLoadedEvent;
use Shopware\Listing\Event\ListingFacetTranslation\ListingFacetTranslationSearchResultLoadedEvent;
use Shopware\Listing\Event\ListingFacetTranslation\ListingFacetTranslationUuidSearchResultLoadedEvent;
use Shopware\Listing\Struct\ListingFacetTranslationSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingFacetTranslationRepository implements RepositoryInterface
{
    /**
     * @var EntityReaderInterface
     */
    private $reader;

    /**
     * @var EntityWriterInterface
     */
    private $writer;

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

    public function __construct(
        EntityReaderInterface $reader,
        EntityWriterInterface $writer,
        EntitySearcherInterface $searcher,
        EntityAggregatorInterface $aggregator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->reader = $reader;
        $this->writer = $writer;
        $this->searcher = $searcher;
        $this->aggregator = $aggregator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function search(Criteria $criteria, TranslationContext $context): ListingFacetTranslationSearchResult
    {
        $uuids = $this->searchUuids($criteria, $context);

        $entities = $this->readBasic($uuids->getUuids(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ListingFacetTranslationSearchResult::createFromResults($uuids, $entities, $aggregations);

        $event = new ListingFacetTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(ListingFacetTranslationDefinition::class, $criteria, $context);

        $event = new ListingFacetTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        $result = $this->searcher->search(ListingFacetTranslationDefinition::class, $criteria, $context);

        $event = new ListingFacetTranslationUuidSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $uuids, TranslationContext $context): ListingFacetTranslationBasicCollection
    {
        /** @var ListingFacetTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(ListingFacetTranslationDefinition::class, $uuids, $context);

        $event = new ListingFacetTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $uuids, TranslationContext $context): ListingFacetTranslationDetailCollection
    {
        /** @var ListingFacetTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(ListingFacetTranslationDefinition::class, $uuids, $context);

        $event = new ListingFacetTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(ListingFacetTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(ListingFacetTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(ListingFacetTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
