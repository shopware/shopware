<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\OrderLineItem\Event\OrderLineItemBasicLoadedEvent;
use Shopware\OrderLineItem\Event\OrderLineItemWrittenEvent;
use Shopware\OrderLineItem\Reader\OrderLineItemBasicReader;
use Shopware\OrderLineItem\Searcher\OrderLineItemSearcher;
use Shopware\OrderLineItem\Searcher\OrderLineItemSearchResult;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicCollection;
use Shopware\OrderLineItem\Writer\OrderLineItemWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderLineItemRepository implements RepositoryInterface
{
    /**
     * @var OrderLineItemBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var OrderLineItemSearcher
     */
    private $searcher;

    /**
     * @var OrderLineItemWriter
     */
    private $writer;

    public function __construct(
        OrderLineItemBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        OrderLineItemSearcher $searcher,
        OrderLineItemWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): OrderLineItemBasicCollection
    {
        if (empty($uuids)) {
            return new OrderLineItemBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            OrderLineItemBasicLoadedEvent::NAME,
            new OrderLineItemBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): OrderLineItemBasicCollection
    {
        return $this->readBasic($uuids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): OrderLineItemSearchResult
    {
        /** @var OrderLineItemSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            OrderLineItemBasicLoadedEvent::NAME,
            new OrderLineItemBasicLoadedEvent($result, $context)
        );

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        return $this->searcher->searchUuids($criteria, $context);
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->searcher->aggregate($criteria, $context);

        return $result;
    }

    public function update(array $data, TranslationContext $context): OrderLineItemWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): OrderLineItemWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): OrderLineItemWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
