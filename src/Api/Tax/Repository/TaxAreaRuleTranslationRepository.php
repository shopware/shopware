<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Repository;

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
use Shopware\Api\Tax\Collection\TaxAreaRuleTranslationBasicCollection;
use Shopware\Api\Tax\Collection\TaxAreaRuleTranslationDetailCollection;
use Shopware\Api\Tax\Definition\TaxAreaRuleTranslationDefinition;
use Shopware\Api\Tax\Event\TaxAreaRuleTranslation\TaxAreaRuleTranslationAggregationResultLoadedEvent;
use Shopware\Api\Tax\Event\TaxAreaRuleTranslation\TaxAreaRuleTranslationBasicLoadedEvent;
use Shopware\Api\Tax\Event\TaxAreaRuleTranslation\TaxAreaRuleTranslationDetailLoadedEvent;
use Shopware\Api\Tax\Event\TaxAreaRuleTranslation\TaxAreaRuleTranslationIdSearchResultLoadedEvent;
use Shopware\Api\Tax\Event\TaxAreaRuleTranslation\TaxAreaRuleTranslationSearchResultLoadedEvent;
use Shopware\Api\Tax\Struct\TaxAreaRuleTranslationSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaxAreaRuleTranslationRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): TaxAreaRuleTranslationSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = TaxAreaRuleTranslationSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new TaxAreaRuleTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(TaxAreaRuleTranslationDefinition::class, $criteria, $context);

        $event = new TaxAreaRuleTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, TranslationContext $context): IdSearchResult
    {
        $result = $this->searcher->search(TaxAreaRuleTranslationDefinition::class, $criteria, $context);

        $event = new TaxAreaRuleTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, TranslationContext $context): TaxAreaRuleTranslationBasicCollection
    {
        /** @var TaxAreaRuleTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(TaxAreaRuleTranslationDefinition::class, $ids, $context);

        $event = new TaxAreaRuleTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, TranslationContext $context): TaxAreaRuleTranslationDetailCollection
    {
        /** @var TaxAreaRuleTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(TaxAreaRuleTranslationDefinition::class, $ids, $context);

        $event = new TaxAreaRuleTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(TaxAreaRuleTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(TaxAreaRuleTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(TaxAreaRuleTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
