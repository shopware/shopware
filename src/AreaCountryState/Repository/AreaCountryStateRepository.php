<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Repository;

use Shopware\AreaCountryState\Event\AreaCountryStateBasicLoadedEvent;
use Shopware\AreaCountryState\Event\AreaCountryStateWrittenEvent;
use Shopware\AreaCountryState\Loader\AreaCountryStateBasicLoader;
use Shopware\AreaCountryState\Searcher\AreaCountryStateSearcher;
use Shopware\AreaCountryState\Searcher\AreaCountryStateSearchResult;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;
use Shopware\AreaCountryState\Writer\AreaCountryStateWriter;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AreaCountryStateRepository
{
    /**
     * @var AreaCountryStateBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AreaCountryStateSearcher
     */
    private $searcher;

    /**
     * @var AreaCountryStateWriter
     */
    private $writer;

    public function __construct(
        AreaCountryStateBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        AreaCountryStateSearcher $searcher,
        AreaCountryStateWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): AreaCountryStateBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryStateBasicLoadedEvent::NAME,
            new AreaCountryStateBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): AreaCountryStateSearchResult
    {
        /** @var AreaCountryStateSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryStateBasicLoadedEvent::NAME,
            new AreaCountryStateBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): AreaCountryStateWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): AreaCountryStateWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): AreaCountryStateWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
