<?php declare(strict_types=1);

namespace Shopware\Holiday\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Holiday\Event\HolidayBasicLoadedEvent;
use Shopware\Holiday\Event\HolidayWrittenEvent;
use Shopware\Holiday\Loader\HolidayBasicLoader;
use Shopware\Holiday\Searcher\HolidaySearcher;
use Shopware\Holiday\Searcher\HolidaySearchResult;
use Shopware\Holiday\Struct\HolidayBasicCollection;
use Shopware\Holiday\Writer\HolidayWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class HolidayRepository
{
    /**
     * @var HolidayBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var HolidaySearcher
     */
    private $searcher;

    /**
     * @var HolidayWriter
     */
    private $writer;

    public function __construct(
        HolidayBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        HolidaySearcher $searcher,
        HolidayWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): HolidayBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            HolidayBasicLoadedEvent::NAME,
            new HolidayBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): HolidaySearchResult
    {
        /** @var HolidaySearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            HolidayBasicLoadedEvent::NAME,
            new HolidayBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): HolidayWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): HolidayWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): HolidayWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
