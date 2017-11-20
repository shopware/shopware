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
use Shopware\Product\Collection\ProductManufacturerBasicCollection;
use Shopware\Product\Collection\ProductManufacturerDetailCollection;
use Shopware\Product\Definition\ProductManufacturerDefinition;
use Shopware\Product\Event\ProductManufacturer\ProductManufacturerAggregationResultLoadedEvent;
use Shopware\Product\Event\ProductManufacturer\ProductManufacturerBasicLoadedEvent;
use Shopware\Product\Event\ProductManufacturer\ProductManufacturerDetailLoadedEvent;
use Shopware\Product\Event\ProductManufacturer\ProductManufacturerSearchResultLoadedEvent;
use Shopware\Product\Event\ProductManufacturer\ProductManufacturerUuidSearchResultLoadedEvent;
use Shopware\Product\Struct\ProductManufacturerSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductManufacturerRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): ProductManufacturerSearchResult
    {
        $uuids = $this->searchUuids($criteria, $context);

        $entities = $this->readBasic($uuids->getUuids(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ProductManufacturerSearchResult::createFromResults($uuids, $entities, $aggregations);

        $event = new ProductManufacturerSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(ProductManufacturerDefinition::class, $criteria, $context);

        $event = new ProductManufacturerAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        $result = $this->searcher->search(ProductManufacturerDefinition::class, $criteria, $context);

        $event = new ProductManufacturerUuidSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductManufacturerBasicCollection
    {
        /** @var ProductManufacturerBasicCollection $entities */
        $entities = $this->reader->readBasic(ProductManufacturerDefinition::class, $uuids, $context);

        $event = new ProductManufacturerBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductManufacturerDetailCollection
    {
        /** @var ProductManufacturerDetailCollection $entities */
        $entities = $this->reader->readDetail(ProductManufacturerDefinition::class, $uuids, $context);

        $event = new ProductManufacturerDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(ProductManufacturerDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(ProductManufacturerDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(ProductManufacturerDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
