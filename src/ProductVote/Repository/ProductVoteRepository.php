<?php declare(strict_types=1);

namespace Shopware\ProductVote\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\ProductVote\Event\ProductVoteBasicLoadedEvent;
use Shopware\ProductVote\Event\ProductVoteWrittenEvent;
use Shopware\ProductVote\Reader\ProductVoteBasicReader;
use Shopware\ProductVote\Searcher\ProductVoteSearcher;
use Shopware\ProductVote\Searcher\ProductVoteSearchResult;
use Shopware\ProductVote\Struct\ProductVoteBasicCollection;
use Shopware\ProductVote\Writer\ProductVoteWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductVoteRepository implements RepositoryInterface
{
    /**
     * @var ProductVoteBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductVoteSearcher
     */
    private $searcher;

    /**
     * @var ProductVoteWriter
     */
    private $writer;

    public function __construct(
        ProductVoteBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        ProductVoteSearcher $searcher,
        ProductVoteWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductVoteBasicCollection
    {
        if (empty($uuids)) {
            return new ProductVoteBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            ProductVoteBasicLoadedEvent::NAME,
            new ProductVoteBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): ProductVoteBasicCollection
    {
        return $this->readBasic($uuids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): ProductVoteSearchResult
    {
        /** @var ProductVoteSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductVoteBasicLoadedEvent::NAME,
            new ProductVoteBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ProductVoteWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ProductVoteWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ProductVoteWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
