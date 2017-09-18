<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\PriceGroupDiscount\Event\PriceGroupDiscountBasicLoadedEvent;
use Shopware\PriceGroupDiscount\Event\PriceGroupDiscountWrittenEvent;
use Shopware\PriceGroupDiscount\Loader\PriceGroupDiscountBasicLoader;
use Shopware\PriceGroupDiscount\Searcher\PriceGroupDiscountSearcher;
use Shopware\PriceGroupDiscount\Searcher\PriceGroupDiscountSearchResult;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicCollection;
use Shopware\PriceGroupDiscount\Writer\PriceGroupDiscountWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PriceGroupDiscountRepository
{
    /**
     * @var PriceGroupDiscountBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PriceGroupDiscountSearcher
     */
    private $searcher;

    /**
     * @var PriceGroupDiscountWriter
     */
    private $writer;

    public function __construct(
        PriceGroupDiscountBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        PriceGroupDiscountSearcher $searcher,
        PriceGroupDiscountWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): PriceGroupDiscountBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            PriceGroupDiscountBasicLoadedEvent::NAME,
            new PriceGroupDiscountBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): PriceGroupDiscountSearchResult
    {
        /** @var PriceGroupDiscountSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            PriceGroupDiscountBasicLoadedEvent::NAME,
            new PriceGroupDiscountBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): PriceGroupDiscountWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): PriceGroupDiscountWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): PriceGroupDiscountWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
