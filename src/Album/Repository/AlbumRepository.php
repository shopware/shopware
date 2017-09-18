<?php declare(strict_types=1);

namespace Shopware\Album\Repository;

use Shopware\Album\Event\AlbumBasicLoadedEvent;
use Shopware\Album\Event\AlbumDetailLoadedEvent;
use Shopware\Album\Event\AlbumWrittenEvent;
use Shopware\Album\Loader\AlbumBasicLoader;
use Shopware\Album\Loader\AlbumDetailLoader;
use Shopware\Album\Searcher\AlbumSearcher;
use Shopware\Album\Searcher\AlbumSearchResult;
use Shopware\Album\Struct\AlbumBasicCollection;
use Shopware\Album\Struct\AlbumDetailCollection;
use Shopware\Album\Writer\AlbumWriter;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AlbumRepository
{
    /**
     * @var AlbumDetailLoader
     */
    protected $detailLoader;

    /**
     * @var AlbumBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AlbumSearcher
     */
    private $searcher;

    /**
     * @var AlbumWriter
     */
    private $writer;

    public function __construct(
        AlbumDetailLoader $detailLoader,
        AlbumBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        AlbumSearcher $searcher,
        AlbumWriter $writer
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readDetail(array $uuids, TranslationContext $context): AlbumDetailCollection
    {
        if (empty($uuids)) {
            return new AlbumDetailCollection();
        }
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            AlbumDetailLoadedEvent::NAME,
            new AlbumDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): AlbumBasicCollection
    {
        if (empty($uuids)) {
            return new AlbumBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            AlbumBasicLoadedEvent::NAME,
            new AlbumBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): AlbumSearchResult
    {
        /** @var AlbumSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            AlbumBasicLoadedEvent::NAME,
            new AlbumBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): AlbumWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): AlbumWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): AlbumWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
