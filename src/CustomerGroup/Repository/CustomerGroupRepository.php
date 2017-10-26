<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\CustomerGroup\Event\CustomerGroupDetailLoadedEvent;
use Shopware\CustomerGroup\Event\CustomerGroupWrittenEvent;
use Shopware\CustomerGroup\Reader\CustomerGroupBasicReader;
use Shopware\CustomerGroup\Reader\CustomerGroupDetailReader;
use Shopware\CustomerGroup\Searcher\CustomerGroupSearcher;
use Shopware\CustomerGroup\Searcher\CustomerGroupSearchResult;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\CustomerGroup\Struct\CustomerGroupDetailCollection;
use Shopware\CustomerGroup\Writer\CustomerGroupWriter;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerGroupRepository
{
    /**
     * @var CustomerGroupDetailReader
     */
    protected $detailReader;

    /**
     * @var CustomerGroupBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CustomerGroupSearcher
     */
    private $searcher;

    /**
     * @var CustomerGroupWriter
     */
    private $writer;

    public function __construct(
        CustomerGroupDetailReader $detailReader,
        CustomerGroupBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        CustomerGroupSearcher $searcher,
        CustomerGroupWriter $writer
    ) {
        $this->detailReader = $detailReader;
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): CustomerGroupBasicCollection
    {
        if (empty($uuids)) {
            return new CustomerGroupBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            CustomerGroupBasicLoadedEvent::NAME,
            new CustomerGroupBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): CustomerGroupDetailCollection
    {
        if (empty($uuids)) {
            return new CustomerGroupDetailCollection();
        }
        $collection = $this->detailReader->readDetail($uuids, $context);

        $this->eventDispatcher->dispatch(
            CustomerGroupDetailLoadedEvent::NAME,
            new CustomerGroupDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): CustomerGroupSearchResult
    {
        /** @var CustomerGroupSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            CustomerGroupBasicLoadedEvent::NAME,
            new CustomerGroupBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): CustomerGroupWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): CustomerGroupWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): CustomerGroupWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
