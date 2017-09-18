<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\CustomerGroup\Event\CustomerGroupDetailLoadedEvent;
use Shopware\CustomerGroup\Event\CustomerGroupWrittenEvent;
use Shopware\CustomerGroup\Loader\CustomerGroupBasicLoader;
use Shopware\CustomerGroup\Loader\CustomerGroupDetailLoader;
use Shopware\CustomerGroup\Searcher\CustomerGroupSearcher;
use Shopware\CustomerGroup\Searcher\CustomerGroupSearchResult;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\CustomerGroup\Struct\CustomerGroupDetailCollection;
use Shopware\CustomerGroup\Writer\CustomerGroupWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerGroupRepository
{
    /**
     * @var CustomerGroupDetailLoader
     */
    protected $detailLoader;

    /**
     * @var CustomerGroupBasicLoader
     */
    private $basicLoader;

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
        CustomerGroupDetailLoader $detailLoader,
        CustomerGroupBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        CustomerGroupSearcher $searcher,
        CustomerGroupWriter $writer
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readDetail(array $uuids, TranslationContext $context): CustomerGroupDetailCollection
    {
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            CustomerGroupDetailLoadedEvent::NAME,
            new CustomerGroupDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): CustomerGroupBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            CustomerGroupBasicLoadedEvent::NAME,
            new CustomerGroupBasicLoadedEvent($collection, $context)
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

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): CustomerGroupWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): CustomerGroupWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
