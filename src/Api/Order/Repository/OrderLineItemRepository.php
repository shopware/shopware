<?php declare(strict_types=1);

namespace Shopware\Api\Order\Repository;

use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\AggregatorResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntityAggregatorInterface;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Order\Collection\OrderLineItemBasicCollection;
use Shopware\Api\Order\Collection\OrderLineItemDetailCollection;
use Shopware\Api\Order\Definition\OrderLineItemDefinition;
use Shopware\Api\Order\Event\OrderLineItem\OrderLineItemAggregationResultLoadedEvent;
use Shopware\Api\Order\Event\OrderLineItem\OrderLineItemBasicLoadedEvent;
use Shopware\Api\Order\Event\OrderLineItem\OrderLineItemDetailLoadedEvent;
use Shopware\Api\Order\Event\OrderLineItem\OrderLineItemIdSearchResultLoadedEvent;
use Shopware\Api\Order\Event\OrderLineItem\OrderLineItemSearchResultLoadedEvent;
use Shopware\Api\Order\Struct\OrderLineItemSearchResult;
use Shopware\Context\Struct\ShopContext;
use Shopware\Version\VersionManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderLineItemRepository implements RepositoryInterface
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
     * @var VersionManager
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

    public function search(Criteria $criteria, ShopContext $context): OrderLineItemSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = OrderLineItemSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new OrderLineItemSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, ShopContext $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(OrderLineItemDefinition::class, $criteria, $context);

        $event = new OrderLineItemAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ShopContext $context): IdSearchResult
    {
        $result = $this->searcher->search(OrderLineItemDefinition::class, $criteria, $context);

        $event = new OrderLineItemIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ShopContext $context): OrderLineItemBasicCollection
    {
        /** @var OrderLineItemBasicCollection $entities */
        $entities = $this->reader->readBasic(OrderLineItemDefinition::class, $ids, $context);

        $event = new OrderLineItemBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ShopContext $context): OrderLineItemDetailCollection
    {
        /** @var OrderLineItemDetailCollection $entities */
        $entities = $this->reader->readDetail(OrderLineItemDefinition::class, $ids, $context);

        $event = new OrderLineItemDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(OrderLineItemDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(OrderLineItemDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(OrderLineItemDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(OrderLineItemDefinition::class, $ids, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, ShopContext $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(OrderLineItemDefinition::class, $id, WriteContext::createFromShopContext($context), $name, $versionId);
    }

    public function merge(string $versionId, ShopContext $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromShopContext($context));
    }
}
