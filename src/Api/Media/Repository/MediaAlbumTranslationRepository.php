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
use Shopware\Api\Media\Collection\MediaAlbumTranslationBasicCollection;
use Shopware\Api\Media\Collection\MediaAlbumTranslationDetailCollection;
use Shopware\Api\Media\Definition\MediaAlbumTranslationDefinition;
use Shopware\Api\Media\Event\MediaAlbumTranslation\MediaAlbumTranslationAggregationResultLoadedEvent;
use Shopware\Api\Media\Event\MediaAlbumTranslation\MediaAlbumTranslationBasicLoadedEvent;
use Shopware\Api\Media\Event\MediaAlbumTranslation\MediaAlbumTranslationDetailLoadedEvent;
use Shopware\Api\Media\Event\MediaAlbumTranslation\MediaAlbumTranslationIdSearchResultLoadedEvent;
use Shopware\Api\Media\Event\MediaAlbumTranslation\MediaAlbumTranslationSearchResultLoadedEvent;
use Shopware\Api\Media\Struct\MediaAlbumTranslationSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MediaAlbumTranslationRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): MediaAlbumTranslationSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = MediaAlbumTranslationSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new MediaAlbumTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(MediaAlbumTranslationDefinition::class, $criteria, $context);

        $event = new MediaAlbumTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, TranslationContext $context): IdSearchResult
    {
        $result = $this->searcher->search(MediaAlbumTranslationDefinition::class, $criteria, $context);

        $event = new MediaAlbumTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, TranslationContext $context): MediaAlbumTranslationBasicCollection
    {
        /** @var MediaAlbumTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(MediaAlbumTranslationDefinition::class, $ids, $context);

        $event = new MediaAlbumTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, TranslationContext $context): MediaAlbumTranslationDetailCollection
    {
        /** @var MediaAlbumTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(MediaAlbumTranslationDefinition::class, $ids, $context);

        $event = new MediaAlbumTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(MediaAlbumTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(MediaAlbumTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(MediaAlbumTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->delete(MediaAlbumTranslationDefinition::class, $ids, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
