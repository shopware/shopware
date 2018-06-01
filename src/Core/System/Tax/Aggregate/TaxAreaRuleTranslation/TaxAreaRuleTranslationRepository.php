<?php declare(strict_types=1);

namespace Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation;

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
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Collection\TaxAreaRuleTranslationBasicCollection;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Collection\TaxAreaRuleTranslationDetailCollection;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Event\TaxAreaRuleTranslationAggregationResultLoadedEvent;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Event\TaxAreaRuleTranslationBasicLoadedEvent;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Event\TaxAreaRuleTranslationDetailLoadedEvent;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Event\TaxAreaRuleTranslationIdSearchResultLoadedEvent;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Event\TaxAreaRuleTranslationSearchResultLoadedEvent;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Struct\TaxAreaRuleTranslationSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaxAreaRuleTranslationRepository implements RepositoryInterface
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
     * @var \Shopware\Framework\ORM\Version\Service\VersionManager
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

    public function search(Criteria $criteria, Context $context): TaxAreaRuleTranslationSearchResult
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

    public function aggregate(Criteria $criteria, Context $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(TaxAreaRuleTranslationDefinition::class, $criteria, $context);

        $event = new TaxAreaRuleTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $result = $this->searcher->search(TaxAreaRuleTranslationDefinition::class, $criteria, $context);

        $event = new TaxAreaRuleTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, Context $context): TaxAreaRuleTranslationBasicCollection
    {
        /** @var TaxAreaRuleTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(TaxAreaRuleTranslationDefinition::class, $ids, $context);

        $event = new TaxAreaRuleTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, Context $context): TaxAreaRuleTranslationDetailCollection
    {
        /** @var \Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Collection\TaxAreaRuleTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(TaxAreaRuleTranslationDefinition::class, $ids, $context);

        $event = new TaxAreaRuleTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(TaxAreaRuleTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(TaxAreaRuleTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(TaxAreaRuleTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(TaxAreaRuleTranslationDefinition::class, $ids, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(TaxAreaRuleTranslationDefinition::class, $id, WriteContext::createFromContext($context), $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromContext($context));
    }
}
