<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\ProductListingPrice\Event\ProductListingPriceBasicLoadedEvent;
use Shopware\ProductListingPrice\Event\ProductListingPriceWrittenEvent;
use Shopware\ProductListingPrice\Reader\ProductListingPriceBasicReader;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearcher;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearchResult;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicCollection;
use Shopware\ProductListingPrice\Writer\ProductListingPriceWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductListingPriceRepository implements RepositoryInterface
{
    /**
     * @var ProductListingPriceBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductListingPriceSearcher
     */
    private $searcher;

    /**
     * @var ProductListingPriceWriter
     */
    private $writer;

    public function __construct(
        ProductListingPriceBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        ProductListingPriceSearcher $searcher,
        ProductListingPriceWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductListingPriceBasicCollection
    {
        if (empty($uuids)) {
            return new ProductListingPriceBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductListingPriceBasicLoadedEvent::NAME,
            new ProductListingPriceBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductListingPriceBasicCollection
    {
        return $this->readBasic($uuids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): ProductListingPriceSearchResult
    {
        /** @var ProductListingPriceSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductListingPriceBasicLoadedEvent::NAME,
            new ProductListingPriceBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ProductListingPriceWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ProductListingPriceWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ProductListingPriceWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
