<?php declare(strict_types=1);

namespace Shopware\Api\Unit\Repository;

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
use Shopware\Api\Unit\Collection\UnitTranslationBasicCollection;
use Shopware\Api\Unit\Collection\UnitTranslationDetailCollection;
use Shopware\Api\Unit\Definition\UnitTranslationDefinition;
use Shopware\Api\Unit\Event\UnitTranslation\UnitTranslationAggregationResultLoadedEvent;
use Shopware\Api\Unit\Event\UnitTranslation\UnitTranslationBasicLoadedEvent;
use Shopware\Api\Unit\Event\UnitTranslation\UnitTranslationDetailLoadedEvent;
use Shopware\Api\Unit\Event\UnitTranslation\UnitTranslationIdSearchResultLoadedEvent;
use Shopware\Api\Unit\Event\UnitTranslation\UnitTranslationSearchResultLoadedEvent;
use Shopware\Api\Unit\Struct\UnitTranslationSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UnitTranslationRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): UnitTranslationSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = UnitTranslationSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new UnitTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(UnitTranslationDefinition::class, $criteria, $context);

        $event = new UnitTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, TranslationContext $context): IdSearchResult
    {
        $result = $this->searcher->search(UnitTranslationDefinition::class, $criteria, $context);

        $event = new UnitTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, TranslationContext $context): UnitTranslationBasicCollection
    {
        /** @var UnitTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(UnitTranslationDefinition::class, $ids, $context);

        $event = new UnitTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, TranslationContext $context): UnitTranslationDetailCollection
    {
        /** @var UnitTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(UnitTranslationDefinition::class, $ids, $context);

        $event = new UnitTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(UnitTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(UnitTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(UnitTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->delete(UnitTranslationDefinition::class, $ids, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
