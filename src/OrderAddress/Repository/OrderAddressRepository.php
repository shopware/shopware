<?php declare(strict_types=1);

namespace Shopware\OrderAddress\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\OrderAddress\Event\OrderAddressBasicLoadedEvent;
use Shopware\OrderAddress\Event\OrderAddressWrittenEvent;
use Shopware\OrderAddress\Reader\OrderAddressBasicReader;
use Shopware\OrderAddress\Searcher\OrderAddressSearcher;
use Shopware\OrderAddress\Searcher\OrderAddressSearchResult;
use Shopware\OrderAddress\Struct\OrderAddressBasicCollection;
use Shopware\OrderAddress\Writer\OrderAddressWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderAddressRepository implements RepositoryInterface
{
    /**
     * @var OrderAddressBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var OrderAddressSearcher
     */
    private $searcher;

    /**
     * @var OrderAddressWriter
     */
    private $writer;

    public function __construct(
        OrderAddressBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        OrderAddressSearcher $searcher,
        OrderAddressWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): OrderAddressBasicCollection
    {
        if (empty($uuids)) {
            return new OrderAddressBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            OrderAddressBasicLoadedEvent::NAME,
            new OrderAddressBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): OrderAddressBasicCollection
    {
        return $this->readBasic($uuids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): OrderAddressSearchResult
    {
        /** @var OrderAddressSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            OrderAddressBasicLoadedEvent::NAME,
            new OrderAddressBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): OrderAddressWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): OrderAddressWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): OrderAddressWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
