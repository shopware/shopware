<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderDelivery\Event\OrderDeliveryBasicLoadedEvent;
use Shopware\OrderDelivery\Event\OrderDeliveryDetailLoadedEvent;
use Shopware\OrderDelivery\Event\OrderDeliveryWrittenEvent;
use Shopware\OrderDelivery\Loader\OrderDeliveryBasicLoader;
use Shopware\OrderDelivery\Loader\OrderDeliveryDetailLoader;
use Shopware\OrderDelivery\Searcher\OrderDeliverySearcher;
use Shopware\OrderDelivery\Searcher\OrderDeliverySearchResult;
use Shopware\OrderDelivery\Struct\OrderDeliveryBasicCollection;
use Shopware\OrderDelivery\Struct\OrderDeliveryDetailCollection;
use Shopware\OrderDelivery\Writer\OrderDeliveryWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderDeliveryRepository
{
    /**
     * @var OrderDeliveryDetailLoader
     */
    protected $detailLoader;

    /**
     * @var OrderDeliveryBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var OrderDeliverySearcher
     */
    private $searcher;

    /**
     * @var OrderDeliveryWriter
     */
    private $writer;

    public function __construct(
        OrderDeliveryDetailLoader $detailLoader,
        OrderDeliveryBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        OrderDeliverySearcher $searcher,
        OrderDeliveryWriter $writer
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readDetail(array $uuids, TranslationContext $context): OrderDeliveryDetailCollection
    {
        if (empty($uuids)) {
            return new OrderDeliveryDetailCollection();
        }
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            OrderDeliveryDetailLoadedEvent::NAME,
            new OrderDeliveryDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): OrderDeliveryBasicCollection
    {
        if (empty($uuids)) {
            return new OrderDeliveryBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            OrderDeliveryBasicLoadedEvent::NAME,
            new OrderDeliveryBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): OrderDeliverySearchResult
    {
        /** @var OrderDeliverySearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            OrderDeliveryBasicLoadedEvent::NAME,
            new OrderDeliveryBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): OrderDeliveryWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): OrderDeliveryWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): OrderDeliveryWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
