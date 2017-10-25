<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\ProductDetailPrice\Event\ProductDetailPriceBasicLoadedEvent;
use Shopware\ProductDetailPrice\Event\ProductDetailPriceWrittenEvent;
use Shopware\ProductDetailPrice\Reader\ProductDetailPriceBasicReader;
use Shopware\ProductDetailPrice\Searcher\ProductDetailPriceSearcher;
use Shopware\ProductDetailPrice\Searcher\ProductDetailPriceSearchResult;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;
use Shopware\ProductDetailPrice\Writer\ProductDetailPriceWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductDetailPriceRepository implements RepositoryInterface
{
    /**
     * @var ProductDetailPriceBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductDetailPriceSearcher
     */
    private $searcher;

    /**
     * @var ProductDetailPriceWriter
     */
    private $writer;

    public function __construct(
        ProductDetailPriceBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        ProductDetailPriceSearcher $searcher,
        ProductDetailPriceWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductDetailPriceBasicCollection
    {
        if (empty($uuids)) {
            return new ProductDetailPriceBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductDetailPriceBasicLoadedEvent::NAME,
            new ProductDetailPriceBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductDetailPriceBasicCollection
    {
        return $this->readBasic($uuids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): ProductDetailPriceSearchResult
    {
        /** @var ProductDetailPriceSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductDetailPriceBasicLoadedEvent::NAME,
            new ProductDetailPriceBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ProductDetailPriceWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ProductDetailPriceWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ProductDetailPriceWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
