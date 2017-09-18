<?php declare(strict_types=1);

namespace Shopware\Media\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Media\Event\MediaBasicLoadedEvent;
use Shopware\Media\Event\MediaWrittenEvent;
use Shopware\Media\Loader\MediaBasicLoader;
use Shopware\Media\Searcher\MediaSearcher;
use Shopware\Media\Searcher\MediaSearchResult;
use Shopware\Media\Struct\MediaBasicCollection;
use Shopware\Media\Writer\MediaWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MediaRepository
{
    /**
     * @var MediaBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var MediaSearcher
     */
    private $searcher;

    /**
     * @var MediaWriter
     */
    private $writer;

    public function __construct(
        MediaBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        MediaSearcher $searcher,
        MediaWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): MediaBasicCollection
    {
        if (empty($uuids)) {
            return new MediaBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            MediaBasicLoadedEvent::NAME,
            new MediaBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): MediaSearchResult
    {
        /** @var MediaSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            MediaBasicLoadedEvent::NAME,
            new MediaBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): MediaWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): MediaWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): MediaWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
