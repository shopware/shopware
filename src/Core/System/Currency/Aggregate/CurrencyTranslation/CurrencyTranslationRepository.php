<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\AggregatorResult;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Core\Framework\ORM\Search\IdSearchResult;
use Shopware\Core\Framework\ORM\Version\Service\VersionManager;
use Shopware\Core\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Core\Framework\ORM\Write\WriteContext;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationBasicCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationDetailCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Event\CurrencyTranslationAggregationResultLoadedEvent;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Event\CurrencyTranslationBasicLoadedEvent;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Event\CurrencyTranslationDetailLoadedEvent;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Event\CurrencyTranslationIdSearchResultLoadedEvent;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Event\CurrencyTranslationSearchResultLoadedEvent;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Struct\CurrencyTranslationSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CurrencyTranslationRepository implements RepositoryInterface
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
     * @var \Shopware\Core\Framework\ORM\Version\Service\VersionManager
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

    public function search(Criteria $criteria, Context $context): CurrencyTranslationSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = CurrencyTranslationSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new CurrencyTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, Context $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(CurrencyTranslationDefinition::class, $criteria, $context);

        $event = new CurrencyTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $result = $this->searcher->search(CurrencyTranslationDefinition::class, $criteria, $context);

        $event = new CurrencyTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, Context $context): CurrencyTranslationBasicCollection
    {
        /** @var \Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(CurrencyTranslationDefinition::class, $ids, $context);

        $event = new CurrencyTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, Context $context): CurrencyTranslationDetailCollection
    {
        /** @var \Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(CurrencyTranslationDefinition::class, $ids, $context);

        $event = new CurrencyTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(CurrencyTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(CurrencyTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(CurrencyTranslationDefinition::class, $data, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, Context $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(CurrencyTranslationDefinition::class, $ids, WriteContext::createFromContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(CurrencyTranslationDefinition::class, $id, WriteContext::createFromContext($context), $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromContext($context));
    }
}
