<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Repository;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\AreaCountry\Event\AreaCountryDetailLoadedEvent;
use Shopware\AreaCountry\Event\AreaCountryWrittenEvent;
use Shopware\AreaCountry\Loader\AreaCountryBasicLoader;
use Shopware\AreaCountry\Loader\AreaCountryDetailLoader;
use Shopware\AreaCountry\Searcher\AreaCountrySearcher;
use Shopware\AreaCountry\Searcher\AreaCountrySearchResult;
use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\AreaCountry\Struct\AreaCountryDetailCollection;
use Shopware\AreaCountry\Writer\AreaCountryWriter;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AreaCountryRepository
{
    /**
     * @var AreaCountryDetailLoader
     */
    protected $detailLoader;

    /**
     * @var AreaCountryBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AreaCountrySearcher
     */
    private $searcher;

    /**
     * @var AreaCountryWriter
     */
    private $writer;

    public function __construct(
        AreaCountryDetailLoader $detailLoader,
        AreaCountryBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        AreaCountrySearcher $searcher,
        AreaCountryWriter $writer
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readDetail(array $uuids, TranslationContext $context): AreaCountryDetailCollection
    {
        if (empty($uuids)) {
            return new AreaCountryDetailCollection();
        }
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryDetailLoadedEvent::NAME,
            new AreaCountryDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): AreaCountryBasicCollection
    {
        if (empty($uuids)) {
            return new AreaCountryBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryBasicLoadedEvent::NAME,
            new AreaCountryBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): AreaCountrySearchResult
    {
        /** @var AreaCountrySearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryBasicLoadedEvent::NAME,
            new AreaCountryBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): AreaCountryWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): AreaCountryWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): AreaCountryWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
