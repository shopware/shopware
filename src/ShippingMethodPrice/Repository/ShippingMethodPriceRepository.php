<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\ShippingMethodPrice\Event\ShippingMethodPriceBasicLoadedEvent;
use Shopware\ShippingMethodPrice\Event\ShippingMethodPriceWrittenEvent;
use Shopware\ShippingMethodPrice\Reader\ShippingMethodPriceBasicReader;
use Shopware\ShippingMethodPrice\Searcher\ShippingMethodPriceSearcher;
use Shopware\ShippingMethodPrice\Searcher\ShippingMethodPriceSearchResult;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicCollection;
use Shopware\ShippingMethodPrice\Writer\ShippingMethodPriceWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShippingMethodPriceRepository implements RepositoryInterface
{
    /**
     * @var ShippingMethodPriceBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ShippingMethodPriceSearcher
     */
    private $searcher;

    /**
     * @var ShippingMethodPriceWriter
     */
    private $writer;

    public function __construct(
        ShippingMethodPriceBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        ShippingMethodPriceSearcher $searcher,
        ShippingMethodPriceWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): ShippingMethodPriceBasicCollection
    {
        if (empty($uuids)) {
            return new ShippingMethodPriceBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            ShippingMethodPriceBasicLoadedEvent::NAME,
            new ShippingMethodPriceBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): ShippingMethodPriceBasicCollection
    {
        return $this->readBasic($uuids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): ShippingMethodPriceSearchResult
    {
        /** @var ShippingMethodPriceSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ShippingMethodPriceBasicLoadedEvent::NAME,
            new ShippingMethodPriceBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ShippingMethodPriceWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ShippingMethodPriceWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ShippingMethodPriceWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
