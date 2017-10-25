<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\ShippingMethod\Event\ShippingMethodBasicLoadedEvent;
use Shopware\ShippingMethod\Event\ShippingMethodDetailLoadedEvent;
use Shopware\ShippingMethod\Event\ShippingMethodWrittenEvent;
use Shopware\ShippingMethod\Reader\ShippingMethodBasicReader;
use Shopware\ShippingMethod\Reader\ShippingMethodDetailReader;
use Shopware\ShippingMethod\Searcher\ShippingMethodSearcher;
use Shopware\ShippingMethod\Searcher\ShippingMethodSearchResult;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicCollection;
use Shopware\ShippingMethod\Struct\ShippingMethodDetailCollection;
use Shopware\ShippingMethod\Writer\ShippingMethodWriter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShippingMethodRepository
{
    /**
     * @var ShippingMethodDetailReader
     */
    protected $detailReader;

    /**
     * @var ShippingMethodBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ShippingMethodSearcher
     */
    private $searcher;

    /**
     * @var ShippingMethodWriter
     */
    private $writer;

    public function __construct(
        ShippingMethodDetailReader $detailReader,
        ShippingMethodBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        ShippingMethodSearcher $searcher,
        ShippingMethodWriter $writer
    ) {
        $this->detailReader = $detailReader;
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): ShippingMethodBasicCollection
    {
        if (empty($uuids)) {
            return new ShippingMethodBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            ShippingMethodBasicLoadedEvent::NAME,
            new ShippingMethodBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): ShippingMethodDetailCollection
    {
        if (empty($uuids)) {
            return new ShippingMethodDetailCollection();
        }
        $collection = $this->detailReader->readDetail($uuids, $context);

        $this->eventDispatcher->dispatch(
            ShippingMethodDetailLoadedEvent::NAME,
            new ShippingMethodDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): ShippingMethodSearchResult
    {
        /** @var ShippingMethodSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ShippingMethodBasicLoadedEvent::NAME,
            new ShippingMethodBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): ShippingMethodWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): ShippingMethodWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): ShippingMethodWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
