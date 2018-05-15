<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Repository;

use Shopware\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Framework\ORM\RepositoryInterface;
use Shopware\Framework\ORM\Search\AggregatorResult;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Framework\ORM\Write\WriteContext;
use Shopware\Checkout\Shipping\Collection\ShippingMethodPriceBasicCollection;
use Shopware\Checkout\Shipping\Collection\ShippingMethodPriceDetailCollection;
use Shopware\Checkout\Shipping\Definition\ShippingMethodPriceDefinition;
use Shopware\Checkout\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceAggregationResultLoadedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceBasicLoadedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceDetailLoadedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceIdSearchResultLoadedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceSearchResultLoadedEvent;
use Shopware\Checkout\Shipping\Struct\ShippingMethodPriceSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Version\VersionManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShippingMethodPriceRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, ApplicationContext $context): ShippingMethodPriceSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ShippingMethodPriceSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new ShippingMethodPriceSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, ApplicationContext $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(ShippingMethodPriceDefinition::class, $criteria, $context);

        $event = new ShippingMethodPriceAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ApplicationContext $context): IdSearchResult
    {
        $result = $this->searcher->search(ShippingMethodPriceDefinition::class, $criteria, $context);

        $event = new ShippingMethodPriceIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ApplicationContext $context): ShippingMethodPriceBasicCollection
    {
        /** @var ShippingMethodPriceBasicCollection $entities */
        $entities = $this->reader->readBasic(ShippingMethodPriceDefinition::class, $ids, $context);

        $event = new ShippingMethodPriceBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ApplicationContext $context): ShippingMethodPriceDetailCollection
    {
        /** @var ShippingMethodPriceDetailCollection $entities */
        $entities = $this->reader->readDetail(ShippingMethodPriceDefinition::class, $ids, $context);

        $event = new ShippingMethodPriceDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(ShippingMethodPriceDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(ShippingMethodPriceDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(ShippingMethodPriceDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(ShippingMethodPriceDefinition::class, $ids, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, ApplicationContext $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(ShippingMethodPriceDefinition::class, $id, WriteContext::createFromApplicationContext($context), $name, $versionId);
    }

    public function merge(string $versionId, ApplicationContext $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromApplicationContext($context));
    }
}
