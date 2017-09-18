<?php declare(strict_types=1);

namespace Shopware\Currency\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Event\CurrencyBasicLoadedEvent;
use Shopware\Currency\Event\CurrencyDetailLoadedEvent;
use Shopware\Currency\Event\CurrencyWrittenEvent;
use Shopware\Currency\Loader\CurrencyBasicLoader;
use Shopware\Currency\Loader\CurrencyDetailLoader;
use Shopware\Currency\Searcher\CurrencySearcher;
use Shopware\Currency\Searcher\CurrencySearchResult;
use Shopware\Currency\Struct\CurrencyBasicCollection;
use Shopware\Currency\Struct\CurrencyDetailCollection;
use Shopware\Currency\Writer\CurrencyWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CurrencyRepository
{
    /**
     * @var CurrencyDetailLoader
     */
    protected $detailLoader;

    /**
     * @var CurrencyBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CurrencySearcher
     */
    private $searcher;

    /**
     * @var CurrencyWriter
     */
    private $writer;

    public function __construct(
        CurrencyDetailLoader $detailLoader,
        CurrencyBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        CurrencySearcher $searcher,
        CurrencyWriter $writer
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readDetail(array $uuids, TranslationContext $context): CurrencyDetailCollection
    {
        if (empty($uuids)) {
            return new CurrencyDetailCollection();
        }
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            CurrencyDetailLoadedEvent::NAME,
            new CurrencyDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): CurrencyBasicCollection
    {
        if (empty($uuids)) {
            return new CurrencyBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            CurrencyBasicLoadedEvent::NAME,
            new CurrencyBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): CurrencySearchResult
    {
        /** @var CurrencySearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            CurrencyBasicLoadedEvent::NAME,
            new CurrencyBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): CurrencyWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): CurrencyWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): CurrencyWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
