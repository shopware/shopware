<?php declare(strict_types=1);

namespace Shopware\Shop\Repository;

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
use Shopware\Shop\Collection\ShopTemplateConfigPresetBasicCollection;
use Shopware\Shop\Collection\ShopTemplateConfigPresetDetailCollection;
use Shopware\Shop\Definition\ShopTemplateConfigPresetDefinition;
use Shopware\Shop\Event\ShopTemplateConfigPreset\ShopTemplateConfigPresetAggregationResultLoadedEvent;
use Shopware\Shop\Event\ShopTemplateConfigPreset\ShopTemplateConfigPresetBasicLoadedEvent;
use Shopware\Shop\Event\ShopTemplateConfigPreset\ShopTemplateConfigPresetDetailLoadedEvent;
use Shopware\Shop\Event\ShopTemplateConfigPreset\ShopTemplateConfigPresetSearchResultLoadedEvent;
use Shopware\Shop\Event\ShopTemplateConfigPreset\ShopTemplateConfigPresetUuidSearchResultLoadedEvent;
use Shopware\Shop\Struct\ShopTemplateConfigPresetSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShopTemplateConfigPresetRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): ShopTemplateConfigPresetSearchResult
    {
        $uuids = $this->searchUuids($criteria, $context);

        $entities = $this->readBasic($uuids->getUuids(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ShopTemplateConfigPresetSearchResult::createFromResults($uuids, $entities, $aggregations);

        $event = new ShopTemplateConfigPresetSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(ShopTemplateConfigPresetDefinition::class, $criteria, $context);

        $event = new ShopTemplateConfigPresetAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        $result = $this->searcher->search(ShopTemplateConfigPresetDefinition::class, $criteria, $context);

        $event = new ShopTemplateConfigPresetUuidSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $uuids, TranslationContext $context): ShopTemplateConfigPresetBasicCollection
    {
        /** @var ShopTemplateConfigPresetBasicCollection $entities */
        $entities = $this->reader->readBasic(ShopTemplateConfigPresetDefinition::class, $uuids, $context);

        $event = new ShopTemplateConfigPresetBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $uuids, TranslationContext $context): ShopTemplateConfigPresetDetailCollection
    {
        /** @var ShopTemplateConfigPresetDetailCollection $entities */
        $entities = $this->reader->readDetail(ShopTemplateConfigPresetDefinition::class, $uuids, $context);

        $event = new ShopTemplateConfigPresetDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(ShopTemplateConfigPresetDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(ShopTemplateConfigPresetDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(ShopTemplateConfigPresetDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
