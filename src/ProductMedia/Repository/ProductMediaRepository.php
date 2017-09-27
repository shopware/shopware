<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductMedia\Event\ProductMediaBasicLoadedEvent;
use Shopware\ProductMedia\Event\ProductMediaWrittenEvent;
use Shopware\ProductMedia\Loader\ProductMediaBasicLoader;
use Shopware\ProductMedia\Searcher\ProductMediaSearcher;
use Shopware\ProductMedia\Searcher\ProductMediaSearchResult;
use Shopware\ProductMedia\Struct\ProductMediaBasicCollection;
use Shopware\ProductMedia\Writer\ProductMediaWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductMediaRepository
{
    /**
     * @var ProductMediaBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductMediaSearcher
     */
    private $searcher;

    /**
     * @var ProductMediaWriter
     */
    private $writer;

    public function __construct(
        ProductMediaBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ProductMediaSearcher $searcher,
        ProductMediaWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): ProductMediaBasicCollection
    {
        if (empty($uuids)) {
            return new ProductMediaBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductMediaBasicLoadedEvent::NAME,
            new ProductMediaBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ProductMediaSearchResult
    {
        /** @var ProductMediaSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductMediaBasicLoadedEvent::NAME,
            new ProductMediaBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ProductMediaWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ProductMediaWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ProductMediaWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
