<?php declare(strict_types=1);

namespace Shopware\Category\Repository;

use Shopware\Category\Event\CategoryBasicLoadedEvent;
use Shopware\Category\Event\CategoryDetailLoadedEvent;
use Shopware\Category\Event\CategoryWrittenEvent;
use Shopware\Category\Reader\CategoryBasicReader;
use Shopware\Category\Reader\CategoryDetailReader;
use Shopware\Category\Searcher\CategorySearcher;
use Shopware\Category\Searcher\CategorySearchResult;
use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Category\Struct\CategoryDetailCollection;
use Shopware\Category\Writer\CategoryWriter;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CategoryRepository
{
    /**
     * @var CategoryDetailReader
     */
    protected $detailReader;

    /**
     * @var CategoryBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CategorySearcher
     */
    private $searcher;

    /**
     * @var CategoryWriter
     */
    private $writer;

    public function __construct(
        CategoryDetailReader $detailReader,
        CategoryBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        CategorySearcher $searcher,
        CategoryWriter $writer
    ) {
        $this->detailReader = $detailReader;
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): CategoryBasicCollection
    {
        if (empty($uuids)) {
            return new CategoryBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            CategoryBasicLoadedEvent::NAME,
            new CategoryBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): CategoryDetailCollection
    {
        if (empty($uuids)) {
            return new CategoryDetailCollection();
        }
        $collection = $this->detailReader->readDetail($uuids, $context);

        $this->eventDispatcher->dispatch(
            CategoryDetailLoadedEvent::NAME,
            new CategoryDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): CategorySearchResult
    {
        /** @var CategorySearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            CategoryBasicLoadedEvent::NAME,
            new CategoryBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): CategoryWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): CategoryWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): CategoryWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
