<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Repository;

use Shopware\AreaCountryState\Event\AreaCountryStateBasicLoadedEvent;
use Shopware\AreaCountryState\Event\AreaCountryStateWrittenEvent;
use Shopware\AreaCountryState\Reader\AreaCountryStateBasicReader;
use Shopware\AreaCountryState\Searcher\AreaCountryStateSearcher;
use Shopware\AreaCountryState\Searcher\AreaCountryStateSearchResult;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;
use Shopware\AreaCountryState\Writer\AreaCountryStateWriter;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AreaCountryStateRepository implements RepositoryInterface
{
    /**
     * @var AreaCountryStateBasicReader
     */
    private $basicReader;

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
        AreaCountryStateBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        AreaCountryStateSearcher $searcher,
        AreaCountryStateWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): AreaCountryStateBasicCollection
    {
        if (empty($uuids)) {
            return new AreaCountryStateBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            AreaCountryStateBasicLoadedEvent::NAME,
            new AreaCountryStateBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): AreaCountryStateBasicCollection
    {
        return $this->readBasic($uuids, $context);
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

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): AreaCountryStateWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): AreaCountryStateWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
