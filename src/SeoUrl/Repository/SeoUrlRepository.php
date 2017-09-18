<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\SeoUrl\Event\SeoUrlBasicLoadedEvent;
use Shopware\SeoUrl\Event\SeoUrlWrittenEvent;
use Shopware\SeoUrl\Loader\SeoUrlBasicLoader;
use Shopware\SeoUrl\Searcher\SeoUrlSearcher;
use Shopware\SeoUrl\Searcher\SeoUrlSearchResult;
use Shopware\SeoUrl\Struct\SeoUrlBasicCollection;
use Shopware\SeoUrl\Writer\SeoUrlWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SeoUrlRepository
{
    /**
     * @var SeoUrlBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SeoUrlSearcher
     */
    private $searcher;

    /**
     * @var SeoUrlWriter
     */
    private $writer;

    public function __construct(
        SeoUrlBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        SeoUrlSearcher $searcher,
        SeoUrlWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): SeoUrlBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            SeoUrlBasicLoadedEvent::NAME,
            new SeoUrlBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): SeoUrlSearchResult
    {
        /** @var SeoUrlSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            SeoUrlBasicLoadedEvent::NAME,
            new SeoUrlBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): SeoUrlWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): SeoUrlWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): SeoUrlWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
