<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryBasicCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryDetailCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Event\OrderDeliveryAggregationResultLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Event\OrderDeliveryBasicLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Event\OrderDeliveryDetailLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Event\OrderDeliveryIdSearchResultLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Event\OrderDeliverySearchResultLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Struct\OrderDeliverySearchResult;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\AggregatorResult;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Core\Framework\ORM\Search\IdSearchResult;
use Shopware\Core\Framework\ORM\Version\Service\VersionManager;
use Shopware\Core\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Core\Framework\ORM\Write\WriteContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderDeliveryRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, Context $context): OrderDeliverySearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = OrderDeliverySearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new OrderDeliverySearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, Context $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(OrderDeliveryDefinition::class, $criteria, $context);

        $event = new OrderDeliveryAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $result = $this->searcher->search(OrderDeliveryDefinition::class, $criteria, $context);

        $event = new OrderDeliveryIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, Context $context): OrderDeliveryBasicCollection
    {
        /** @var \Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryBasicCollection $entities */
        $entities = $this->reader->readBasic(OrderDeliveryDefinition::class, $ids, $context);

        $event = new OrderDeliveryBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, Context $context): OrderDeliveryDetailCollection
    {
        /** @var OrderDeliveryDetailCollection $entities */
        $entities = $this->reader->readDetail(OrderDeliveryDefinition::class, $ids, $context);

        $event = new OrderDeliveryDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(OrderDeliveryDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(OrderDeliveryDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(OrderDeliveryDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(OrderDeliveryDefinition::class, $ids, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(OrderDeliveryDefinition::class, $id, WriteContext::createFromContext($context), $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromContext($context));
    }
}
