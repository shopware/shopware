<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\ShopTemplate\Event\ShopTemplateBasicLoadedEvent;
use Shopware\ShopTemplate\Event\ShopTemplateWrittenEvent;
use Shopware\ShopTemplate\Loader\ShopTemplateBasicLoader;
use Shopware\ShopTemplate\Searcher\ShopTemplateSearcher;
use Shopware\ShopTemplate\Searcher\ShopTemplateSearchResult;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicCollection;
use Shopware\ShopTemplate\Writer\ShopTemplateWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShopTemplateRepository
{
    /**
     * @var ShopTemplateBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ShopTemplateSearcher
     */
    private $searcher;

    /**
     * @var ShopTemplateWriter
     */
    private $writer;

    public function __construct(
        ShopTemplateBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        ShopTemplateSearcher $searcher,
        ShopTemplateWriter $writer
    ) {
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function read(array $uuids, TranslationContext $context): ShopTemplateBasicCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            ShopTemplateBasicLoadedEvent::NAME,
            new ShopTemplateBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ShopTemplateSearchResult
    {
        /** @var ShopTemplateSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ShopTemplateBasicLoadedEvent::NAME,
            new ShopTemplateBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ShopTemplateWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ShopTemplateWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ShopTemplateWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
