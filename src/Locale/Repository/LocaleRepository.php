<?php declare(strict_types=1);

namespace Shopware\Locale\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\Locale\Event\LocaleBasicLoadedEvent;
use Shopware\Locale\Event\LocaleWrittenEvent;
use Shopware\Locale\Reader\LocaleBasicReader;
use Shopware\Locale\Searcher\LocaleSearcher;
use Shopware\Locale\Searcher\LocaleSearchResult;
use Shopware\Locale\Struct\LocaleBasicCollection;
use Shopware\Locale\Writer\LocaleWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LocaleRepository implements RepositoryInterface
{
    /**
     * @var LocaleBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var LocaleSearcher
     */
    private $searcher;

    /**
     * @var LocaleWriter
     */
    private $writer;

    public function __construct(
        LocaleBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        LocaleSearcher $searcher,
        LocaleWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): LocaleBasicCollection
    {
        if (empty($uuids)) {
            return new LocaleBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            LocaleBasicLoadedEvent::NAME,
            new LocaleBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): LocaleBasicCollection
    {
        return $this->readBasic($uuids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): LocaleSearchResult
    {
        /** @var LocaleSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            LocaleBasicLoadedEvent::NAME,
            new LocaleBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): LocaleWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): LocaleWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): LocaleWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
