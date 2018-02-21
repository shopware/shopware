<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Repository;

use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\AggregationResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntityAggregatorInterface;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Tax\Collection\TaxBasicCollection;
use Shopware\Api\Tax\Collection\TaxDetailCollection;
use Shopware\Api\Tax\Definition\TaxDefinition;
use Shopware\Api\Tax\Event\Tax\TaxAggregationResultLoadedEvent;
use Shopware\Api\Tax\Event\Tax\TaxBasicLoadedEvent;
use Shopware\Api\Tax\Event\Tax\TaxDetailLoadedEvent;
use Shopware\Api\Tax\Event\Tax\TaxIdSearchResultLoadedEvent;
use Shopware\Api\Tax\Event\Tax\TaxSearchResultLoadedEvent;
use Shopware\Api\Tax\Struct\TaxSearchResult;
use Shopware\Context\Struct\ShopContext;
use Shopware\Version\VersionManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaxRepository implements RepositoryInterface
{
    /**
     * @var EntityReaderInterface
     */
    private $reader;

    /**
     * @var VersionManager
     */
    private $versionManager;

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

    public function search(Criteria $criteria, ShopContext $context): TaxSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = TaxSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new TaxSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, ShopContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(TaxDefinition::class, $criteria, $context);

        $event = new TaxAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ShopContext $context): IdSearchResult
    {
        $result = $this->searcher->search(TaxDefinition::class, $criteria, $context);

        $event = new TaxIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ShopContext $context): TaxBasicCollection
    {
        /** @var TaxBasicCollection $entities */
        $entities = $this->reader->readBasic(TaxDefinition::class, $ids, $context);

        $event = new TaxBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ShopContext $context): TaxDetailCollection
    {
        /** @var TaxDetailCollection $entities */
        $entities = $this->reader->readDetail(TaxDefinition::class, $ids, $context);

        $event = new TaxDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(TaxDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(TaxDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(TaxDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(TaxDefinition::class, $ids, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, ShopContext $context, ?string $name = null): string
    {
        return $this->versionManager->createVersion(TaxDefinition::class, $id, WriteContext::createFromShopContext($context), $name);
    }

    public function merge(string $versionId, ShopContext $context)
    {
        $this->versionManager->merge($versionId, WriteContext::createFromShopContext($context));
    }
}
