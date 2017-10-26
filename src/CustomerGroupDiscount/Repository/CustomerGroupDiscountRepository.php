<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroupDiscount\Event\CustomerGroupDiscountBasicLoadedEvent;
use Shopware\CustomerGroupDiscount\Event\CustomerGroupDiscountWrittenEvent;
use Shopware\CustomerGroupDiscount\Reader\CustomerGroupDiscountBasicReader;
use Shopware\CustomerGroupDiscount\Searcher\CustomerGroupDiscountSearcher;
use Shopware\CustomerGroupDiscount\Searcher\CustomerGroupDiscountSearchResult;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicCollection;
use Shopware\CustomerGroupDiscount\Writer\CustomerGroupDiscountWriter;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerGroupDiscountRepository implements RepositoryInterface
{
    /**
     * @var CustomerGroupDiscountBasicReader
     */
    private $basicReader;

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
        CustomerGroupDiscountBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        CustomerGroupDiscountSearcher $searcher,
        CustomerGroupDiscountWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): CustomerGroupDiscountBasicCollection
    {
        if (empty($uuids)) {
            return new CustomerGroupDiscountBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            CustomerGroupDiscountBasicLoadedEvent::NAME,
            new CustomerGroupDiscountBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): CustomerGroupDiscountBasicCollection
    {
        return $this->readBasic($uuids, $context);
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

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): CustomerGroupDiscountWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): CustomerGroupDiscountWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
