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
use Shopware\Product\Collection\ProductManufacturerTranslationBasicCollection;
use Shopware\Product\Collection\ProductManufacturerTranslationDetailCollection;
use Shopware\Product\Definition\ProductManufacturerTranslationDefinition;
use Shopware\Product\Event\ProductManufacturerTranslation\ProductManufacturerTranslationAggregationResultLoadedEvent;
use Shopware\Product\Event\ProductManufacturerTranslation\ProductManufacturerTranslationBasicLoadedEvent;
use Shopware\Product\Event\ProductManufacturerTranslation\ProductManufacturerTranslationDetailLoadedEvent;
use Shopware\Product\Event\ProductManufacturerTranslation\ProductManufacturerTranslationSearchResultLoadedEvent;
use Shopware\Product\Event\ProductManufacturerTranslation\ProductManufacturerTranslationUuidSearchResultLoadedEvent;
use Shopware\Product\Struct\ProductManufacturerTranslationSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductManufacturerTranslationRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): ProductManufacturerTranslationSearchResult
    {
        $uuids = $this->searchUuids($criteria, $context);

        $entities = $this->readBasic($uuids->getUuids(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ProductManufacturerTranslationSearchResult::createFromResults($uuids, $entities, $aggregations);

        $event = new ProductManufacturerTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(ProductManufacturerTranslationDefinition::class, $criteria, $context);

        $event = new ProductManufacturerTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        $result = $this->searcher->search(ProductManufacturerTranslationDefinition::class, $criteria, $context);

        $event = new ProductManufacturerTranslationUuidSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductManufacturerTranslationBasicCollection
    {
        /** @var ProductManufacturerTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(ProductManufacturerTranslationDefinition::class, $uuids, $context);

        $event = new ProductManufacturerTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductManufacturerTranslationDetailCollection
    {
        /** @var ProductManufacturerTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(ProductManufacturerTranslationDefinition::class, $uuids, $context);

        $event = new ProductManufacturerTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(ProductManufacturerTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(ProductManufacturerTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(ProductManufacturerTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
