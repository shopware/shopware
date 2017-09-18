<?php declare(strict_types=1);

namespace Shopware\Area\Repository;

use Shopware\Area\Event\AreaBasicLoadedEvent;
use Shopware\Area\Event\AreaDetailLoadedEvent;
use Shopware\Area\Event\AreaWrittenEvent;
use Shopware\Area\Loader\AreaBasicLoader;
use Shopware\Area\Loader\AreaDetailLoader;
use Shopware\Area\Searcher\AreaSearcher;
use Shopware\Area\Searcher\AreaSearchResult;
use Shopware\Area\Struct\AreaBasicCollection;
use Shopware\Area\Struct\AreaDetailCollection;
use Shopware\Area\Writer\AreaWriter;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AreaRepository
{
    /**
     * @var AreaDetailLoader
     */
    protected $detailLoader;

    /**
     * @var AreaBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AreaSearcher
     */
    private $searcher;

    /**
     * @var AreaWriter
     */
    private $writer;

    public function __construct(
        AreaDetailLoader $detailLoader,
        AreaBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        AreaSearcher $searcher,
        AreaWriter $writer
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readDetail(array $uuids, TranslationContext $context): AreaDetailCollection
    {
        if (empty($uuids)) {
            return new AreaDetailCollection();
        }
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            AreaDetailLoadedEvent::NAME,
            new AreaDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): AreaBasicCollection
    {
        if (empty($uuids)) {
            return new AreaBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            AreaBasicLoadedEvent::NAME,
            new AreaBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): AreaSearchResult
    {
        /** @var AreaSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            AreaBasicLoadedEvent::NAME,
            new AreaBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): AreaWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): AreaWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): AreaWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
