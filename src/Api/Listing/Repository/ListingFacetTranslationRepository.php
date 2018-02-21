<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Repository;

use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\AggregationResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntityAggregatorInterface;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Listing\Collection\ListingFacetTranslationBasicCollection;
use Shopware\Api\Listing\Collection\ListingFacetTranslationDetailCollection;
use Shopware\Api\Listing\Definition\ListingFacetTranslationDefinition;
use Shopware\Api\Listing\Event\ListingFacetTranslation\ListingFacetTranslationAggregationResultLoadedEvent;
use Shopware\Api\Listing\Event\ListingFacetTranslation\ListingFacetTranslationBasicLoadedEvent;
use Shopware\Api\Listing\Event\ListingFacetTranslation\ListingFacetTranslationDetailLoadedEvent;
use Shopware\Api\Listing\Event\ListingFacetTranslation\ListingFacetTranslationIdSearchResultLoadedEvent;
use Shopware\Api\Listing\Event\ListingFacetTranslation\ListingFacetTranslationSearchResultLoadedEvent;
use Shopware\Api\Listing\Struct\ListingFacetTranslationSearchResult;
use Shopware\Context\Struct\ShopContext;
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

    public function search(Criteria $criteria, ShopContext $context): ListingFacetTranslationSearchResult
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

    public function aggregate(Criteria $criteria, ShopContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(ListingFacetTranslationDefinition::class, $criteria, $context);

        $event = new ListingFacetTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ShopContext $context): IdSearchResult
    {
        $result = $this->searcher->search(ListingFacetTranslationDefinition::class, $criteria, $context);

        $event = new ListingFacetTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ShopContext $context): ListingFacetTranslationBasicCollection
    {
        /** @var ListingFacetTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(ListingFacetTranslationDefinition::class, $ids, $context);

        $event = new ListingFacetTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ShopContext $context): ListingFacetTranslationDetailCollection
    {
        /** @var ListingFacetTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(ListingFacetTranslationDefinition::class, $ids, $context);

        $event = new ListingFacetTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(ListingFacetTranslationDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(ListingFacetTranslationDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(ListingFacetTranslationDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->delete(ListingFacetTranslationDefinition::class, $ids, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
