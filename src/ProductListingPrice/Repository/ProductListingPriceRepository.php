<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductListingPrice\Event\ProductListingPriceBasicLoadedEvent;
use Shopware\ProductListingPrice\Event\ProductListingPriceWrittenEvent;
use Shopware\ProductListingPrice\Loader\ProductListingPriceBasicLoader;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearcher;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearchResult;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicCollection;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductListingPriceRepository
{
    /**
     * @var ProductListingPriceBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductListingPriceSearcher
     */
    private $searcher;

    public function __construct(
        ProductListingPriceBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ProductListingPriceSearcher $searcher
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
    }

    public function read(array $uuids, TranslationContext $context): ProductListingPriceBasicCollection
    {
        if (empty($uuids)) {
            return new ProductListingPriceBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductListingPriceBasicLoadedEvent::NAME,
            new ProductListingPriceBasicLoadedEvent($collection, $context)
        );

        return $collection;
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
}
