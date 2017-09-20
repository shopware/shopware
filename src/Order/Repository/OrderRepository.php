<?php declare(strict_types=1);

namespace Shopware\Order\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Order\Event\OrderBasicLoadedEvent;
use Shopware\Order\Event\OrderDetailLoadedEvent;
use Shopware\Order\Event\OrderWrittenEvent;
use Shopware\Order\Loader\OrderBasicLoader;
use Shopware\Order\Loader\OrderDetailLoader;
use Shopware\Order\Searcher\OrderSearcher;
use Shopware\Order\Searcher\OrderSearchResult;
use Shopware\Order\Struct\OrderBasicCollection;
use Shopware\Order\Struct\OrderDetailCollection;
use Shopware\Order\Writer\OrderWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderRepository
{
    /**
     * @var OrderDetailLoader
     */
    protected $detailLoader;

    /**
     * @var OrderBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var OrderSearcher
     */
    private $searcher;

    /**
     * @var OrderWriter
     */
    private $writer;

    public function __construct(
        OrderDetailLoader $detailLoader,
        OrderBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        OrderSearcher $searcher,
        OrderWriter $writer
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readDetail(array $uuids, TranslationContext $context): OrderDetailCollection
    {
        if (empty($uuids)) {
            return new OrderDetailCollection();
        }
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            OrderDetailLoadedEvent::NAME,
            new OrderDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): OrderBasicCollection
    {
        if (empty($uuids)) {
            return new OrderBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            OrderBasicLoadedEvent::NAME,
            new OrderBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): OrderSearchResult
    {
        /** @var OrderSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            OrderBasicLoadedEvent::NAME,
            new OrderBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): OrderWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): OrderWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): OrderWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
