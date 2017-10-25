<?php declare(strict_types=1);

namespace Shopware\Unit\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\Unit\Event\UnitBasicLoadedEvent;
use Shopware\Unit\Event\UnitWrittenEvent;
use Shopware\Unit\Reader\UnitBasicReader;
use Shopware\Unit\Searcher\UnitSearcher;
use Shopware\Unit\Searcher\UnitSearchResult;
use Shopware\Unit\Struct\UnitBasicCollection;
use Shopware\Unit\Writer\UnitWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UnitRepository implements RepositoryInterface
{
    /**
     * @var UnitBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var UnitSearcher
     */
    private $searcher;

    /**
     * @var UnitWriter
     */
    private $writer;

    public function __construct(
        UnitBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        UnitSearcher $searcher,
        UnitWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): UnitBasicCollection
    {
        if (empty($uuids)) {
            return new UnitBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            UnitBasicLoadedEvent::NAME,
            new UnitBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): UnitBasicCollection
    {
        return $this->readBasic($uuids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): UnitSearchResult
    {
        /** @var UnitSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            UnitBasicLoadedEvent::NAME,
            new UnitBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): UnitWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): UnitWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): UnitWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
