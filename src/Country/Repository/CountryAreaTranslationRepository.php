<?php declare(strict_types=1);

namespace Shopware\Country\Repository;

use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\AggregationResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntityAggregatorInterface;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\UuidSearchResult;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Country\Collection\CountryAreaTranslationDetailCollection;
use Shopware\Country\Definition\CountryAreaTranslationDefinition;
use Shopware\Country\Event\CountryAreaTranslation\CountryAreaTranslationAggregationResultLoadedEvent;
use Shopware\Country\Event\CountryAreaTranslation\CountryAreaTranslationBasicLoadedEvent;
use Shopware\Country\Event\CountryAreaTranslation\CountryAreaTranslationDetailLoadedEvent;
use Shopware\Country\Event\CountryAreaTranslation\CountryAreaTranslationSearchResultLoadedEvent;
use Shopware\Country\Event\CountryAreaTranslation\CountryAreaTranslationUuidSearchResultLoadedEvent;
use Shopware\Country\Struct\CountryAreaTranslationSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CountryAreaTranslationRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): CountryAreaTranslationSearchResult
    {
        $uuids = $this->searchUuids($criteria, $context);

        $entities = $this->readBasic($uuids->getUuids(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = CountryAreaTranslationSearchResult::createFromResults($uuids, $entities, $aggregations);

        $event = new CountryAreaTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(CountryAreaTranslationDefinition::class, $criteria, $context);

        $event = new CountryAreaTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        $result = $this->searcher->search(CountryAreaTranslationDefinition::class, $criteria, $context);

        $event = new CountryAreaTranslationUuidSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $uuids, TranslationContext $context): CountryAreaTranslationBasicCollection
    {
        /** @var CountryAreaTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(CountryAreaTranslationDefinition::class, $uuids, $context);

        $event = new CountryAreaTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $uuids, TranslationContext $context): CountryAreaTranslationDetailCollection
    {
        /** @var CountryAreaTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(CountryAreaTranslationDefinition::class, $uuids, $context);

        $event = new CountryAreaTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(CountryAreaTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(CountryAreaTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(CountryAreaTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
