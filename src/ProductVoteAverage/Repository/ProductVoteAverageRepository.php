<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductVoteAverage\Event\ProductVoteAverageBasicLoadedEvent;
use Shopware\ProductVoteAverage\Event\ProductVoteAverageWrittenEvent;
use Shopware\ProductVoteAverage\Loader\ProductVoteAverageBasicLoader;
use Shopware\ProductVoteAverage\Searcher\ProductVoteAverageSearcher;
use Shopware\ProductVoteAverage\Searcher\ProductVoteAverageSearchResult;
use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicCollection;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductVoteAverageRepository
{
    /**
     * @var ProductVoteAverageBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductVoteAverageSearcher
     */
    private $searcher;

    public function __construct(
        ProductVoteAverageBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ProductVoteAverageSearcher $searcher
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
    }

    public function read(array $uuids, TranslationContext $context): ProductVoteAverageBasicCollection
    {
        if (empty($uuids)) {
            return new ProductVoteAverageBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductVoteAverageBasicLoadedEvent::NAME,
            new ProductVoteAverageBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ProductVoteAverageSearchResult
    {
        /** @var ProductVoteAverageSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductVoteAverageBasicLoadedEvent::NAME,
            new ProductVoteAverageBasicLoadedEvent($result, $context)
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
