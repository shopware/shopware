<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerAddress\Event\CustomerAddressBasicLoadedEvent;
use Shopware\CustomerAddress\Event\CustomerAddressWrittenEvent;
use Shopware\CustomerAddress\Loader\CustomerAddressBasicLoader;
use Shopware\CustomerAddress\Searcher\CustomerAddressSearcher;
use Shopware\CustomerAddress\Searcher\CustomerAddressSearchResult;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\CustomerAddress\Writer\CustomerAddressWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerAddressRepository
{
    /**
     * @var CustomerAddressBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CustomerAddressSearcher
     */
    private $searcher;

    /**
     * @var CustomerAddressWriter
     */
    private $writer;

    public function __construct(
        CustomerAddressBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        CustomerAddressSearcher $searcher,
        CustomerAddressWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): CustomerAddressBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            CustomerAddressBasicLoadedEvent::NAME,
            new CustomerAddressBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): CustomerAddressSearchResult
    {
        /** @var CustomerAddressSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            CustomerAddressBasicLoadedEvent::NAME,
            new CustomerAddressBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): CustomerAddressWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): CustomerAddressWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): CustomerAddressWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
