<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductDetail\Event\ProductDetailBasicLoadedEvent;
use Shopware\ProductDetail\Event\ProductDetailDetailLoadedEvent;
use Shopware\ProductDetail\Event\ProductDetailWrittenEvent;
use Shopware\ProductDetail\Loader\ProductDetailBasicLoader;
use Shopware\ProductDetail\Loader\ProductDetailDetailLoader;
use Shopware\ProductDetail\Searcher\ProductDetailSearcher;
use Shopware\ProductDetail\Searcher\ProductDetailSearchResult;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductDetail\Struct\ProductDetailDetailCollection;
use Shopware\ProductDetail\Writer\ProductDetailWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductDetailRepository
{
    /**
     * @var ProductDetailDetailLoader
     */
    protected $detailLoader;

    /**
     * @var ProductDetailBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductDetailSearcher
     */
    private $searcher;

    /**
     * @var ProductDetailWriter
     */
    private $writer;

    public function __construct(
        ProductDetailDetailLoader $detailLoader,
        ProductDetailBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ProductDetailSearcher $searcher,
        ProductDetailWriter $writer
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductDetailDetailCollection
    {
        if (empty($uuids)) {
            return new ProductDetailDetailCollection();
        }
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductDetailDetailLoadedEvent::NAME,
            new ProductDetailDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): ProductDetailBasicCollection
    {
        if (empty($uuids)) {
            return new ProductDetailBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductDetailBasicLoadedEvent::NAME,
            new ProductDetailBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ProductDetailSearchResult
    {
        /** @var ProductDetailSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductDetailBasicLoadedEvent::NAME,
            new ProductDetailBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ProductDetailWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ProductDetailWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ProductDetailWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
