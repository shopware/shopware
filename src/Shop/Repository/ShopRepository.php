<?php declare(strict_types=1);

namespace Shopware\Shop\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\Shop\Event\ShopBasicLoadedEvent;
use Shopware\Shop\Event\ShopDetailLoadedEvent;
use Shopware\Shop\Event\ShopWrittenEvent;
use Shopware\Shop\Reader\ShopBasicReader;
use Shopware\Shop\Reader\ShopDetailReader;
use Shopware\Shop\Searcher\ShopSearcher;
use Shopware\Shop\Searcher\ShopSearchResult;
use Shopware\Shop\Struct\ShopBasicCollection;
use Shopware\Shop\Struct\ShopDetailCollection;
use Shopware\Shop\Writer\ShopWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShopRepository
{
    /**
     * @var ShopDetailReader
     */
    protected $detailReader;

    /**
     * @var ShopBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ShopSearcher
     */
    private $searcher;

    /**
     * @var ShopWriter
     */
    private $writer;

    public function __construct(
        ShopDetailReader $detailReader,
        ShopBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        ShopSearcher $searcher,
        ShopWriter $writer
    ) {
        $this->detailReader = $detailReader;
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): ShopBasicCollection
    {
        if (empty($uuids)) {
            return new ShopBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            ShopBasicLoadedEvent::NAME,
            new ShopBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): ShopDetailCollection
    {
        if (empty($uuids)) {
            return new ShopDetailCollection();
        }
        $collection = $this->detailReader->readDetail($uuids, $context);

        $this->eventDispatcher->dispatch(
            ShopDetailLoadedEvent::NAME,
            new ShopDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ShopSearchResult
    {
        /** @var ShopSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ShopBasicLoadedEvent::NAME,
            new ShopBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ShopWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ShopWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ShopWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
