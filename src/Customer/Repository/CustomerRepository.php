<?php declare(strict_types=1);

namespace Shopware\Customer\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Event\CustomerBasicLoadedEvent;
use Shopware\Customer\Event\CustomerDetailLoadedEvent;
use Shopware\Customer\Event\CustomerWrittenEvent;
use Shopware\Customer\Reader\CustomerBasicReader;
use Shopware\Customer\Reader\CustomerDetailReader;
use Shopware\Customer\Searcher\CustomerSearcher;
use Shopware\Customer\Searcher\CustomerSearchResult;
use Shopware\Customer\Struct\CustomerBasicCollection;
use Shopware\Customer\Struct\CustomerDetailCollection;
use Shopware\Customer\Writer\CustomerWriter;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerRepository
{
    /**
     * @var CustomerDetailReader
     */
    protected $detailReader;

    /**
     * @var CustomerBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CustomerSearcher
     */
    private $searcher;

    /**
     * @var CustomerWriter
     */
    private $writer;

    public function __construct(
        CustomerDetailReader $detailReader,
        CustomerBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        CustomerSearcher $searcher,
        CustomerWriter $writer
    ) {
        $this->detailReader = $detailReader;
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): CustomerBasicCollection
    {
        if (empty($uuids)) {
            return new CustomerBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            CustomerBasicLoadedEvent::NAME,
            new CustomerBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): CustomerDetailCollection
    {
        if (empty($uuids)) {
            return new CustomerDetailCollection();
        }
        $collection = $this->detailReader->readDetail($uuids, $context);

        $this->eventDispatcher->dispatch(
            CustomerDetailLoadedEvent::NAME,
            new CustomerDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): CustomerSearchResult
    {
        /** @var CustomerSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            CustomerBasicLoadedEvent::NAME,
            new CustomerBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): CustomerWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): CustomerWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): CustomerWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
