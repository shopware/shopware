<?php

namespace Shopware\Api\Configuration\Repository;

use Shopware\Api\Entity\Search\AggregationResult;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntityAggregatorInterface;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Api\Configuration\Event\ConfigurationGroupTranslation\ConfigurationGroupTranslationSearchResultLoadedEvent;
use Shopware\Api\Configuration\Event\ConfigurationGroupTranslation\ConfigurationGroupTranslationBasicLoadedEvent;
use Shopware\Api\Configuration\Event\ConfigurationGroupTranslation\ConfigurationGroupTranslationAggregationResultLoadedEvent;
use Shopware\Api\Configuration\Event\ConfigurationGroupTranslation\ConfigurationGroupTranslationIdSearchResultLoadedEvent;
use Shopware\Api\Configuration\Struct\ConfigurationGroupTranslationSearchResult;
use Shopware\Api\Configuration\Definition\ConfigurationGroupTranslationDefinition;
use Shopware\Api\Configuration\Collection\ConfigurationGroupTranslationBasicCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Shopware\Api\Configuration\Collection\ConfigurationGroupTranslationDetailCollection;
use Shopware\Api\Configuration\Event\ConfigurationGroupTranslation\ConfigurationGroupTranslationDetailLoadedEvent;

class ConfigurationGroupTranslationRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, ShopContext $context): ConfigurationGroupTranslationSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ConfigurationGroupTranslationSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new ConfigurationGroupTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, ShopContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(ConfigurationGroupTranslationDefinition::class, $criteria, $context);

        $event = new ConfigurationGroupTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ShopContext $context): IdSearchResult
    {
        $result = $this->searcher->search(ConfigurationGroupTranslationDefinition::class, $criteria, $context);

        $event = new ConfigurationGroupTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ShopContext $context): ConfigurationGroupTranslationBasicCollection
    {
        /** @var ConfigurationGroupTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(ConfigurationGroupTranslationDefinition::class, $ids, $context);

        $event = new ConfigurationGroupTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ShopContext $context): ConfigurationGroupTranslationDetailCollection
    {

        /** @var ConfigurationGroupTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(ConfigurationGroupTranslationDefinition::class, $ids, $context);

        $event = new ConfigurationGroupTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;                
                
    }

    public function update(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(ConfigurationGroupTranslationDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(ConfigurationGroupTranslationDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(ConfigurationGroupTranslationDefinition::class, $data, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ShopContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->delete(ConfigurationGroupTranslationDefinition::class, $ids, WriteContext::createFromShopContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}