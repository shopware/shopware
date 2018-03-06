<?php

namespace Shopware\Api\Product\Repository;

use Shopware\Api\Entity\Search\AggregationResult;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntityAggregatorInterface;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Api\Product\Event\ProductConfigurator\ProductConfiguratorSearchResultLoadedEvent;
use Shopware\Api\Product\Event\ProductConfigurator\ProductConfiguratorBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductConfigurator\ProductConfiguratorAggregationResultLoadedEvent;
use Shopware\Api\Product\Event\ProductConfigurator\ProductConfiguratorIdSearchResultLoadedEvent;
use Shopware\Api\Product\Struct\ProductConfiguratorSearchResult;
use Shopware\Api\Product\Definition\ProductConfiguratorDefinition;
use Shopware\Api\Product\Collection\ProductConfiguratorBasicCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


class ProductConfiguratorRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, ShopContext $context): ProductConfiguratorSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ProductConfiguratorSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new ProductConfiguratorSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, ShopContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(ProductConfiguratorDefinition::class, $criteria, $context);

        $event = new ProductConfiguratorAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ShopContext $context): IdSearchResult
    {
        $result = $this->searcher->search(ProductConfiguratorDefinition::class, $criteria, $context);

        $event = new ProductConfiguratorIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ShopContext $context): ProductConfiguratorBasicCollection
    {
        /** @var ProductConfiguratorBasicCollection $entities */
        $entities = $this->reader->readBasic(ProductConfiguratorDefinition::class, $ids, $context);

        $event = new ProductConfiguratorBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ShopContext $context): ProductConfiguratorBasicCollection
    {
     return $this->readBasic($ids, $context);
    }

    public function update(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(ProductConfiguratorDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(ProductConfiguratorDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(ProductConfiguratorDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->delete(ProductConfiguratorDefinition::class, $ids, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}