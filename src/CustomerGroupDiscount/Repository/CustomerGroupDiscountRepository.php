<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroupDiscount\Event\CustomerGroupDiscountBasicLoadedEvent;
use Shopware\CustomerGroupDiscount\Event\CustomerGroupDiscountWrittenEvent;
use Shopware\CustomerGroupDiscount\Loader\CustomerGroupDiscountBasicLoader;
use Shopware\CustomerGroupDiscount\Searcher\CustomerGroupDiscountSearcher;
use Shopware\CustomerGroupDiscount\Searcher\CustomerGroupDiscountSearchResult;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicCollection;
use Shopware\CustomerGroupDiscount\Writer\CustomerGroupDiscountWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerGroupDiscountRepository
{
    /**
     * @var CustomerGroupDiscountBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CustomerGroupDiscountSearcher
     */
    private $searcher;

    /**
     * @var CustomerGroupDiscountWriter
     */
    private $writer;

    public function __construct(
        CustomerGroupDiscountBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        CustomerGroupDiscountSearcher $searcher,
        CustomerGroupDiscountWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): CustomerGroupDiscountBasicCollection
    {
        if (empty($uuids)) {
            return new CustomerGroupDiscountBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            CustomerGroupDiscountBasicLoadedEvent::NAME,
            new CustomerGroupDiscountBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): CustomerGroupDiscountSearchResult
    {
        /** @var CustomerGroupDiscountSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            CustomerGroupDiscountBasicLoadedEvent::NAME,
            new CustomerGroupDiscountBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): CustomerGroupDiscountWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): CustomerGroupDiscountWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): CustomerGroupDiscountWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
