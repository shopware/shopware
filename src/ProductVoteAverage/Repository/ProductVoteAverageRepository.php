<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\ProductVoteAverage\Event\ProductVoteAverageBasicLoadedEvent;
use Shopware\ProductVoteAverage\Event\ProductVoteAverageWrittenEvent;
use Shopware\ProductVoteAverage\Reader\ProductVoteAverageBasicReader;
use Shopware\ProductVoteAverage\Searcher\ProductVoteAverageSearcher;
use Shopware\ProductVoteAverage\Searcher\ProductVoteAverageSearchResult;
use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicCollection;
use Shopware\ProductVoteAverage\Writer\ProductVoteAverageWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductVoteAverageRepository implements RepositoryInterface
{
    /**
     * @var ProductVoteAverageBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductVoteAverageSearcher
     */
    private $searcher;

    /**
     * @var ProductVoteAverageWriter
     */
    private $writer;

    public function __construct(
        ProductVoteAverageBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        ProductVoteAverageSearcher $searcher,
        ProductVoteAverageWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductVoteAverageBasicCollection
    {
        if (empty($uuids)) {
            return new ProductVoteAverageBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductVoteAverageBasicLoadedEvent::NAME,
            new ProductVoteAverageBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductVoteAverageBasicCollection
    {
        return $this->readBasic($uuids, $context);
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

    public function update(array $data, TranslationContext $context): ProductVoteAverageWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ProductVoteAverageWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ProductVoteAverageWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
