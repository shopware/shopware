<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\PriceGroup\Event\PriceGroupBasicLoadedEvent;
use Shopware\PriceGroup\Event\PriceGroupDetailLoadedEvent;
use Shopware\PriceGroup\Event\PriceGroupWrittenEvent;
use Shopware\PriceGroup\Reader\PriceGroupBasicReader;
use Shopware\PriceGroup\Reader\PriceGroupDetailReader;
use Shopware\PriceGroup\Searcher\PriceGroupSearcher;
use Shopware\PriceGroup\Searcher\PriceGroupSearchResult;
use Shopware\PriceGroup\Struct\PriceGroupBasicCollection;
use Shopware\PriceGroup\Struct\PriceGroupDetailCollection;
use Shopware\PriceGroup\Writer\PriceGroupWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PriceGroupRepository
{
    /**
     * @var PriceGroupDetailReader
     */
    protected $detailReader;

    /**
     * @var PriceGroupBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PriceGroupSearcher
     */
    private $searcher;

    /**
     * @var PriceGroupWriter
     */
    private $writer;

    public function __construct(
        PriceGroupDetailReader $detailReader,
        PriceGroupBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        PriceGroupSearcher $searcher,
        PriceGroupWriter $writer
    ) {
        $this->detailReader = $detailReader;
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): PriceGroupBasicCollection
    {
        if (empty($uuids)) {
            return new PriceGroupBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            PriceGroupBasicLoadedEvent::NAME,
            new PriceGroupBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): PriceGroupDetailCollection
    {
        if (empty($uuids)) {
            return new PriceGroupDetailCollection();
        }
        $collection = $this->detailReader->readDetail($uuids, $context);

        $this->eventDispatcher->dispatch(
            PriceGroupDetailLoadedEvent::NAME,
            new PriceGroupDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): PriceGroupSearchResult
    {
        /** @var PriceGroupSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            PriceGroupBasicLoadedEvent::NAME,
            new PriceGroupBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): PriceGroupWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): PriceGroupWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): PriceGroupWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
