<?php declare(strict_types=1);

namespace Shopware\Api\Country\Repository;

use Shopware\Api\Country\Collection\CountryTranslationBasicCollection;
use Shopware\Api\Country\Collection\CountryTranslationDetailCollection;
use Shopware\Api\Country\Definition\CountryTranslationDefinition;
use Shopware\Api\Country\Event\CountryTranslation\CountryTranslationAggregationResultLoadedEvent;
use Shopware\Api\Country\Event\CountryTranslation\CountryTranslationBasicLoadedEvent;
use Shopware\Api\Country\Event\CountryTranslation\CountryTranslationDetailLoadedEvent;
use Shopware\Api\Country\Event\CountryTranslation\CountryTranslationIdSearchResultLoadedEvent;
use Shopware\Api\Country\Event\CountryTranslation\CountryTranslationSearchResultLoadedEvent;
use Shopware\Api\Country\Struct\CountryTranslationSearchResult;
use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\AggregatorResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntityAggregatorInterface;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Context\Struct\ShopContext;
use Shopware\Version\VersionManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CountryTranslationRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, ShopContext $context): CountryTranslationSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = CountryTranslationSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new CountryTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, ShopContext $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(CountryTranslationDefinition::class, $criteria, $context);

        $event = new CountryTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ShopContext $context): IdSearchResult
    {
        $result = $this->searcher->search(CountryTranslationDefinition::class, $criteria, $context);

        $event = new CountryTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ShopContext $context): CountryTranslationBasicCollection
    {
        /** @var CountryTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(CountryTranslationDefinition::class, $ids, $context);

        $event = new CountryTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ShopContext $context): CountryTranslationDetailCollection
    {
        /** @var CountryTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(CountryTranslationDefinition::class, $ids, $context);

        $event = new CountryTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(CountryTranslationDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(CountryTranslationDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(CountryTranslationDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(CountryTranslationDefinition::class, $ids, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, ShopContext $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(CountryTranslationDefinition::class, $id, WriteContext::createFromShopContext($context), $name, $versionId);
    }

    public function merge(string $versionId, ShopContext $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromShopContext($context));
    }
}
