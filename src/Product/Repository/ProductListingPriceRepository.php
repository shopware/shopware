<?php declare(strict_types=1);

namespace Shopware\Product\Repository;

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
use Shopware\Product\Collection\ProductListingPriceBasicCollection;
use Shopware\Product\Collection\ProductListingPriceDetailCollection;
use Shopware\Product\Definition\ProductListingPriceDefinition;
use Shopware\Product\Event\ProductListingPrice\ProductListingPriceAggregationResultLoadedEvent;
use Shopware\Product\Event\ProductListingPrice\ProductListingPriceBasicLoadedEvent;
use Shopware\Product\Event\ProductListingPrice\ProductListingPriceDetailLoadedEvent;
use Shopware\Product\Event\ProductListingPrice\ProductListingPriceSearchResultLoadedEvent;
use Shopware\Product\Event\ProductListingPrice\ProductListingPriceUuidSearchResultLoadedEvent;
use Shopware\Product\Struct\ProductListingPriceSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductListingPriceRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): ProductListingPriceSearchResult
    {
        $uuids = $this->searchUuids($criteria, $context);

        $entities = $this->readBasic($uuids->getUuids(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ProductListingPriceSearchResult::createFromResults($uuids, $entities, $aggregations);

        $event = new ProductListingPriceSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(ProductListingPriceDefinition::class, $criteria, $context);

        $event = new ProductListingPriceAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        $result = $this->searcher->search(ProductListingPriceDefinition::class, $criteria, $context);

        $event = new ProductListingPriceUuidSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductListingPriceBasicCollection
    {
        /** @var ProductListingPriceBasicCollection $entities */
        $entities = $this->reader->readBasic(ProductListingPriceDefinition::class, $uuids, $context);

        $event = new ProductListingPriceBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductListingPriceDetailCollection
    {
        /** @var ProductListingPriceDetailCollection $entities */
        $entities = $this->reader->readDetail(ProductListingPriceDefinition::class, $uuids, $context);

        $event = new ProductListingPriceDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(ProductListingPriceDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(ProductListingPriceDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(ProductListingPriceDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
