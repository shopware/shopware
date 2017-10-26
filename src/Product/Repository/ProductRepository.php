<?php declare(strict_types=1);

namespace Shopware\Product\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\Product\Event\ProductBasicLoadedEvent;
use Shopware\Product\Event\ProductDetailLoadedEvent;
use Shopware\Product\Event\ProductWrittenEvent;
use Shopware\Product\Reader\ProductBasicReader;
use Shopware\Product\Reader\ProductDetailReader;
use Shopware\Product\Searcher\ProductSearcher;
use Shopware\Product\Searcher\ProductSearchResult;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\Product\Struct\ProductDetailCollection;
use Shopware\Product\Writer\ProductWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductRepository
{
    /**
     * @var ProductDetailReader
     */
    protected $detailReader;

    /**
     * @var ProductBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductSearcher
     */
    private $searcher;

    /**
     * @var ProductWriter
     */
    private $writer;

    public function __construct(
        ProductDetailReader $detailReader,
        ProductBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        ProductSearcher $searcher,
        ProductWriter $writer
    ) {
        $this->detailReader = $detailReader;
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductBasicCollection
    {
        if (empty($uuids)) {
            return new ProductBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductBasicLoadedEvent::NAME,
            new ProductBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductDetailCollection
    {
        if (empty($uuids)) {
            return new ProductDetailCollection();
        }
        $collection = $this->detailReader->readDetail($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductDetailLoadedEvent::NAME,
            new ProductDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ProductSearchResult
    {
        /** @var ProductSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductBasicLoadedEvent::NAME,
            new ProductBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ProductWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ProductWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ProductWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
