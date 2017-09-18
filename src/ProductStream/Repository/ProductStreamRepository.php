<?php declare(strict_types=1);

namespace Shopware\ProductStream\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductStream\Event\ProductStreamBasicLoadedEvent;
use Shopware\ProductStream\Event\ProductStreamWrittenEvent;
use Shopware\ProductStream\Loader\ProductStreamBasicLoader;
use Shopware\ProductStream\Searcher\ProductStreamSearcher;
use Shopware\ProductStream\Searcher\ProductStreamSearchResult;
use Shopware\ProductStream\Struct\ProductStreamBasicCollection;
use Shopware\ProductStream\Writer\ProductStreamWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductStreamRepository
{
    /**
     * @var ProductStreamBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductStreamSearcher
     */
    private $searcher;

    /**
     * @var ProductStreamWriter
     */
    private $writer;

    public function __construct(
        ProductStreamBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ProductStreamSearcher $searcher,
        ProductStreamWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): ProductStreamBasicCollection
    {
        if (empty($uuids)) {
            return new ProductStreamBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductStreamBasicLoadedEvent::NAME,
            new ProductStreamBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ProductStreamSearchResult
    {
        /** @var ProductStreamSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductStreamBasicLoadedEvent::NAME,
            new ProductStreamBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ProductStreamWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ProductStreamWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ProductStreamWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
