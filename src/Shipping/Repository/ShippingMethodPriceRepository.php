<?php declare(strict_types=1);

namespace Shopware\Shipping\Repository;

use Shopware\Api\Read\EntityReaderInterface;
use Shopware\Api\RepositoryInterface;
use Shopware\Api\Search\AggregationResult;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\EntityAggregatorInterface;
use Shopware\Api\Search\EntitySearcherInterface;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Api\Write\EntityWriterInterface;
use Shopware\Api\Write\GenericWrittenEvent;
use Shopware\Api\Write\WriteContext;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Shipping\Collection\ShippingMethodPriceBasicCollection;
use Shopware\Shipping\Collection\ShippingMethodPriceDetailCollection;
use Shopware\Shipping\Definition\ShippingMethodPriceDefinition;
use Shopware\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceAggregationResultLoadedEvent;
use Shopware\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceBasicLoadedEvent;
use Shopware\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceDetailLoadedEvent;
use Shopware\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceSearchResultLoadedEvent;
use Shopware\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceUuidSearchResultLoadedEvent;
use Shopware\Shipping\Struct\ShippingMethodPriceSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShippingMethodPriceRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): ShippingMethodPriceSearchResult
    {
        $uuids = $this->searchUuids($criteria, $context);

        $entities = $this->readBasic($uuids->getUuids(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ShippingMethodPriceSearchResult::createFromResults($uuids, $entities, $aggregations);

        $event = new ShippingMethodPriceSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(ShippingMethodPriceDefinition::class, $criteria, $context);

        $event = new ShippingMethodPriceAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        $result = $this->searcher->search(ShippingMethodPriceDefinition::class, $criteria, $context);

        $event = new ShippingMethodPriceUuidSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $uuids, TranslationContext $context): ShippingMethodPriceBasicCollection
    {
        /** @var ShippingMethodPriceBasicCollection $entities */
        $entities = $this->reader->readBasic(ShippingMethodPriceDefinition::class, $uuids, $context);

        $event = new ShippingMethodPriceBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $uuids, TranslationContext $context): ShippingMethodPriceDetailCollection
    {
        /** @var ShippingMethodPriceDetailCollection $entities */
        $entities = $this->reader->readDetail(ShippingMethodPriceDefinition::class, $uuids, $context);

        $event = new ShippingMethodPriceDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(ShippingMethodPriceDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(ShippingMethodPriceDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(ShippingMethodPriceDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
