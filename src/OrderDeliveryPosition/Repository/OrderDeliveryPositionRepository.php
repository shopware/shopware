<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\OrderDeliveryPosition\Event\OrderDeliveryPositionBasicLoadedEvent;
use Shopware\OrderDeliveryPosition\Event\OrderDeliveryPositionWrittenEvent;
use Shopware\OrderDeliveryPosition\Reader\OrderDeliveryPositionBasicReader;
use Shopware\OrderDeliveryPosition\Searcher\OrderDeliveryPositionSearcher;
use Shopware\OrderDeliveryPosition\Searcher\OrderDeliveryPositionSearchResult;
use Shopware\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicCollection;
use Shopware\OrderDeliveryPosition\Writer\OrderDeliveryPositionWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderDeliveryPositionRepository implements RepositoryInterface
{
    /**
     * @var OrderDeliveryPositionBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var OrderDeliveryPositionSearcher
     */
    private $searcher;

    /**
     * @var OrderDeliveryPositionWriter
     */
    private $writer;

    public function __construct(
        OrderDeliveryPositionBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        OrderDeliveryPositionSearcher $searcher,
        OrderDeliveryPositionWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): OrderDeliveryPositionBasicCollection
    {
        if (empty($uuids)) {
            return new OrderDeliveryPositionBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            OrderDeliveryPositionBasicLoadedEvent::NAME,
            new OrderDeliveryPositionBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): OrderDeliveryPositionBasicCollection
    {
        return $this->readBasic($uuids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): OrderDeliveryPositionSearchResult
    {
        /** @var OrderDeliveryPositionSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            OrderDeliveryPositionBasicLoadedEvent::NAME,
            new OrderDeliveryPositionBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): OrderDeliveryPositionWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): OrderDeliveryPositionWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): OrderDeliveryPositionWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
