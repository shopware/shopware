<?php declare(strict_types=1);

namespace Shopware\Api\Media\Repository;

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
use Shopware\Api\Media\Collection\MediaTranslationBasicCollection;
use Shopware\Api\Media\Collection\MediaTranslationDetailCollection;
use Shopware\Api\Media\Definition\MediaTranslationDefinition;
use Shopware\Api\Media\Event\MediaTranslation\MediaTranslationAggregationResultLoadedEvent;
use Shopware\Api\Media\Event\MediaTranslation\MediaTranslationBasicLoadedEvent;
use Shopware\Api\Media\Event\MediaTranslation\MediaTranslationDetailLoadedEvent;
use Shopware\Api\Media\Event\MediaTranslation\MediaTranslationIdSearchResultLoadedEvent;
use Shopware\Api\Media\Event\MediaTranslation\MediaTranslationSearchResultLoadedEvent;
use Shopware\Api\Media\Struct\MediaTranslationSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MediaTranslationRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): MediaTranslationSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = MediaTranslationSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new MediaTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(MediaTranslationDefinition::class, $criteria, $context);

        $event = new MediaTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, TranslationContext $context): IdSearchResult
    {
        $result = $this->searcher->search(MediaTranslationDefinition::class, $criteria, $context);

        $event = new MediaTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, TranslationContext $context): MediaTranslationBasicCollection
    {
        /** @var MediaTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(MediaTranslationDefinition::class, $ids, $context);

        $event = new MediaTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, TranslationContext $context): MediaTranslationDetailCollection
    {
        /** @var MediaTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(MediaTranslationDefinition::class, $ids, $context);

        $event = new MediaTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(MediaTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(MediaTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(MediaTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->delete(MediaTranslationDefinition::class, $ids, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
