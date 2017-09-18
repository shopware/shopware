<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\PaymentMethod\Event\PaymentMethodBasicLoadedEvent;
use Shopware\PaymentMethod\Event\PaymentMethodDetailLoadedEvent;
use Shopware\PaymentMethod\Event\PaymentMethodWrittenEvent;
use Shopware\PaymentMethod\Loader\PaymentMethodBasicLoader;
use Shopware\PaymentMethod\Loader\PaymentMethodDetailLoader;
use Shopware\PaymentMethod\Searcher\PaymentMethodSearcher;
use Shopware\PaymentMethod\Searcher\PaymentMethodSearchResult;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodDetailCollection;
use Shopware\PaymentMethod\Writer\PaymentMethodWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PaymentMethodRepository
{
    /**
     * @var PaymentMethodDetailLoader
     */
    protected $detailLoader;

    /**
     * @var PaymentMethodBasicLoader
     */
    private $basicLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PaymentMethodSearcher
     */
    private $searcher;

    /**
     * @var PaymentMethodWriter
     */
    private $writer;

    public function __construct(
        PaymentMethodDetailLoader $detailLoader,
        PaymentMethodBasicLoader $basicLoader,
        EventDispatcherInterface $eventDispatcher,
        PaymentMethodSearcher $searcher,
        PaymentMethodWriter $writer
    ) {
        $this->detailLoader = $detailLoader;
        $this->basicLoader = $basicLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readDetail(array $uuids, TranslationContext $context): PaymentMethodDetailCollection
    {
        if (empty($uuids)) {
            return new PaymentMethodDetailCollection();
        }
        $collection = $this->detailLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            PaymentMethodDetailLoadedEvent::NAME,
            new PaymentMethodDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function read(array $uuids, TranslationContext $context): PaymentMethodBasicCollection
    {
        if (empty($uuids)) {
            return new PaymentMethodBasicCollection();
        }

        $collection = $this->basicLoader->load($uuids, $context);

        $this->eventDispatcher->dispatch(
            PaymentMethodBasicLoadedEvent::NAME,
            new PaymentMethodBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): PaymentMethodSearchResult
    {
        /** @var PaymentMethodSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            PaymentMethodBasicLoadedEvent::NAME,
            new PaymentMethodBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): PaymentMethodWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): PaymentMethodWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): PaymentMethodWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $this->eventDispatcher->dispatch($event::NAME, $event);

        return $event;
    }
}
