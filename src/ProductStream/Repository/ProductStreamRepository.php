<?php declare(strict_types=1);

namespace Shopware\ProductStream\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\ProductStream\Event\ProductStreamBasicLoadedEvent;
use Shopware\ProductStream\Event\ProductStreamWrittenEvent;
use Shopware\ProductStream\Reader\ProductStreamBasicReader;
use Shopware\ProductStream\Searcher\ProductStreamSearcher;
use Shopware\ProductStream\Searcher\ProductStreamSearchResult;
use Shopware\ProductStream\Struct\ProductStreamBasicCollection;
use Shopware\ProductStream\Writer\ProductStreamWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductStreamRepository implements RepositoryInterface
{
    /**
     * @var ProductStreamBasicReader
     */
    private $basicReader;

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
        ProductStreamBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        ProductStreamSearcher $searcher,
        ProductStreamWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductStreamBasicCollection
    {
        if (empty($uuids)) {
            return new ProductStreamBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductStreamBasicLoadedEvent::NAME,
            new ProductStreamBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductStreamBasicCollection
    {
        return $this->readBasic($uuids, $context);
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
