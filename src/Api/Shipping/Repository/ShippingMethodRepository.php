<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Repository;

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
use Shopware\Api\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Api\Shipping\Collection\ShippingMethodDetailCollection;
use Shopware\Api\Shipping\Definition\ShippingMethodDefinition;
use Shopware\Api\Shipping\Event\ShippingMethod\ShippingMethodAggregationResultLoadedEvent;
use Shopware\Api\Shipping\Event\ShippingMethod\ShippingMethodBasicLoadedEvent;
use Shopware\Api\Shipping\Event\ShippingMethod\ShippingMethodDetailLoadedEvent;
use Shopware\Api\Shipping\Event\ShippingMethod\ShippingMethodIdSearchResultLoadedEvent;
use Shopware\Api\Shipping\Event\ShippingMethod\ShippingMethodSearchResultLoadedEvent;
use Shopware\Api\Shipping\Struct\ShippingMethodSearchResult;
use Shopware\Context\Struct\ShopContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShippingMethodRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, ShopContext $context): ShippingMethodSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ShippingMethodSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new ShippingMethodSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, ShopContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(ShippingMethodDefinition::class, $criteria, $context);

        $event = new ShippingMethodAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ShopContext $context): IdSearchResult
    {
        $result = $this->searcher->search(ShippingMethodDefinition::class, $criteria, $context);

        $event = new ShippingMethodIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ShopContext $context): ShippingMethodBasicCollection
    {
        /** @var ShippingMethodBasicCollection $entities */
        $entities = $this->reader->readBasic(ShippingMethodDefinition::class, $ids, $context);

        $event = new ShippingMethodBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ShopContext $context): ShippingMethodDetailCollection
    {
        /** @var ShippingMethodDetailCollection $entities */
        $entities = $this->reader->readDetail(ShippingMethodDefinition::class, $ids, $context);

        $event = new ShippingMethodDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(ShippingMethodDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(ShippingMethodDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(ShippingMethodDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->delete(ShippingMethodDefinition::class, $ids, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
