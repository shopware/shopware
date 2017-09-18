<?php declare(strict_types=1);

namespace Shopware\Tax\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\Tax\Event\TaxBasicLoadedEvent;
use Shopware\Tax\Event\TaxWrittenEvent;
use Shopware\Tax\Loader\TaxBasicLoader;
use Shopware\Tax\Searcher\TaxSearcher;
use Shopware\Tax\Searcher\TaxSearchResult;
use Shopware\Tax\Struct\TaxBasicCollection;
use Shopware\Tax\Writer\TaxWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaxRepository
{
    /**
     * @var TaxBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var TaxSearcher
     */
    private $searcher;

    /**
     * @var TaxWriter
     */
    private $writer;

    public function __construct(
        TaxBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        TaxSearcher $searcher,
        TaxWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): TaxBasicCollection
    {
        if (empty($uuids)) {
            return new TaxBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            TaxBasicLoadedEvent::NAME,
            new TaxBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): TaxSearchResult
    {
        /** @var TaxSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            TaxBasicLoadedEvent::NAME,
            new TaxBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): TaxWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): TaxWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): TaxWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
