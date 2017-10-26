<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Repository;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\AreaCountry\Event\AreaCountryDetailLoadedEvent;
use Shopware\AreaCountry\Event\AreaCountryWrittenEvent;
use Shopware\AreaCountry\Reader\AreaCountryBasicReader;
use Shopware\AreaCountry\Reader\AreaCountryDetailReader;
use Shopware\AreaCountry\Searcher\AreaCountrySearcher;
use Shopware\AreaCountry\Searcher\AreaCountrySearchResult;
use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\AreaCountry\Struct\AreaCountryDetailCollection;
use Shopware\AreaCountry\Writer\AreaCountryWriter;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AreaCountryRepository
{
    /**
     * @var AreaCountryDetailReader
     */
    protected $detailReader;

    /**
     * @var AreaCountryBasicReader
     */
    private $basicReader;

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
        AreaCountryDetailReader $detailReader,
        AreaCountryBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        AreaCountrySearcher $searcher,
        AreaCountryWriter $writer
    ) {
        $this->detailReader = $detailReader;
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): AreaCountryBasicCollection
    {
        if (empty($uuids)) {
            return new AreaCountryBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryBasicLoadedEvent::NAME,
            new AreaCountryBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): AreaCountryDetailCollection
    {
        if (empty($uuids)) {
            return new AreaCountryDetailCollection();
        }
        $collection = $this->detailReader->readDetail($uuids, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryDetailLoadedEvent::NAME,
            new AreaCountryDetailLoadedEvent($collection, $context)
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

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): AreaCountryWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): AreaCountryWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
