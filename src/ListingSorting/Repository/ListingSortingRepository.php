<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\ListingSorting\Event\ListingSortingBasicLoadedEvent;
use Shopware\ListingSorting\Event\ListingSortingWrittenEvent;
use Shopware\ListingSorting\Reader\ListingSortingBasicReader;
use Shopware\ListingSorting\Searcher\ListingSortingSearcher;
use Shopware\ListingSorting\Searcher\ListingSortingSearchResult;
use Shopware\ListingSorting\Struct\ListingSortingBasicCollection;
use Shopware\ListingSorting\Writer\ListingSortingWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingSortingRepository implements RepositoryInterface
{
    /**
     * @var ListingSortingBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ListingSortingSearcher
     */
    private $searcher;

    /**
     * @var ListingSortingWriter
     */
    private $writer;

    public function __construct(
        ListingSortingBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        ListingSortingSearcher $searcher,
        ListingSortingWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): ListingSortingBasicCollection
    {
        if (empty($uuids)) {
            return new ListingSortingBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            ListingSortingBasicLoadedEvent::NAME,
            new ListingSortingBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): ListingSortingBasicCollection
    {
        return $this->readBasic($uuids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): ListingSortingSearchResult
    {
        /** @var ListingSortingSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ListingSortingBasicLoadedEvent::NAME,
            new ListingSortingBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ListingSortingWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ListingSortingWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ListingSortingWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
