<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressBasicCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressDetailCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Event\CustomerAddressAggregationResultLoadedEvent;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Event\CustomerAddressBasicLoadedEvent;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Event\CustomerAddressDetailLoadedEvent;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Event\CustomerAddressIdSearchResultLoadedEvent;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Event\CustomerAddressSearchResultLoadedEvent;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Struct\CustomerAddressSearchResult;
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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerAddressRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, Context $context): CustomerAddressSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = CustomerAddressSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new CustomerAddressSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, Context $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(CustomerAddressDefinition::class, $criteria, $context);

        $event = new CustomerAddressAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $result = $this->searcher->search(CustomerAddressDefinition::class, $criteria, $context);

        $event = new CustomerAddressIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, Context $context): CustomerAddressBasicCollection
    {
        /** @var \Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressBasicCollection $entities */
        $entities = $this->reader->readBasic(CustomerAddressDefinition::class, $ids, $context);

        $event = new CustomerAddressBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, Context $context): CustomerAddressDetailCollection
    {
        /** @var \Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressDetailCollection $entities */
        $entities = $this->reader->readDetail(CustomerAddressDefinition::class, $ids, $context);

        $event = new CustomerAddressDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->update(CustomerAddressDefinition::class, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->upsert(CustomerAddressDefinition::class, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->insert(CustomerAddressDefinition::class, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->delete(CustomerAddressDefinition::class, $ids, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(CustomerAddressDefinition::class, $id, WriteContext::createFromContext($context), $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromContext($context));
    }
}
