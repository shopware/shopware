<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\TaxAreaRule\Event\TaxAreaRuleBasicLoadedEvent;
use Shopware\TaxAreaRule\Event\TaxAreaRuleWrittenEvent;
use Shopware\TaxAreaRule\Loader\TaxAreaRuleBasicLoader;
use Shopware\TaxAreaRule\Searcher\TaxAreaRuleSearcher;
use Shopware\TaxAreaRule\Searcher\TaxAreaRuleSearchResult;
use Shopware\TaxAreaRule\Struct\TaxAreaRuleBasicCollection;
use Shopware\TaxAreaRule\Writer\TaxAreaRuleWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaxAreaRuleRepository
{
    /**
     * @var TaxAreaRuleBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var TaxAreaRuleSearcher
     */
    private $searcher;

    /**
     * @var TaxAreaRuleWriter
     */
    private $writer;

    public function __construct(
        TaxAreaRuleBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        TaxAreaRuleSearcher $searcher,
        TaxAreaRuleWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): TaxAreaRuleBasicCollection
    {
        if (empty($uuids)) {
            return new TaxAreaRuleBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            TaxAreaRuleBasicLoadedEvent::NAME,
            new TaxAreaRuleBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): TaxAreaRuleSearchResult
    {
        /** @var TaxAreaRuleSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            TaxAreaRuleBasicLoadedEvent::NAME,
            new TaxAreaRuleBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): TaxAreaRuleWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): TaxAreaRuleWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): TaxAreaRuleWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
