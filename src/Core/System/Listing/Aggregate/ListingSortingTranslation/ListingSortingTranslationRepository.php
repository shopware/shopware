<?php declare(strict_types=1);

namespace Shopware\System\Listing\Aggregate\ListingSortingTranslation;

use Shopware\Framework\Context;
use Shopware\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Framework\ORM\RepositoryInterface;
use Shopware\Framework\ORM\Search\AggregatorResult;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Framework\ORM\Version\Service\VersionManager;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Framework\ORM\Write\WriteContext;
use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationBasicCollection;
use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationDetailCollection;
use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Event\ListingSortingTranslationAggregationResultLoadedEvent;
use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Event\ListingSortingTranslationBasicLoadedEvent;
use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Event\ListingSortingTranslationDetailLoadedEvent;
use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Event\ListingSortingTranslationIdSearchResultLoadedEvent;
use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Event\ListingSortingTranslationSearchResultLoadedEvent;
use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Struct\ListingSortingTranslationSearchResult;
use Shopware\System\Listing\Definition\ListingSortingTranslationDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingSortingTranslationRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, Context $context): ListingSortingTranslationSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ListingSortingTranslationSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new ListingSortingTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, Context $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(ListingSortingTranslationDefinition::class, $criteria, $context);

        $event = new ListingSortingTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $result = $this->searcher->search(ListingSortingTranslationDefinition::class, $criteria, $context);

        $event = new ListingSortingTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, Context $context): ListingSortingTranslationBasicCollection
    {
        /** @var \Shopware\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(ListingSortingTranslationDefinition::class, $ids, $context);

        $event = new ListingSortingTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, Context $context): ListingSortingTranslationDetailCollection
    {
        /** @var ListingSortingTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(ListingSortingTranslationDefinition::class, $ids, $context);

        $event = new ListingSortingTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(ListingSortingTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(ListingSortingTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(ListingSortingTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(ListingSortingTranslationDefinition::class, $ids, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(ListingSortingTranslationDefinition::class, $id, WriteContext::createFromContext($context), $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromContext($context));
    }
}
