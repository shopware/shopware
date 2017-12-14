<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Repository;

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
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldValueBasicCollection;
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldValueDetailCollection;
use Shopware\Api\Shop\Definition\ShopTemplateConfigFormFieldValueDefinition;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueAggregationResultLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueBasicLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueDetailLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueSearchResultLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueUuidSearchResultLoadedEvent;
use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldValueSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShopTemplateConfigFormFieldValueRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): ShopTemplateConfigFormFieldValueSearchResult
    {
        $uuids = $this->searchUuids($criteria, $context);

        $entities = $this->readBasic($uuids->getUuids(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ShopTemplateConfigFormFieldValueSearchResult::createFromResults($uuids, $entities, $aggregations);

        $event = new ShopTemplateConfigFormFieldValueSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(ShopTemplateConfigFormFieldValueDefinition::class, $criteria, $context);

        $event = new ShopTemplateConfigFormFieldValueAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        $result = $this->searcher->search(ShopTemplateConfigFormFieldValueDefinition::class, $criteria, $context);

        $event = new ShopTemplateConfigFormFieldValueUuidSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $uuids, TranslationContext $context): ShopTemplateConfigFormFieldValueBasicCollection
    {
        /** @var ShopTemplateConfigFormFieldValueBasicCollection $entities */
        $entities = $this->reader->readBasic(ShopTemplateConfigFormFieldValueDefinition::class, $uuids, $context);

        $event = new ShopTemplateConfigFormFieldValueBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $uuids, TranslationContext $context): ShopTemplateConfigFormFieldValueDetailCollection
    {
        /** @var ShopTemplateConfigFormFieldValueDetailCollection $entities */
        $entities = $this->reader->readDetail(ShopTemplateConfigFormFieldValueDefinition::class, $uuids, $context);

        $event = new ShopTemplateConfigFormFieldValueDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(ShopTemplateConfigFormFieldValueDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(ShopTemplateConfigFormFieldValueDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(ShopTemplateConfigFormFieldValueDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
